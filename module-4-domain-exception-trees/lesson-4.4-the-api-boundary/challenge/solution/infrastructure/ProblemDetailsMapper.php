<?php
declare(strict_types=1);

namespace Infrastructure;

use Workshop\CarriesContext;
use Workshop\Decision;
use Workshop\InvalidWorkshopInput;

/**
 * Turns anything thrown into an RFC 9457 body, without leaking the inside.
 *
 * REFERENCE IMPLEMENTATION. The last piece of Module 4, and the one that spends
 * everything the other three lessons built.
 *
 * WHY IT IS IN infrastructure/
 * -----------------------------
 * It knows about HTTP status codes, a URL prefix, and a media type. Apply the
 * course's question: if you deleted the framework, the database driver and the
 * web server, would a "409" still mean anything to Marcus? No. It is not a fact
 * about a workshop.
 *
 * The domain therefore holds `failureCode()` — a name for the kind of failure —
 * and this file holds the opinion about what that means over HTTP. A second
 * boundary (a queue consumer, a CLI, gRPC) writes its own table and the domain
 * does not change.
 *
 * IT SWITCHES ON THE CODE, NOT ON THE CLASS
 * ------------------------------------------
 * `match ($failure::class)` would work and would be wrong. It puts
 * `Workshop\IllegalStatusTransition` into infrastructure, so renaming a domain
 * class becomes a change to the boundary — and the day somebody splits that
 * class in two, this file silently stops matching and everything becomes a 500.
 *
 * Switching on `failureCode()` means the coupling is to a string the domain
 * declared as a public contract on purpose. Lesson 4.2 section 6.
 *
 * THE UNKNOWN CASE IS A 500, AND THAT IS DELIBERATE
 * --------------------------------------------------
 * A domain failure with no entry in the catalogue is a business refusal this
 * boundary does not know how to present. It becomes an opaque 500.
 *
 * That is uncomfortable — it is a 4xx being reported as a 5xx, which is the
 * mistake Lesson 4.1 opened with — and it is still right, because the
 * alternative is guessing. A wrong 4xx tells a client something false with
 * confidence; a 500 tells it we do not know, which is true.
 *
 * The real answer is that it must never happen, and that is a TEST rather than
 * a hope: `checks.php` walks every leaf of the tree and asserts each one maps
 * to something other than 500. That test is the answer to the objection in
 * Lesson 4.2's quiz — that a table in infrastructure will drift from the domain
 * it describes.
 */
final readonly class ProblemDetailsMapper
{
    /**
     * Where a `type` URI points.
     *
     * It need not dereference. It is better if it does: a client hitting a 409
     * they have never seen wants a page explaining what an
     * "illegal-status-transition" is, and a URI that 404s teaches them not to
     * bother looking.
     */
    public const string TYPE_PREFIX = 'https://workshop.example/problems/';

    /**
     * failure code -> what it means over HTTP.
     *
     * THE STATUS CODES, AND WHY EACH ONE
     * -----------------------------------
     *   404  the thing named in the URL is not there
     *   409  the request was understood; the current state forbids it
     *   422  the request was understood; the CONTENT of it is wrong
     *
     * 409 versus 422 is the branch of the tree, arriving at the wire:
     *
     *   WorkshopException     -> 409   "fix the world, then try again"
     *   InvalidWorkshopInput  -> 422   "fix your request, then try again"
     *
     * A client that automates retries needs that difference. Retrying a 422
     * with a corrected payload can succeed; retrying a 409 with the same
     * payload fails identically until something else changes. Send both as 400
     * and the difference is gone.
     *
     * WHY NOT 400
     * ------------
     * 400 means the request was malformed — bad JSON, a missing header, an
     * unparseable date. The body reaching this class parsed fine; it was
     * understood and refused. Using 400 for everything is the HTTP equivalent
     * of `throw new \DomainException`.
     *
     * @var array<string, array{status: int, title: string}>
     */
    private const array CATALOGUE = [
        'job-card-not-found' => [
            'status' => 404,
            'title'  => 'Job card not found',
        ],
        'line-not-found' => [
            'status' => 404,
            'title'  => 'Line not found',
        ],
        'job-card-already-exists' => [
            'status' => 409,
            'title'  => 'Job card already exists',
        ],
        'illegal-status-transition' => [
            'status' => 409,
            'title'  => 'Illegal status transition',
        ],
        'job-card-is-terminal' => [
            'status' => 409,
            'title'  => 'Job card is closed',
        ],
        'nothing-awaiting-authorisation' => [
            'status' => 409,
            'title'  => 'Nothing awaiting authorisation',
        ],
        'outstanding-authorisations' => [
            'status' => 409,
            'title'  => 'Work is awaiting authorisation',
        ],
        'line-currency-mismatch' => [
            'status' => 422,
            'title'  => 'Line currency does not match the job card',
        ],
        'missing-work-description' => [
            'status' => 422,
            'title'  => 'Missing work description',
        ],
    ];

    /**
     * Context keys that would shadow a reserved RFC 9457 member.
     *
     * ONE ENTRY, and it is a real collision rather than a defensive measure.
     * `JobCardIsTerminal` carries `status` — the JOB CARD's status, "invoiced".
     * RFC 9457 reserves `status` for the HTTP code. Serialise the context
     * unchanged and the body says `"status": "invoiced"`, which is not an
     * integer and is not 409, and every client's error handling breaks in a
     * different way.
     *
     * NOTE WHERE THE FIX IS. The domain does not rename its field. "Status" is
     * the workshop's word for the thing a job card is in — Rule 4 — and it was
     * their word before HTTP existed. The wire format's reservation is the wire
     * format's problem, so the rename happens here.
     *
     * This mechanism is CRUDE and it is worth saying so: it renames by key
     * name alone, so if a second failure ever carries a `status` meaning
     * something different, this table is wrong for one of them. At that point
     * the honest fix is a per-code rename table, or renaming in the domain and
     * accepting the cost. One entry does not justify either yet.
     *
     * @var array<string, string>
     */
    private const array RENAMES = [
        'status' => 'jobCardStatus',
    ];

    /**
     * @param array<string, int> $statusOverrides failure code -> status, for a
     *        route that disagrees with the catalogue. See withStatusFor().
     */
    public function __construct(private array $statusOverrides = []) {}

    /**
     * A mapper that disagrees with the catalogue about one code.
     *
     * THE CASE THIS EXISTS FOR, and it is the strongest argument against having
     * a single global table at all:
     *
     *     GET  /job-cards/JC-9999            -> JobCardNotFound is a 404.
     *                                           The URL names a thing that is
     *                                           not there.
     *
     *     POST /invoices {"jobCardId": ...}  -> JobCardNotFound is a 422.
     *                                           The URL is fine. The BODY names
     *                                           a thing that is not there.
     *
     * Same exception, same code, two correct answers — because the status is
     * about the REQUEST, and the domain does not know what was asked. That is
     * also the complete answer to "why not put httpStatus() on the exception":
     * the exception could not have known either.
     *
     * So the catalogue is a default, and a route that knows better says so.
     */
    #[\NoDiscard('Returns a new mapper — the original is unchanged')]
    public function withStatusFor(string $failureCode, int $status): self
    {
        return clone($this, [
            'statusOverrides' => $this->statusOverrides + [$failureCode => $status],
        ]);
    }

    /**
     * Anything thrown -> a body that is safe to send.
     *
     * THE ORDER OF THESE THREE BRANCHES IS THE WHOLE SECURITY MODEL.
     *
     * Only the first branch produces detail from the exception. Everything else
     * — a StorageFailure, a PDOException, a TypeError, a library exception, an
     * exception written next year by somebody who forgot the tree — falls to
     * `opaque()` and reveals nothing.
     *
     * That is the right default, and it is why this method takes `\Throwable`
     * rather than `WorkshopFailure`. A mapper that only accepted domain
     * failures would push the "what about everything else" decision back out to
     * every caller, and one of them would get it wrong by writing
     * `$e->getMessage()` into a 500.
     */
    public function map(\Throwable $failure, ?string $instance = null): ProblemDetails
    {
        if (!$failure instanceof CarriesContext) {
            return $this->opaque($instance);
        }

        $code  = $failure->failureCode();
        $entry = self::CATALOGUE[$code] ?? null;

        if ($entry === null) {
            // A domain failure this boundary cannot present. See the class doc
            // block: uncomfortable, correct, and asserted against in checks.php.
            return $this->opaque($instance);
        }

        return ProblemDetails::of(
            type: self::TYPE_PREFIX . $code,
            title: $entry['title'],
            status: $this->statusOverrides[$code] ?? $entry['status'],
            // SAFE, and worth knowing why rather than assuming: a domain
            // message is written for a person who runs the business. It names
            // job cards and statuses, never classes, files or SQL. Lesson 4.2
            // moved every message onto an exception in `Workshop\`, which is
            // what makes this line defensible.
            detail: $failure->getMessage(),
            instance: $instance,
            extensions: $this->extensionsFrom($failure),
        );
    }

    /**
     * A Decision -> one body carrying EVERY reason.
     *
     * The act-path throws one failure and this maps it. The ask-path — a form
     * submission that wants to report all of it at once — has a Decision, and
     * this is where Lesson 4.3 pays for itself at the boundary:
     *
     *     {
     *       "type":   ".../outstanding-authorisations",
     *       "status": 409,
     *       "detail": "...",
     *       "errors": [
     *         {"code": "outstanding-authorisations", "detail": "...", ...},
     *         {"code": "illegal-status-transition",  "detail": "...", ...}
     *       ]
     *     }
     *
     * THE STATUS IS THE FIRST REFUSAL'S, not the highest or the most severe.
     * That is deliberate: `Decision::orThrow()` throws the first, so a client
     * that submits the same request without asking first gets the same status
     * it would have got here. Two paths, one answer.
     *
     * `errors` as a plural extension member is the conventional shape for
     * multiple problems and it is what RFC 9457 section 3 suggests for the
     * case. It is a convention, not a registered member — which is worth
     * knowing before you tell a client it is standard.
     *
     * @throws \InvalidArgumentException the decision is allowed — there is no
     *         problem to report, and building one would be a lie
     */
    public function fromDecision(Decision $decision, ?string $instance = null): ProblemDetails
    {
        if ($decision->isAllowed()) {
            throw new \InvalidArgumentException(
                'An allowed Decision has no problem to report. A 2xx belongs here, '
                . 'not a problem document.'
            );
        }

        $first   = $decision->refusals[0];
        $primary = $this->mapCode($first->code, $first->message, $first->context, $instance);

        if (count($decision->refusals) === 1) {
            return $primary;
        }

        $errors = array_map(
            fn(\Workshop\Refusal $r): array => [
                'code'   => $r->code,
                'type'   => self::TYPE_PREFIX . $r->code,
                'detail' => $r->message,
            ] + $this->rename($r->context),
            $decision->refusals
        );

        return ProblemDetails::of(
            type: $primary->type,
            title: $primary->title,
            status: $primary->status,
            detail: $primary->detail,
            instance: $instance,
            extensions: $primary->extensions + ['errors' => $errors],
        );
    }

    /**
     * The 500. Everything a client is allowed to know about a failure this
     * boundary did not expect.
     *
     * NO detail, NO extensions, NO class name, NO message.
     *
     * The temptation is `'detail' => $failure->getMessage()`, "just for
     * debugging". That message is `SQLSTATE[HY000] [2002] Connection refused`,
     * or a file path, or half a query with a customer's email in it. The
     * operator gets it from the log, where Lesson 4.1's `previous` chain put
     * it. The client gets nothing, because the client can do nothing with it.
     *
     * `type` stays "about:blank" — the RFC's default, meaning "no more specific
     * type than the status code". A URI here would promise a documentation page
     * about a problem nobody has classified.
     */
    private function opaque(?string $instance): ProblemDetails
    {
        return ProblemDetails::of(
            type: 'about:blank',
            // When type is about:blank the RFC says title SHOULD be the status
            // phrase, which is exactly what this is.
            title: 'Internal Server Error',
            status: 500,
            detail: null,
            instance: $instance,
        );
    }

    /**
     * @param array<string, string|int|float|bool|null|list<string|int|float|bool|null>> $context
     */
    private function mapCode(
        string $code,
        string $message,
        array $context,
        ?string $instance,
    ): ProblemDetails {
        $entry = self::CATALOGUE[$code] ?? null;

        if ($entry === null) {
            return $this->opaque($instance);
        }

        return ProblemDetails::of(
            type: self::TYPE_PREFIX . $code,
            title: $entry['title'],
            status: $this->statusOverrides[$code] ?? $entry['status'],
            detail: $message,
            instance: $instance,
            extensions: $this->rename($context),
        );
    }

    /**
     * @return array<string, string|int|float|bool|null|list<string|int|float|bool|null>>
     */
    private function extensionsFrom(CarriesContext $failure): array
    {
        $context = $this->rename($failure->context());

        // A small extra, and it is the one piece of information the tree has
        // that the catalogue does not: which BRANCH this came from. A client
        // that wants to distinguish "your request was wrong" from "the world is
        // wrong" without hard-coding status codes can read it.
        return $context + [
            'kind' => $failure instanceof InvalidWorkshopInput ? 'input' : 'state',
        ];
    }

    /**
     * @param array<string, string|int|float|bool|null|list<string|int|float|bool|null>> $context
     *
     * @return array<string, string|int|float|bool|null|list<string|int|float|bool|null>>
     */
    private function rename(array $context): array
    {
        $renamed = [];

        foreach ($context as $key => $value) {
            $renamed[self::RENAMES[$key] ?? $key] = $value;
        }

        return $renamed;
    }
}
