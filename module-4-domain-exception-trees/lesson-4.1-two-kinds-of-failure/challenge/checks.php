<?php
declare(strict_types=1);

/**
 * checks.php — Lesson 4.1 acceptance checks
 * ==========================================
 *
 * THE SPECIFICATION for:
 *
 *     Workshop\WorkshopFailure          the marker interface
 *     Workshop\JobCardAlreadyExists     a domain failure that had no name
 *     Workshop\JobCardNotFound          modified: it now carries the marker
 *     Infrastructure\StorageFailure     category (b), deliberately unmarked
 *     Infrastructure\GuardedJobCards    the translating decorator
 *     Infrastructure\InMemoryJobCards   modified: one throw site gets a name
 *
 * DO NOT EDIT.
 *
 * The checks under "THE RULE" are the lesson. Everything else is a decorator
 * delegating.
 */

use Infrastructure\GuardedJobCards;
use Infrastructure\InMemoryJobCards;
use Infrastructure\StorageFailure;
use Workshop\AccountHolderId;
use Workshop\Currency;
use Workshop\JobCard;
use Workshop\JobCardAlreadyExists;
use Workshop\JobCardId;
use Workshop\JobCardNotFound;
use Workshop\JobCards;
use Workshop\VehicleId;
use Workshop\WorkshopFailure;

acceptance('Lesson 4.1 — Two kinds of failure');


// ═══════════════════════════════════════════════════════════════════════════
// Helpers and a scripted test double
// ═══════════════════════════════════════════════════════════════════════════

function aJobCard(string $id = 'JC-1001', string $vehicle = 'VEH-4471'): JobCard
{
    return JobCard::open(
        JobCardId::fromString($id),
        VehicleId::fromString($vehicle),
        AccountHolderId::fromString('AH-0007'),
        Currency::EUR
    );
}

/**
 * A `JobCards` whose every method runs the same closure.
 *
 * Declared here rather than shipped with the lesson because it exists only to
 * put a chosen \Throwable in front of the decorator. It is not an
 * implementation anyone would use; it is a lever.
 */
final class ScriptedJobCards implements JobCards
{
    /** @param \Closure(string): mixed $behaviour */
    public function __construct(private readonly \Closure $behaviour) {}

    public function add(JobCard $jobCard): void       { ($this->behaviour)('add'); }
    public function get(JobCardId $id): JobCard       { return ($this->behaviour)('get'); }
    public function contains(JobCardId $id): bool     { return ($this->behaviour)('contains'); }
    public function remove(JobCardId $id): void       { ($this->behaviour)('remove'); }
    public function forVehicle(VehicleId $id): array  { return ($this->behaviour)('forVehicle'); }
    public function awaitingAuthorisation(): array    { return ($this->behaviour)('awaitingAuthorisation'); }
    public function count(): int                      { return ($this->behaviour)('count'); }
}

/** Run every method of a guarded repository, collecting what came out. */
function throughEveryMethod(JobCards $guarded): array
{
    $id      = JobCardId::fromString('JC-1001');
    $vehicle = VehicleId::fromString('VEH-4471');

    $calls = [
        'add'                   => static fn() => $guarded->add(aJobCard()),
        'get'                   => static fn() => $guarded->get($id),
        'contains'              => static fn() => $guarded->contains($id),
        'remove'                => static fn() => $guarded->remove($id),
        'forVehicle'            => static fn() => $guarded->forVehicle($vehicle),
        'awaitingAuthorisation' => static fn() => $guarded->awaitingAuthorisation(),
        'count'                 => static fn() => $guarded->count(),
    ];

    $outcomes = [];

    foreach ($calls as $method => $call) {
        try {
            $call();
            $outcomes[$method] = null;
        } catch (\Throwable $e) {
            $outcomes[$method] = $e;
        }
    }

    return $outcomes;
}

function guardedOver(\Closure $behaviour): JobCards
{
    return new GuardedJobCards(new ScriptedJobCards($behaviour));
}

/** @return string forward-slashed path of the file a class is declared in */
function fileOf(string $class): string
{
    $file = (new ReflectionClass($class))->getFileName();

    return $file === false ? '' : str_replace('\\', '/', $file);
}

/** @return list<string> public method names, excluding the constructor */
function publicMethodsOf(string $class): array
{
    $names = array_map(
        static fn(ReflectionMethod $m): string => $m->getName(),
        (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC)
    );

    sort($names);

    return array_values(array_filter($names, static fn(string $n): bool => $n !== '__construct'));
}


// ═══════════════════════════════════════════════════════════════════════════
// 1 — The marker interface
// ═══════════════════════════════════════════════════════════════════════════

check('Workshop\WorkshopFailure exists and is an interface',
    fn() => interface_exists(WorkshopFailure::class));

check('WorkshopFailure extends \Throwable',
    fn() => in_array('Throwable', class_implements(WorkshopFailure::class) ?: [], true));

check('WorkshopFailure declares no methods of its own',
    function (): bool {
        $own = (new ReflectionClass(WorkshopFailure::class))->getMethods();
        $inherited = (new ReflectionClass(Throwable::class))->getMethods();

        return count($own) === count($inherited);
    });

check('WorkshopFailure declares no constants',
    fn() => (new ReflectionClass(WorkshopFailure::class))->getConstants() === []);

check('WorkshopFailure is declared in domain/workshop/',
    fn() => declared_in(WorkshopFailure::class, 'domain/workshop'));

check('WorkshopFailure does not reference Infrastructure in code',
    fn() => !str_contains(class_source_without_comments(WorkshopFailure::class), 'Infrastructure'));


// ═══════════════════════════════════════════════════════════════════════════
// 2 — Domain failures carry the marker, and keep their SPL parents
// ═══════════════════════════════════════════════════════════════════════════

check('JobCardNotFound implements WorkshopFailure',
    fn() => is_a(JobCardNotFound::class, WorkshopFailure::class, true));

check('JobCardNotFound still extends \DomainException — Lesson 3.1 keeps passing',
    fn() => is_a(JobCardNotFound::class, DomainException::class, true));

check('JobCardNotFound still carries the identity it was given',
    function (): bool {
        $id = JobCardId::fromString('JC-9999');

        return JobCardNotFound::withId($id)->jobCardId?->equals($id) === true;
    });

check('Workshop\JobCardAlreadyExists exists and implements WorkshopFailure',
    fn() => class_exists(JobCardAlreadyExists::class)
        && is_a(JobCardAlreadyExists::class, WorkshopFailure::class, true));

check('JobCardAlreadyExists extends \DomainException',
    fn() => is_a(JobCardAlreadyExists::class, DomainException::class, true));

check('JobCardAlreadyExists is declared in domain/workshop/',
    fn() => declared_in(JobCardAlreadyExists::class, 'domain/workshop'));

check('JobCardAlreadyExists carries the identity, like JobCardNotFound does',
    function (): bool {
        $id = JobCardId::fromString('JC-1001');

        return JobCardAlreadyExists::withId($id)->jobCardId?->equals($id) === true;
    });

check('InMemoryJobCards throws JobCardAlreadyExists on a duplicate add',
    function (): bool {
        $repository = new InMemoryJobCards();
        $repository->add(aJobCard());

        return throws(JobCardAlreadyExists::class, static fn() => $repository->add(aJobCard()));
    });

check('...and that is still a \DomainException — Lesson 3.1 keeps passing',
    function (): bool {
        $repository = new InMemoryJobCards();
        $repository->add(aJobCard());

        return throws(DomainException::class, static fn() => $repository->add(aJobCard()));
    });


// ═══════════════════════════════════════════════════════════════════════════
// 3 — StorageFailure: category (b), and deliberately NOT marked
// ═══════════════════════════════════════════════════════════════════════════

check('Infrastructure\StorageFailure exists',
    fn() => class_exists(StorageFailure::class));

check('StorageFailure extends \RuntimeException, not \DomainException',
    fn() => is_a(StorageFailure::class, RuntimeException::class, true)
        && !is_a(StorageFailure::class, DomainException::class, true));

check('StorageFailure is NOT a WorkshopFailure — this is the whole point',
    fn() => !is_a(StorageFailure::class, WorkshopFailure::class, true));

check('StorageFailure is declared OUTSIDE domain/workshop/',
    fn() => class_exists(StorageFailure::class)
        && !str_contains(fileOf(StorageFailure::class), '/domain/workshop/'));

check('StorageFailure is in the Infrastructure namespace',
    fn() => (new ReflectionClass(StorageFailure::class))->getNamespaceName() === 'Infrastructure');


// ═══════════════════════════════════════════════════════════════════════════
// 4 — GuardedJobCards is a JobCards, and nothing more
// ═══════════════════════════════════════════════════════════════════════════

check('Infrastructure\GuardedJobCards implements Workshop\JobCards',
    fn() => is_a(GuardedJobCards::class, JobCards::class, true));

check('GuardedJobCards is declared OUTSIDE domain/workshop/',
    fn() => class_exists(GuardedJobCards::class)
        && !str_contains(fileOf(GuardedJobCards::class), '/domain/workshop/'));

check('GuardedJobCards adds no public method beyond the seven on the interface',
    fn() => publicMethodsOf(GuardedJobCards::class) === publicMethodsOf(JobCards::class));

check('GuardedJobCards accepts any JobCards, not one concrete class',
    function (): bool {
        $parameter = (new ReflectionClass(GuardedJobCards::class))
            ->getConstructor()?->getParameters()[0] ?? null;

        return (string) $parameter?->getType() === JobCards::class;
    });


// ═══════════════════════════════════════════════════════════════════════════
// 5 — THE RULE, first half: a WorkshopFailure passes through UNTOUCHED
// ═══════════════════════════════════════════════════════════════════════════
//
// "Untouched" means the same object. A rewrapped exception reports the
// decorator's line number in every log entry, and loses its subclass — which
// at the API boundary is the difference between a 404 and a 409.

$domainFailure = JobCardNotFound::withId(JobCardId::fromString('JC-1001'));

$passedThrough = throughEveryMethod(guardedOver(
    static fn(string $method): mixed => throw $domainFailure
));

check('Every method lets a WorkshopFailure escape',
    fn() => count(array_filter($passedThrough, static fn(?Throwable $e): bool => $e !== null)) === 7);

check('...and it is the SAME OBJECT, in all seven — asserted with ===',
    fn() => count(array_filter(
        $passedThrough,
        static fn(?Throwable $e): bool => $e === $domainFailure
    )) === 7);

check('...so the reported line is still where the rule was broken',
    fn() => ($passedThrough['get'] ?? null)?->getLine() === $domainFailure->getLine());


// ═══════════════════════════════════════════════════════════════════════════
// 6 — THE RULE, second half: anything else becomes a StorageFailure
// ═══════════════════════════════════════════════════════════════════════════

$driverFailure = new PDOException('SQLSTATE[HY000] [2002] Connection refused');

$translated = throughEveryMethod(guardedOver(
    static fn(string $method): mixed => throw $driverFailure
));

check('Every method translates a PDOException into a StorageFailure',
    fn() => count(array_filter(
        $translated,
        static fn(?Throwable $e): bool => $e instanceof StorageFailure
    )) === 7);

check('The original driver exception survives as getPrevious()',
    fn() => count(array_filter(
        $translated,
        static fn(?Throwable $e): bool => $e?->getPrevious() === $driverFailure
    )) === 7);

check('A StorageFailure message does not repeat the driver text',
    function () use ($translated): bool {
        $message = ($translated['get'] ?? null)?->getMessage() ?? '';

        return $message !== '' && !str_contains($message, 'SQLSTATE');
    });

check('Any \Exception is translated, not just PDOException',
    function (): bool {
        $guarded = guardedOver(static fn(string $method): mixed => throw new \UnexpectedValueException('bad row'));

        return throws(StorageFailure::class, static fn() => $guarded->count());
    });


// ═══════════════════════════════════════════════════════════════════════════
// 7 — THE RULE, the half everyone forgets: \Error is NOT translated
// ═══════════════════════════════════════════════════════════════════════════
//
// Category (c). A TypeError in a mapper is a bug in the mapper, and reporting
// it as a storage failure sends the operator to check a database that is fine.

$escaping = throughEveryMethod(guardedOver(
    static fn(string $method): mixed => throw new \TypeError('Argument #1 must be of type Money, string given')
));

check('A \TypeError is NOT wrapped as a StorageFailure',
    fn() => count(array_filter(
        $escaping,
        static fn(?Throwable $e): bool => $e instanceof StorageFailure
    )) === 0);

check('A \TypeError escapes the decorator unchanged, in all seven methods',
    fn() => count(array_filter(
        $escaping,
        static fn(?Throwable $e): bool => $e instanceof TypeError
    )) === 7);

check('A \DivisionByZeroError escapes too — it is \Error, not \Exception',
    function (): bool {
        $guarded = guardedOver(static fn(string $method): mixed => throw new \DivisionByZeroError('Division by zero'));

        return throws(DivisionByZeroError::class, static fn() => $guarded->count());
    });


// ═══════════════════════════════════════════════════════════════════════════
// 8 — It still behaves like a repository
// ═══════════════════════════════════════════════════════════════════════════
//
// A decorator that translates failures correctly and returns the wrong values
// has not helped anyone. Lesson 3.4's substitutability, re-asserted against a
// wrapper rather than a second implementation.

$guarded = new GuardedJobCards(new InMemoryJobCards());

check('add() then get() round-trips the same aggregate',
    function () use ($guarded): bool {
        $jobCard = aJobCard('JC-2001');
        $guarded->add($jobCard);

        return $guarded->get(JobCardId::fromString('JC-2001')) === $jobCard;
    });

check('contains() answers rather than throwing',
    fn() => $guarded->contains(JobCardId::fromString('JC-2001')) === true
        && $guarded->contains(JobCardId::fromString('JC-0000')) === false);

check('count() returns the inner count',
    fn() => $guarded->count() === 1);

check('forVehicle() returns a list of aggregates',
    function () use ($guarded): bool {
        $results = $guarded->forVehicle(VehicleId::fromString('VEH-4471'));

        return $results !== [] && array_is_list($results) && $results[0] instanceof JobCard;
    });

check('awaitingAuthorisation() returns a list',
    function () use ($guarded): bool {
        $results = $guarded->awaitingAuthorisation();

        return array_is_list($results);
    });

check('remove() then contains() reports false',
    function () use ($guarded): bool {
        $guarded->remove(JobCardId::fromString('JC-2001'));

        return $guarded->contains(JobCardId::fromString('JC-2001')) === false;
    });

check('A real JobCardNotFound from the real repository passes through as itself',
    fn() => throws(JobCardNotFound::class,
        static fn() => $guarded->get(JobCardId::fromString('JC-0000'))));

check('A real duplicate add passes through as JobCardAlreadyExists, not StorageFailure',
    function (): bool {
        $repository = new GuardedJobCards(new InMemoryJobCards());
        $repository->add(aJobCard('JC-3001'));

        return throws(JobCardAlreadyExists::class,
            static fn() => $repository->add(aJobCard('JC-3001')));
    });


// ═══════════════════════════════════════════════════════════════════════════
// 9 — The clause the boundary will write in Lesson 4.4
// ═══════════════════════════════════════════════════════════════════════════

check('catch (WorkshopFailure) catches both domain failures',
    fn() => JobCardNotFound::withId(JobCardId::fromString('JC-1')) instanceof WorkshopFailure
        && JobCardAlreadyExists::withId(JobCardId::fromString('JC-1')) instanceof WorkshopFailure);

check('catch (WorkshopFailure) does NOT catch a StorageFailure',
    fn() => !((new ReflectionClass(StorageFailure::class))->implementsInterface(WorkshopFailure::class)));

check('catch (WorkshopFailure) does NOT catch a bare \PDOException',
    fn() => !(new PDOException('x') instanceof WorkshopFailure));

check('catch (WorkshopFailure) does NOT catch a \TypeError',
    fn() => !(new TypeError('x') instanceof WorkshopFailure));


acceptance_report();
