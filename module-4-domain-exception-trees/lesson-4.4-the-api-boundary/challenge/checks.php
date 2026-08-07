<?php
declare(strict_types=1);

/**
 * checks.php — Lesson 4.4 acceptance checks
 * ==========================================
 *
 * THE SPECIFICATION for:
 *
 *     Infrastructure\ProblemDetails         an RFC 9457 document
 *     Infrastructure\ProblemDetailsMapper   anything thrown -> a safe body
 *
 * DO NOT EDIT.
 *
 * Section 4 is the lesson: nothing internal reaches the wire. Section 3 is the
 * answer to the objection that a table in infrastructure drifts from the domain
 * it describes.
 */

use Infrastructure\ProblemDetails;
use Infrastructure\ProblemDetailsMapper;
use Infrastructure\StorageFailure;
use Workshop\Currency;
use Workshop\Decision;
use Workshop\IllegalStatusTransition;
use Workshop\InvalidWorkshopInput;
use Workshop\JobCardAlreadyExists;
use Workshop\JobCardId;
use Workshop\JobCardIsTerminal;
use Workshop\JobCardNotFound;
use Workshop\JobCardStatus;
use Workshop\LineCurrencyMismatch;
use Workshop\LineId;
use Workshop\LineNotFound;
use Workshop\MissingWorkDescription;
use Workshop\NothingAwaitingAuthorisation;
use Workshop\OutstandingAuthorisations;
use Workshop\Refusal;

acceptance('Lesson 4.4 — The API boundary');


// ═══════════════════════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════════════════════

function mapper(): ProblemDetailsMapper
{
    return new ProblemDetailsMapper();
}

/** One instance of every leaf of the tree, keyed by failure code. */
function everyFailure(): array
{
    $id     = JobCardId::fromString('JC-1042');
    $lineId = LineId::fromString('LN-0001');

    $failures = [
        JobCardNotFound::withId($id),
        JobCardAlreadyExists::withId($id),
        IllegalStatusTransition::from($id, JobCardStatus::InProgress, JobCardStatus::Invoiced),
        JobCardIsTerminal::cannot($id, 'add a line to', JobCardStatus::Invoiced),
        LineNotFound::on($id, $lineId),
        LineCurrencyMismatch::between($id, Currency::EUR, 'Front pads', Currency::GBP),
        NothingAwaitingAuthorisation::on($id),
        OutstandingAuthorisations::on($id, ['Rear discs below minimum thickness']),
        MissingWorkDescription::forJobCard($id),
    ];

    $keyed = [];

    foreach ($failures as $failure) {
        $keyed[$failure->failureCode()] = $failure;
    }

    return $keyed;
}

/** Everything that must NOT produce a readable body. */
function everyOpaqueThing(): array
{
    return [
        'StorageFailure'    => StorageFailure::during('get', new PDOException('SQLSTATE[HY000] [2002] Connection refused')),
        'PDOException'      => new PDOException('SQLSTATE[HY000] [2002] Connection refused'),
        'RuntimeException'  => new RuntimeException('/var/www/app/src/Mapper.php failed at line 88'),
        'TypeError'         => new TypeError('Argument #1 ($money) must be of type Workshop\Money, string given'),
        'LogicException'    => new LogicException('unreachable'),
        'Error'             => new Error('Call to a member function total() on null'),
    ];
}

/**
 * Substrings that must never appear in any problem body.
 *
 * NOTE WHAT IS NOT HERE: the bare word "Error". The opaque body's title is
 * "Internal Server Error", which is the HTTP status phrase and exactly what
 * RFC 9457 asks for when `type` is about:blank. A leak test that fails on the
 * correct answer gets switched off, so the class names are caught by the
 * namespace separator and the file extension instead.
 */
function forbiddenInAnyBody(): array
{
    return [
        '\\'             => 'a namespace separator',
        'Infrastructure' => 'an infrastructure namespace',
        '.php'           => 'a file name',
        '/var/'          => 'an absolute path',
        'SQLSTATE'       => 'a driver error code',
        'Exception'      => 'a class name',
        '#0 '            => 'a stack frame',
        'Stack trace'    => 'a stack trace',
    ];
}

/**
 * Every string in a body — keys and scalar values, at any depth.
 *
 * THE CHECK RUNS ON THE DECODED STRUCTURE, NOT ON THE JSON TEXT, and that is
 * deliberate. `json_encode` escapes a literal `"` as `\"` and, without
 * JSON_UNESCAPED_UNICODE, an em dash as `—` — so a raw-text search for a
 * backslash reports a leak for a message that merely quotes a part name.
 *
 * Testing the values means an encoding choice can neither cause a false alarm
 * nor hide a real leak: `Workshop\JobCard` is a backslash in the decoded string
 * whatever the encoder did to it.
 *
 * @param array<string, mixed> $body
 *
 * @return list<string>
 */
function stringsIn(array $body): array
{
    $found = [];

    array_walk_recursive($body, static function (mixed $value, string|int $key) use (&$found): void {
        $found[] = (string) $key;

        if ($value === null || is_scalar($value)) {
            $found[] = (string) $value;
        }
    });

    return $found;
}

/** The first leak in a body, or null. */
function leakIn(array $body): ?string
{
    foreach (stringsIn($body) as $string) {
        foreach (forbiddenInAnyBody() as $needle => $why) {
            if (str_contains($string, $needle)) {
                return sprintf('%s (%s) in "%s"', $needle, $why, $string);
            }
        }
    }

    return null;
}

function captured(callable $fn): ?\Throwable
{
    try {
        $fn();
    } catch (\Throwable $e) {
        return $e;
    }

    return null;
}

function attributesOn(string $class, string $method): array
{
    return array_map(
        static fn(ReflectionAttribute $a): string => $a->getName(),
        (new ReflectionMethod($class, $method))->getAttributes()
    );
}


// ═══════════════════════════════════════════════════════════════════════════
// 1 — ProblemDetails is an RFC 9457 document
// ═══════════════════════════════════════════════════════════════════════════

check('Infrastructure\ProblemDetails exists, final and readonly',
    fn() => class_exists(ProblemDetails::class)
        && (new ReflectionClass(ProblemDetails::class))->isFinal()
        && (new ReflectionClass(ProblemDetails::class))->isReadOnly());

check('It is declared OUTSIDE domain/workshop/ — a 409 is not a fact about a workshop',
    fn() => !str_contains(
        str_replace('\\', '/', (string) (new ReflectionClass(ProblemDetails::class))->getFileName()),
        '/domain/workshop/'
    ));

check('The media type is application/problem+json, not application/json',
    fn() => ProblemDetails::MEDIA_TYPE === 'application/problem+json');

check('The five reserved members are named',
    function (): bool {
        $reserved = ProblemDetails::RESERVED;
        sort($reserved);

        return $reserved === ['detail', 'instance', 'status', 'title', 'type'];
    });

check('of() refuses an extension member that would shadow a reserved one',
    fn() => throws(InvalidArgumentException::class,
        static fn() => ProblemDetails::of('about:blank', 'x', 409, extensions: ['status' => 'invoiced'])));

check('of() refuses a status outside 4xx and 5xx',
    fn() => throws(InvalidArgumentException::class,
        static fn() => ProblemDetails::of('about:blank', 'x', 200))
        && throws(InvalidArgumentException::class,
        static fn() => ProblemDetails::of('about:blank', 'x', 302)));

check('jsonSerialize() omits null members rather than writing null',
    function (): bool {
        $body = ProblemDetails::of('about:blank', 'Internal Server Error', 500)->jsonSerialize();

        return !array_key_exists('detail', $body)
            && !array_key_exists('instance', $body);
    });

check('jsonSerialize() puts the reserved members first',
    function (): bool {
        $body = ProblemDetails::of(
            'https://example/p/x', 'X', 409, 'because', '/here', ['jobCardId' => 'JC-1']
        )->jsonSerialize();

        return array_keys($body) === ['type', 'title', 'status', 'detail', 'instance', 'jobCardId'];
    });

check('withInstance() returns a new object and carries #[\NoDiscard]',
    function (): bool {
        $original = ProblemDetails::of('about:blank', 'X', 409);
        $located  = $original->withInstance('/job-cards/JC-1042/invoice');

        return $original->instance === null
            && $located->instance === '/job-cards/JC-1042/invoice'
            && $located->title === 'X'
            && in_array('NoDiscard', attributesOn(ProblemDetails::class, 'withInstance'), true);
    });

check('toJson() leaves slashes and unicode unescaped',
    function (): bool {
        $json = ProblemDetails::of(
            'https://workshop.example/problems/x', 'X', 409, 'an em dash — here'
        )->toJson();

        return str_contains($json, 'https://workshop.example')
            && str_contains($json, '—')
            && !str_contains($json, '\\');
    });


// ═══════════════════════════════════════════════════════════════════════════
// 2 — The mapper renders a domain failure
// ═══════════════════════════════════════════════════════════════════════════

check('Infrastructure\ProblemDetailsMapper exists, outside the domain',
    fn() => class_exists(ProblemDetailsMapper::class)
        && !str_contains(
            str_replace('\\', '/', (string) (new ReflectionClass(ProblemDetailsMapper::class))->getFileName()),
            '/domain/workshop/'
        ));

check('map() takes a \Throwable, not a WorkshopFailure',
    function (): bool {
        $parameter = (new ReflectionMethod(ProblemDetailsMapper::class, 'map'))->getParameters()[0] ?? null;

        return (string) $parameter?->getType() === 'Throwable';
    });

check('An IllegalStatusTransition becomes a 409 with its context as extensions',
    function (): bool {
        $problem = mapper()->map(everyFailure()['illegal-status-transition']);

        return $problem->status === 409
            && $problem->type === ProblemDetailsMapper::TYPE_PREFIX . 'illegal-status-transition'
            && $problem->detail === everyFailure()['illegal-status-transition']->getMessage()
            && ($problem->extensions['from'] ?? null) === 'in_progress'
            && ($problem->extensions['to'] ?? null) === 'invoiced'
            && is_array($problem->extensions['allowed'] ?? null);
    });

check('A JobCardNotFound becomes a 404 by default',
    fn() => mapper()->map(everyFailure()['job-card-not-found'])->status === 404);

check('The title describes the TYPE and does not contain the identity',
    function (): bool {
        $problem = mapper()->map(everyFailure()['job-card-not-found']);

        return !str_contains($problem->title, 'JC-1042')
            && str_contains((string) $problem->detail, 'JC-1042');
    });

check('The type URI ends with the failure code, not a class name',
    fn() => count(array_filter(
        everyFailure(),
        static fn(object $f): bool => mapper()->map($f)->type
            === ProblemDetailsMapper::TYPE_PREFIX . $f->failureCode()
    )) === 9);

check('An instance can be attached by the route afterwards',
    fn() => mapper()->map(everyFailure()['job-card-not-found'], '/job-cards/JC-1042')->instance
        === '/job-cards/JC-1042');


// ═══════════════════════════════════════════════════════════════════════════
// 3 — EXHAUSTIVE: every leaf of the tree has an answer
// ═══════════════════════════════════════════════════════════════════════════
//
// This is the check that answers the objection to keeping the table outside
// the domain — that it will drift from the tree it describes. It will not,
// because this fails the build the day somebody adds a tenth leaf.

check('All nine failures map to something other than 500',
    fn() => count(array_filter(
        everyFailure(),
        static fn(object $f): bool => mapper()->map($f)->status !== 500
    )) === 9);

check('All nine map into 4xx',
    fn() => count(array_filter(
        everyFailure(),
        static fn(object $f): bool => mapper()->map($f)->status >= 400
            && mapper()->map($f)->status < 500
    )) === 9);

check('Every InvalidWorkshopInput is a 422 — "fix your request"',
    fn() => count(array_filter(
        everyFailure(),
        static fn(object $f): bool => !($f instanceof InvalidWorkshopInput)
            || mapper()->map($f)->status === 422
    )) === 9);

check('Nothing on the state branch is a 422 except the currency mismatch',
    function (): bool {
        foreach (everyFailure() as $code => $failure) {
            if ($failure instanceof InvalidWorkshopInput) {
                continue;
            }

            $status = mapper()->map($failure)->status;

            if ($status === 422 && $code !== 'line-currency-mismatch') {
                return false;
            }
        }

        return true;
    });

check('Every title is non-empty and distinct',
    function (): bool {
        $titles = array_map(static fn(object $f): string => mapper()->map($f)->title, everyFailure());

        return count(array_filter($titles)) === 9
            && count(array_unique($titles)) === 9;
    });

check('Every mapped body carries a `kind` saying which branch it came from',
    function (): bool {
        foreach (everyFailure() as $failure) {
            $kind     = mapper()->map($failure)->extensions['kind'] ?? null;
            $expected = $failure instanceof InvalidWorkshopInput ? 'input' : 'state';

            if ($kind !== $expected) {
                return false;
            }
        }

        return true;
    });


// ═══════════════════════════════════════════════════════════════════════════
// 4 — NOTHING INTERNAL REACHES THE WIRE
// ═══════════════════════════════════════════════════════════════════════════

check('Everything that is not a domain failure becomes an opaque 500',
    fn() => count(array_filter(
        everyOpaqueThing(),
        static fn(Throwable $t): bool => mapper()->map($t)->status === 500
    )) === count(everyOpaqueThing()));

check('...with no detail at all',
    fn() => count(array_filter(
        everyOpaqueThing(),
        static fn(Throwable $t): bool => mapper()->map($t)->detail === null
    )) === count(everyOpaqueThing()));

check('...and no extension members',
    fn() => count(array_filter(
        everyOpaqueThing(),
        static fn(Throwable $t): bool => mapper()->map($t)->extensions === []
    )) === count(everyOpaqueThing()));

check('...and type stays about:blank, with the status phrase as the title',
    fn() => count(array_filter(
        everyOpaqueThing(),
        static fn(Throwable $t): bool => mapper()->map($t)->type === 'about:blank'
            && mapper()->map($t)->title === 'Internal Server Error'
    )) === count(everyOpaqueThing()));

check('An \Error is opaque too — a bug is not a business failure',
    fn() => mapper()->map(new TypeError('Argument #1 ($m) must be of type Workshop\Money'))->status === 500);

check('No opaque body leaks anything — checked value by value',
    function (): bool {
        foreach (everyOpaqueThing() as $throwable) {
            $leak = leakIn(mapper()->map($throwable, '/job-cards/JC-1042/invoice')->jsonSerialize());

            if ($leak !== null) {
                throw new RuntimeException($leak);
            }
        }

        return true;
    });

check('No DOMAIN body leaks anything either',
    function (): bool {
        foreach (everyFailure() as $failure) {
            $leak = leakIn(mapper()->map($failure, '/job-cards/JC-1042/invoice')->jsonSerialize());

            if ($leak !== null) {
                throw new RuntimeException($leak);
            }
        }

        return true;
    });

check('The leak detector actually detects — a body with a class name fails it',
    fn() => leakIn(ProblemDetails::of(
        'about:blank',
        'Internal Server Error',
        500,
        'Workshop\JobCard::invoice() failed in /var/www/src/JobCard.php'
    )->jsonSerialize()) !== null);

check('...and does not fire on the correct opaque body',
    fn() => leakIn(mapper()->map(new RuntimeException('x'))->jsonSerialize()) === null);

check('A StorageFailure loses the SQLSTATE, which is on getPrevious() for the log',
    function (): bool {
        $driver  = new PDOException('SQLSTATE[HY000] [2002] Connection refused');
        $failure = StorageFailure::during('get', $driver);

        return !str_contains(mapper()->map($failure)->toJson(), 'SQLSTATE')
            && $failure->getPrevious() === $driver;
    });

check('Every body is valid JSON that round-trips',
    function (): bool {
        foreach ([...array_values(everyFailure()), ...array_values(everyOpaqueThing())] as $throwable) {
            $json = mapper()->map($throwable)->toJson();

            if (json_decode($json, true) === null) {
                return false;
            }
        }

        return true;
    });


// ═══════════════════════════════════════════════════════════════════════════
// 5 — The collision the domain refused to fix
// ═══════════════════════════════════════════════════════════════════════════
//
// JobCardIsTerminal carries `status` — the job card's, "invoiced". RFC 9457
// reserves `status` for the HTTP code. The domain keeps the workshop's word;
// infrastructure renames.

check('JobCardIsTerminal still carries `status` in its domain context',
    fn() => (everyFailure()['job-card-is-terminal']->context()['status'] ?? null) === 'invoiced');

check('...and the problem body\'s `status` is still the integer 409',
    function (): bool {
        $body = mapper()->map(everyFailure()['job-card-is-terminal'])->jsonSerialize();

        return $body['status'] === 409;
    });

check('...with the job card\'s own status renamed, not dropped',
    function (): bool {
        $body = mapper()->map(everyFailure()['job-card-is-terminal'])->jsonSerialize();

        return ($body['jobCardStatus'] ?? null) === 'invoiced';
    });


// ═══════════════════════════════════════════════════════════════════════════
// 6 — Same failure, two statuses: the route decides
// ═══════════════════════════════════════════════════════════════════════════

check('withStatusFor() overrides one code and returns a new mapper',
    function (): bool {
        $default  = mapper();
        $forPosts = $default->withStatusFor('job-card-not-found', 422);
        $failure  = everyFailure()['job-card-not-found'];

        return $default->map($failure)->status === 404
            && $forPosts->map($failure)->status === 422
            && $default->map($failure)->status === 404;   // unchanged
    });

check('An override changes nothing else about the body',
    function (): bool {
        $failure  = everyFailure()['job-card-not-found'];
        $default  = mapper()->map($failure);
        $override = mapper()->withStatusFor('job-card-not-found', 422)->map($failure);

        return $override->type === $default->type
            && $override->title === $default->title
            && $override->detail === $default->detail;
    });

check('withStatusFor() carries #[\NoDiscard]',
    fn() => in_array('NoDiscard', attributesOn(ProblemDetailsMapper::class, 'withStatusFor'), true));


// ═══════════════════════════════════════════════════════════════════════════
// 7 — A Decision at the boundary: every reason in one body
// ═══════════════════════════════════════════════════════════════════════════

function aTwoReasonDecision(): Decision
{
    $id = JobCardId::fromString('JC-1042');

    return Decision::refusedBecause(
        Refusal::from(OutstandingAuthorisations::on($id, ['Rear discs below minimum thickness'])),
        Refusal::from(IllegalStatusTransition::from(
            $id,
            JobCardStatus::AwaitingAuthorisation,
            JobCardStatus::Completed
        ))
    );
}

check('fromDecision() reports every refusal under `errors`',
    function (): bool {
        $body = mapper()->fromDecision(aTwoReasonDecision())->jsonSerialize();

        return is_array($body['errors'] ?? null) && count($body['errors']) === 2;
    });

check('...each with its own code, type and detail',
    function (): bool {
        $errors = mapper()->fromDecision(aTwoReasonDecision())->jsonSerialize()['errors'];

        return $errors[0]['code'] === 'outstanding-authorisations'
            && $errors[1]['code'] === 'illegal-status-transition'
            && str_starts_with($errors[1]['type'], ProblemDetailsMapper::TYPE_PREFIX)
            && $errors[0]['detail'] !== '';
    });

check('The document status is the FIRST refusal\'s — the same one orThrow() would give',
    function (): bool {
        $decision = aTwoReasonDecision();
        $viaAsk   = mapper()->fromDecision($decision)->status;

        $thrown  = captured(static fn() => $decision->orThrow());
        $viaAct  = mapper()->map($thrown)->status;

        return $viaAsk === $viaAct;
    });

check('...and so are the type and title',
    function (): bool {
        $decision = aTwoReasonDecision();
        $ask      = mapper()->fromDecision($decision);
        $act      = mapper()->map(captured(static fn() => $decision->orThrow()));

        return $ask->type === $act->type && $ask->title === $act->title;
    });

check('A single-refusal Decision produces the same body as mapping the exception',
    function (): bool {
        $failure  = OutstandingAuthorisations::on(JobCardId::fromString('JC-1042'), ['x']);
        $decision = Decision::refusedBecause(Refusal::from($failure));

        $fromDecision = mapper()->fromDecision($decision)->jsonSerialize();
        $fromFailure  = mapper()->map($failure)->jsonSerialize();

        // `kind` is added from the exception's branch, which a Refusal does not
        // carry — so compare the rest.
        unset($fromFailure['kind']);

        return $fromDecision === $fromFailure;
    });

check('fromDecision() refuses an ALLOWED decision — a 2xx is not a problem document',
    fn() => throws(InvalidArgumentException::class,
        static fn() => mapper()->fromDecision(Decision::allowed())));

check('No multi-error body leaks anything either',
    function (): bool {
        $leak = leakIn(
            mapper()->fromDecision(aTwoReasonDecision(), '/job-cards/JC-1042/completion')->jsonSerialize()
        );

        if ($leak !== null) {
            throw new RuntimeException($leak);
        }

        return true;
    });


acceptance_report();
