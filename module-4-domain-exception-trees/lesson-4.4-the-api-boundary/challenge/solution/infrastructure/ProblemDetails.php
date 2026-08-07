<?php
declare(strict_types=1);

namespace Infrastructure;

/**
 * An RFC 9457 Problem Details object.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * RFC 9457, "Problem Details for HTTP APIs", July 2023. It OBSOLETES RFC 7807,
 * which is what most material on the internet still cites — including several
 * framework packages whose README says 7807 and whose code is fine, because the
 * wire format did not change. What changed was the registry and the guidance;
 * cite the current one.
 *
 * THE FIVE MEMBERS, AND WHAT EACH IS ACTUALLY FOR
 * ------------------------------------------------
 *   type      A URI REFERENCE identifying the problem TYPE. Defaults to
 *             "about:blank". It is an identifier, not a link — it need not
 *             dereference, though it is better if it does.
 *
 *   title     A short, human-readable summary of the TYPE. Must not change
 *             from occurrence to occurrence. "Illegal status transition".
 *
 *   status    The HTTP status code, duplicated into the body. Advisory: the
 *             real one is on the response. It is here because bodies get logged
 *             and forwarded without their headers.
 *
 *   detail    Human-readable, specific to THIS OCCURRENCE. "Job card JC-1042 is
 *             In Progress and cannot become Invoiced."
 *
 *   instance  A URI reference identifying this specific occurrence.
 *             "/job-cards/JC-1042/invoice".
 *
 * TITLE VERSUS DETAIL IS THE ONE PEOPLE GET WRONG
 * ------------------------------------------------
 * `title` describes the KIND of problem and is the same every time. `detail`
 * describes THIS one and is different every time.
 *
 * The practical consequence: a client may group, count and alert on `type` and
 * `title`. If the title contains an id, every occurrence is a distinct problem
 * type, and a dashboard counting them is useless. The RFC says clients should
 * not parse `detail` for the same reason Lesson 4.2 said they should not parse
 * a message.
 *
 * EXTENSION MEMBERS
 * ------------------
 * Any other top-level member. This is where `context()` from Lesson 4.2 goes,
 * and it is the reason that lesson insisted on scalars and lists of scalars:
 *
 *     {
 *       "type": "https://workshop.example/problems/illegal-status-transition",
 *       "title": "Illegal status transition",
 *       "status": 409,
 *       "detail": "Job card JC-1042 is In Progress and cannot become Invoiced.",
 *       "instance": "/job-cards/JC-1042/invoice",
 *       "jobCardId": "JC-1042",
 *       "from": "in_progress",
 *       "to": "invoiced",
 *       "allowed": ["awaiting_authorisation", "awaiting_parts", "completed", "cancelled"]
 *     }
 *
 * Note they are TOP LEVEL, not nested under "extensions". That is the format,
 * and it has a consequence this class enforces: an extension member may not be
 * named `type`, `title`, `status`, `detail` or `instance`, because it would
 * silently overwrite one. A domain that put a `status` key in its context —
 * which `JobCardIsTerminal` very nearly does — would otherwise replace the HTTP
 * status code with the string "invoiced".
 *
 * That is not a hypothetical. It is why the constructor refuses it.
 *
 * THE MEDIA TYPE
 * ---------------
 * `application/problem+json`, not `application/json`. It is how a client knows
 * to look for these members without guessing, and it is the part most
 * implementations forget.
 */
final readonly class ProblemDetails implements \JsonSerializable
{
    /** The five members a client may rely on. Nothing may shadow them. */
    public const array RESERVED = ['type', 'title', 'status', 'detail', 'instance'];

    public const string MEDIA_TYPE = 'application/problem+json';

    /**
     * @param array<string, mixed> $extensions
     */
    private function __construct(
        public string $type,
        public string $title,
        public int $status,
        public ?string $detail,
        public ?string $instance,
        public array $extensions,
    ) {}

    /**
     * @param array<string, mixed> $extensions must be JSON-encodable. In
     *        practice this is a failure's context() — scalars and lists of
     *        scalars, which Lesson 4.2 required for exactly this moment — plus
     *        whatever the mapper adds.
     *
     * @throws \InvalidArgumentException an extension member would shadow a reserved one
     */
    public static function of(
        string $type,
        string $title,
        int $status,
        ?string $detail = null,
        ?string $instance = null,
        array $extensions = [],
    ): self {
        $clashes = array_intersect(array_keys($extensions), self::RESERVED);

        if ($clashes !== []) {
            throw new \InvalidArgumentException(sprintf(
                'Extension member(s) %s would overwrite a reserved RFC 9457 member. '
                . 'Rename them in the failure context.',
                implode(', ', $clashes)
            ));
        }

        if ($status < 400 || $status > 599) {
            throw new \InvalidArgumentException(sprintf(
                'A problem status must be 4xx or 5xx; got %d. A 2xx problem is a '
                . 'contradiction, and Lesson 4.1 is about the version of this mistake '
                . 'that goes the other way.',
                $status
            ));
        }

        return new self($type, $title, $status, $detail, $instance, $extensions);
    }

    /**
     * The same problem, at a specific URL.
     *
     * A mapper does not know the request path and should not; a route does.
     * So `instance` is attached last, by whoever has it.
     *
     * `clone($this, [...])` — PHP 8.5's clone-with, which is the only way to
     * produce a modified copy of a readonly class without a constructor call
     * listing every property.
     */
    #[\NoDiscard('Returns a new ProblemDetails — the original is unchanged')]
    public function withInstance(string $instance): self
    {
        return clone($this, ['instance' => $instance]);
    }

    /**
     * The body, ready for json_encode.
     *
     * ORDER: reserved members first, extensions after. Not required by the RFC
     * — JSON objects are unordered — but a human reading a log wants the shape
     * of the thing before its particulars.
     *
     * Null members are OMITTED rather than serialised as null. `"detail": null`
     * is noise that every client has to skip, and the RFC treats absent and
     * null the same way.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $body = [
            'type'   => $this->type,
            'title'  => $this->title,
            'status' => $this->status,
        ];

        if ($this->detail !== null) {
            $body['detail'] = $this->detail;
        }

        if ($this->instance !== null) {
            $body['instance'] = $this->instance;
        }

        return $body + $this->extensions;
    }

    /**
     * BOTH FLAGS ARE ABOUT BEING READABLE ON THE WIRE.
     *
     * JSON_UNESCAPED_SLASHES so the `type` URI reads as a URI in a log rather
     * than as `https:\/\/workshop.example\/...`.
     *
     * JSON_UNESCAPED_UNICODE so an em dash stays an em dash instead of becoming
     * `—`. The domain's messages are full of them, and
     * `application/problem+json` is UTF-8 anyway.
     *
     * A NOTE ON HOW THE LEAK TEST IS WRITTEN, because it is tempting to test
     * this string: `checks.php` inspects the DECODED structure, not the JSON
     * text. Encoders escape things — a quoted part name becomes `\"`, an em
     * dash becomes `—` — so a raw-text search for a backslash reports a
     * leak for a message that merely quotes a part number.
     *
     * Testing the values means neither of those flags can cause a false alarm
     * or hide a real leak. `Workshop\JobCard` is a backslash in the decoded
     * string whatever the encoder did to it. A leak test that fires on correct
     * output is a leak test somebody switches off.
     */
    public function toJson(): string
    {
        return (string) json_encode(
            $this,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
