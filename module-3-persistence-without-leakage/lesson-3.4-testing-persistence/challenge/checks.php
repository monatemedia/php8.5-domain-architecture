<?php
declare(strict_types=1);

/**
 * checks.php — Lesson 3.4 acceptance checks
 * ==========================================
 *
 * THE SPECIFICATION for Infrastructure\SqliteJobCards — and, more importantly,
 * THE CONTRACT TEST for Workshop\JobCards.
 *
 * DO NOT EDIT.
 *
 * HOW THIS FILE IS DIFFERENT FROM EVERY OTHER checks.php
 * -------------------------------------------------------
 * Most of it is written ONCE and run TWICE, against two implementations of the
 * same interface. That is the lesson: a contract test is a suite that belongs
 * to the INTERFACE rather than to any class, and every implementation must
 * satisfy it.
 *
 * Liskov, cashed in as something you can execute.
 *
 * Note what the contract deliberately does NOT assert — Lesson 3.1's check that
 * `get()` returns the same instance you added. That is true of an in-memory
 * repository and cannot be true of a database, so it was never part of the
 * interface's promise. A contract test that asserted it would be testing one
 * implementation's mechanism, not the interface's behaviour.
 */

use Infrastructure\InMemoryJobCards;
use Infrastructure\JobCardMapper;
use Infrastructure\SqliteJobCards;
use Workshop\AccountHolderId;
use Workshop\Currency;
use Workshop\JobCard;
use Workshop\JobCardId;
use Workshop\JobCardNotFound;
use Workshop\JobCards;
use Workshop\LabourLine;
use Workshop\LabourUnits;
use Workshop\Line;
use Workshop\Money;
use Workshop\PartLine;
use Workshop\PartNumber;
use Workshop\VehicleId;

acceptance('Lesson 3.4 — Testing persistence: one contract, two implementations');


// ═══════════════════════════════════════════════════════════════════════════
// Fixtures
// ═══════════════════════════════════════════════════════════════════════════

function aJobCard(string $id = 'JC-1001', string $vehicle = 'VEH-4471'): JobCard
{
    $jobCard = JobCard::open(
        JobCardId::fromString($id),
        VehicleId::fromString($vehicle),
        AccountHolderId::fromString('AH-0007'),
        Currency::EUR
    );

    $jobCard->startWork();
    $jobCard->addLine(LabourLine::create('Rear brake discs', LabourUnits::fromMinutes(90), Money::of(150, Currency::EUR)));
    $jobCard->addLine(PartLine::create(PartNumber::fromString('K9K-1234'), 'Front pads', 2, Money::of(4100, Currency::EUR)));
    $jobCard->releaseEvents();

    return $jobCard;
}

function anAwaitingJobCard(string $id, string $vehicle = 'VEH-4471'): JobCard
{
    $jobCard = aJobCard($id, $vehicle);
    $jobCard->discoverAdditionalWork('Rear discs below minimum thickness');
    $jobCard->releaseEvents();

    return $jobCard;
}

/** @return list<string> */
function lineIdsOf(JobCard $jobCard): array
{
    return array_map(static fn(Line $line): string => $line->id()->value(), $jobCard->lines());
}


// ═══════════════════════════════════════════════════════════════════════════
// SqliteJobCards — placement and shape
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Infrastructure\SqliteJobCards exists, implements JobCards, and is NOT in the domain',
    static function (): bool {
        if (!class_exists(SqliteJobCards::class)) {
            return false;
        }

        $reflection = new ReflectionClass(SqliteJobCards::class);
        $file       = str_replace('\\', '/', (string) $reflection->getFileName());

        return $reflection->getNamespaceName() === 'Infrastructure'
            && !str_contains($file, 'domain/workshop/')
            && in_array(JobCards::class, class_implements(SqliteJobCards::class), true);
    }
);

check(
    'It takes a PDO and a JobCardMapper — it does not build its own',
    static function (): bool {
        $params = (new ReflectionMethod(SqliteJobCards::class, '__construct'))->getParameters();
        $types  = array_map(static fn(ReflectionParameter $p): string => (string) $p->getType(), $params);

        return $types === ['PDO', 'Infrastructure\JobCardMapper'];
    }
);

check(
    'createSchema() builds the tables on a fresh :memory: database',
    static fn(): bool => succeeds(static function (): void {
        $pdo = new PDO('sqlite::memory:');
        SqliteJobCards::createSchema($pdo);

        $pdo->query('SELECT COUNT(*) FROM job_cards')->fetchColumn();
        $pdo->query('SELECT COUNT(*) FROM job_card_lines')->fetchColumn();
    })
);

check(
    'The schema has a position column — a table has no insertion order of its own',
    static function (): bool {
        $pdo = new PDO('sqlite::memory:');
        SqliteJobCards::createSchema($pdo);

        $columns = $pdo->query('PRAGMA table_info(job_card_lines)')->fetchAll(PDO::FETCH_ASSOC);
        $names   = array_column($columns, 'name');

        return in_array('position', $names, true);
    }
);

check(
    'The DOMAIN knows nothing about ordering — no "position" in JobCard',
    static fn(): bool => !str_contains(class_source_without_comments(JobCard::class), 'position')
);

check(
    'SqliteJobCards does the SQL; the mapper does the mapping',
    static function (): bool {
        $sqlite = class_source_without_comments(SqliteJobCards::class);
        $mapper = class_source_without_comments(JobCardMapper::class);

        return str_contains($sqlite, 'SELECT')
            && str_contains($sqlite, 'INSERT')
            && !str_contains($mapper, 'SELECT')
            && !str_contains($mapper, 'PDO');
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// THE CONTRACT — written once, run against both implementations
// ═══════════════════════════════════════════════════════════════════════════

/** @return array<string, callable(): JobCards> */
function implementations(): array
{
    return [
        'InMemory' => static fn(): JobCards => new InMemoryJobCards(),

        'SQLite' => static function (): JobCards {
            // A fresh database per repository: every test gets its own, and it
            // vanishes when the connection closes. No fixtures to clean up and
            // no ordering dependencies between tests.
            $pdo = new PDO('sqlite::memory:');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            SqliteJobCards::createSchema($pdo);

            return new SqliteJobCards($pdo, new JobCardMapper());
        },
    ];
}

/**
 * The contract itself. Every assertion here is about what `JobCards` PROMISES,
 * and nothing about how either class keeps the promise.
 *
 * @param callable(): JobCards $make
 * @return list<array{0: string, 1: callable(): bool}>
 */
function contractFor(callable $make): array
{
    return [
        ['a new repository is empty', static function () use ($make): bool {
            $repository = $make();

            return $repository->count() === 0
                && $repository->awaitingAuthorisation() === []
                && $repository->forVehicle(VehicleId::fromString('VEH-1')) === [];
        }],

        ['add() then contains()', static function () use ($make): bool {
            $repository = $make();
            $repository->add(aJobCard());

            return $repository->contains(JobCardId::fromString('JC-1001'))
                && !$repository->contains(JobCardId::fromString('JC-9999'))
                && $repository->count() === 1;
        }],

        ['get() returns an EQUAL aggregate — identity, status, total', static function () use ($make): bool {
            $repository = $make();
            $original   = aJobCard();
            $repository->add($original);

            $loaded = $repository->get(JobCardId::fromString('JC-1001'));

            return $loaded->id()->equals($original->id())
                && $loaded->vehicleId()->equals($original->vehicleId())
                && $loaded->status() === $original->status()
                && $loaded->total()->equals($original->total())
                && $loaded->total()->amountMinor() === 10450;
        }],

        ['ORDERING: lines come back in insertion order, with their identities', static function () use ($make): bool {
            $repository = $make();
            $original   = aJobCard();
            $repository->add($original);

            $loaded = $repository->get(JobCardId::fromString('JC-1001'));

            return lineIdsOf($loaded) === lineIdsOf($original) && count(lineIdsOf($loaded)) === 2;
        }],

        ['The line CLASSES survive', static function () use ($make): bool {
            $repository = $make();
            $repository->add(aJobCard());

            $lines = $repository->get(JobCardId::fromString('JC-1001'))->lines();

            return $lines[0] instanceof LabourLine && $lines[1] instanceof PartLine;
        }],

        ['get() on a missing id throws JobCardNotFound', static fn(): bool => throws(
            JobCardNotFound::class,
            static fn() => $make()->get(JobCardId::fromString('JC-9999'))
        )],

        ['add() twice with the same identity is refused', static fn(): bool => throws(
            DomainException::class,
            static function () use ($make): void {
                $repository = $make();
                $repository->add(aJobCard());
                $repository->add(aJobCard());
            }
        )],

        ['A refused add() leaves the collection as it was', static function () use ($make): bool {
            $repository = $make();
            $repository->add(aJobCard());

            try {
                $repository->add(aJobCard());
            } catch (DomainException) {
                // expected
            }

            return $repository->count() === 1;
        }],

        ['remove() removes; a second remove() throws', static function () use ($make): bool {
            $repository = $make();
            $repository->add(aJobCard());
            $repository->remove(JobCardId::fromString('JC-1001'));

            return $repository->count() === 0
                && !$repository->contains(JobCardId::fromString('JC-1001'))
                && throws(
                    JobCardNotFound::class,
                    static fn() => $repository->remove(JobCardId::fromString('JC-1001'))
                );
        }],

        ['remove() takes the lines with it — an id can be reused afterwards', static function () use ($make): bool {
            $repository = $make();
            $repository->add(aJobCard());
            $repository->remove(JobCardId::fromString('JC-1001'));
            $repository->add(aJobCard());

            return $repository->get(JobCardId::fromString('JC-1001'))->lineCount() === 2;
        }],

        ['forVehicle() filters, and returns a list', static function () use ($make): bool {
            $repository = $make();
            $repository->add(aJobCard('JC-1', 'VEH-A'));
            $repository->add(aJobCard('JC-2', 'VEH-B'));
            $repository->add(aJobCard('JC-3', 'VEH-A'));

            $found = $repository->forVehicle(VehicleId::fromString('VEH-A'));

            if (array_keys($found) !== [0, 1]) {
                return false;
            }

            foreach ($found as $jobCard) {
                if ($jobCard->vehicleId()->value() !== 'VEH-A') {
                    return false;
                }
            }

            return true;
        }],

        ['forVehicle() returns an empty list for a vehicle with no history', static function () use ($make): bool {
            $repository = $make();
            $repository->add(aJobCard('JC-1', 'VEH-A'));

            return $repository->forVehicle(VehicleId::fromString('VEH-Z')) === [];
        }],

        ['awaitingAuthorisation() finds only those waiting', static function () use ($make): bool {
            $repository = $make();
            $repository->add(aJobCard('JC-1'));
            $repository->add(anAwaitingJobCard('JC-2'));
            $repository->add(anAwaitingJobCard('JC-3'));

            $waiting = $repository->awaitingAuthorisation();

            if (count($waiting) !== 2 || array_keys($waiting) !== [0, 1]) {
                return false;
            }

            foreach ($waiting as $jobCard) {
                if ($jobCard->pendingAuthorisations() === []) {
                    return false;
                }
            }

            return true;
        }],

        ['count() tracks adds and removes', static function () use ($make): bool {
            $repository = $make();
            $repository->add(aJobCard('JC-1'));
            $repository->add(aJobCard('JC-2'));
            $repository->remove(JobCardId::fromString('JC-1'));

            return $repository->count() === 1;
        }],

        ['A loaded job card is fully usable — it can be completed and invoiced', static function () use ($make): bool {
            $repository = $make();
            $repository->add(aJobCard());

            $loaded = $repository->get(JobCardId::fromString('JC-1001'));
            $loaded->complete();
            $loaded->invoice();

            return $loaded->status()->value === 'invoiced'
                && count($loaded->releaseEvents()) === 2;
        }],

        ['Loading records no events', static function () use ($make): bool {
            $repository = $make();
            $repository->add(aJobCard());

            return $repository->get(JobCardId::fromString('JC-1001'))->releaseEvents() === [];
        }],
    ];
}

foreach (implementations() as $name => $make) {
    foreach (contractFor($make) as [$label, $assertion]) {
        check(sprintf('[%-8s] %s', $name, $label), $assertion);
    }
}


// ═══════════════════════════════════════════════════════════════════════════
// The contract is the same contract
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Both implementations were run against an IDENTICAL assertion list',
    static function (): bool {
        $implementations = implementations();

        if (count($implementations) !== 2) {
            return false;
        }

        $labels = [];
        foreach ($implementations as $make) {
            $labels[] = array_map(
                static fn(array $case): string => $case[0],
                contractFor($make)
            );
        }

        // If the two label lists ever differ, somebody has special-cased one
        // implementation — which is the moment a contract test stops being one.
        return $labels[0] === $labels[1] && count($labels[0]) >= 15;
    }
);


acceptance_report();
