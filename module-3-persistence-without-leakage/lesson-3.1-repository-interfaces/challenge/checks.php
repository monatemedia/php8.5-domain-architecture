<?php
declare(strict_types=1);

/**
 * checks.php — Lesson 3.1 acceptance checks
 * ==========================================
 *
 * THE SPECIFICATION for Workshop\JobCards, Workshop\JobCardNotFound and
 * Infrastructure\InMemoryJobCards.
 *
 * DO NOT EDIT.
 *
 * The checks in the "WHICH SIDE OF THE LINE" section are the point of this
 * lesson. Everything else is a repository behaving like a collection.
 */

use Infrastructure\InMemoryJobCards;
use Workshop\AccountHolderId;
use Workshop\Currency;
use Workshop\JobCard;
use Workshop\JobCardId;
use Workshop\JobCardNotFound;
use Workshop\JobCards;
use Workshop\VehicleId;

acceptance('Lesson 3.1 — Repository interfaces belong to the domain');


// ═══════════════════════════════════════════════════════════════════════════
// Helpers
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

/** In Progress, with one item of additional work outstanding. */
function aJobCardAwaitingAuthorisation(string $id, string $vehicle = 'VEH-4471'): JobCard
{
    $jobCard = aJobCard($id, $vehicle);
    $jobCard->startWork();
    $jobCard->discoverAdditionalWork('Rear discs below minimum thickness');

    return $jobCard;
}

/** @return string forward-slashed path of the file a class is declared in */
function fileOf(string $class): string
{
    $file = (new ReflectionClass($class))->getFileName();

    return $file === false ? '' : str_replace('\\', '/', $file);
}


// ═══════════════════════════════════════════════════════════════════════════
// The interface
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\JobCards is an interface, in domain/workshop/JobCards.php',
    static fn(): bool => interface_exists(JobCards::class)
        && declared_in(JobCards::class, 'domain/workshop/JobCards.php')
);

check(
    'It declares exactly the seven methods',
    static function (): bool {
        $methods = array_map(
            static fn(ReflectionMethod $m): string => $m->getName(),
            (new ReflectionClass(JobCards::class))->getMethods()
        );
        sort($methods);

        return $methods === [
            'add',
            'awaitingAuthorisation',
            'contains',
            'count',
            'forVehicle',
            'get',
            'remove',
        ];
    }
);

check(
    'It deals in AGGREGATES: get() returns a JobCard, add() takes one',
    static function (): bool {
        $get = new ReflectionMethod(JobCards::class, 'get');
        $add = new ReflectionMethod(JobCards::class, 'add');

        return (string) $get->getReturnType() === 'Workshop\JobCard'
            && (string) $add->getParameters()[0]->getType() === 'Workshop\JobCard';
    }
);

check(
    'It takes IDENTITIES, never strings: get(), contains(), remove(), forVehicle()',
    static function (): bool {
        $expected = [
            'get'        => 'Workshop\JobCardId',
            'contains'   => 'Workshop\JobCardId',
            'remove'     => 'Workshop\JobCardId',
            'forVehicle' => 'Workshop\VehicleId',
        ];

        foreach ($expected as $method => $type) {
            $params = (new ReflectionMethod(JobCards::class, $method))->getParameters();

            if (count($params) !== 1 || (string) $params[0]->getType() !== $type) {
                return false;
            }
        }

        return true;
    }
);

check(
    'NO STORAGE VOCABULARY: no findBy, query, connection, sql or criteria',
    static function (): bool {
        $banned = ['findby', 'query', 'connection', 'sql', 'criteria', 'hydrate', 'persist', 'flush'];

        foreach ((new ReflectionClass(JobCards::class))->getMethods() as $method) {
            $name = strtolower($method->getName());

            foreach ($banned as $word) {
                if (str_contains($name, $word)) {
                    return false;
                }
            }
        }

        return true;
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// The domain exception
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\JobCardNotFound exists, in the domain, extending \DomainException',
    static fn(): bool => class_exists(JobCardNotFound::class)
        && declared_in(JobCardNotFound::class, 'domain/workshop/JobCardNotFound.php')
        && is_subclass_of(JobCardNotFound::class, DomainException::class)
);

check(
    'It is NOT a RuntimeException — a missing job card is not a crash',
    static fn(): bool => !is_subclass_of(JobCardNotFound::class, RuntimeException::class)
);

check(
    'withId() carries the identity, not only a sentence',
    static function (): bool {
        $exception = JobCardNotFound::withId(JobCardId::fromString('JC-9999'));

        return $exception->jobCardId?->value() === 'JC-9999'
            && str_contains($exception->getMessage(), 'JC-9999');
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// WHICH SIDE OF THE LINE — the point of the lesson
// ═══════════════════════════════════════════════════════════════════════════

check(
    'RULE 1: the interface imports NOTHING — every type it names is its own',
    static function (): bool {
        $source = (string) file_get_contents(fileOf(JobCards::class));

        return preg_match('/^use\s+/m', $source) !== 1;
    }
);

check(
    'RULE 1: no domain file REFERENCES the Infrastructure namespace in code',
    static function (): bool {
        // Comments stripped first, and the distinction matters. The doc block
        // on JobCards explains the inversion — "Infrastructure supplies it" —
        // which is describing the dependency rule, not breaking it. A check
        // that greps the raw file punishes a class for being documented.
        //
        // The first version of this check did exactly that and failed its own
        // reference solution. See class_source_without_comments() in
        // lib/acceptance.php.
        foreach ([JobCards::class, JobCardNotFound::class] as $class) {
            if (str_contains(class_source_without_comments($class), 'Infrastructure')) {
                return false;
            }
        }

        return true;
    }
);

check(
    'The IMPLEMENTATION is NOT in domain/workshop/',
    static function (): bool {
        $file = fileOf(InMemoryJobCards::class);

        return $file !== '' && !str_contains($file, 'domain/workshop/');
    }
);

check(
    'The implementation lives in an infrastructure directory',
    static function (): bool {
        return str_contains(fileOf(InMemoryJobCards::class), 'infrastructure/');
    }
);

check(
    'Its namespace is Infrastructure, not Workshop',
    static fn(): bool => (new ReflectionClass(InMemoryJobCards::class))->getNamespaceName() === 'Infrastructure'
);

check(
    'It implements the domain interface',
    static fn(): bool => in_array(JobCards::class, class_implements(InMemoryJobCards::class), true)
);

check(
    'The ARROW POINTS INWARD: the implementation imports from Workshop',
    static function (): bool {
        $source = (string) file_get_contents(fileOf(InMemoryJobCards::class));

        preg_match_all('/^use\s+([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)/m', $source, $matches);

        $intoDomain = array_filter(
            $matches[1],
            static fn(string $import): bool => str_starts_with(ltrim($import, '\\'), 'Workshop\\')
        );

        return count($intoDomain) >= 3;
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// It behaves like a collection
// ═══════════════════════════════════════════════════════════════════════════

check(
    'A new repository is empty',
    static function (): bool {
        $jobCards = new InMemoryJobCards();

        return $jobCards->count() === 0
            && $jobCards->awaitingAuthorisation() === []
            && $jobCards->forVehicle(VehicleId::fromString('VEH-1')) === [];
    }
);

check(
    'add() then get() returns the SAME aggregate, not a copy',
    static function (): bool {
        $jobCards = new InMemoryJobCards();
        $jobCard  = aJobCard();
        $jobCards->add($jobCard);

        return $jobCards->get(JobCardId::fromString('JC-1001')) === $jobCard
            && $jobCards->count() === 1;
    }
);

check(
    'contains() answers without throwing',
    static function (): bool {
        $jobCards = new InMemoryJobCards();
        $jobCards->add(aJobCard());

        return $jobCards->contains(JobCardId::fromString('JC-1001')) === true
            && $jobCards->contains(JobCardId::fromString('JC-9999')) === false;
    }
);

check(
    'get() on a missing id throws JobCardNotFound',
    static fn(): bool => throws(
        JobCardNotFound::class,
        static fn() => (new InMemoryJobCards())->get(JobCardId::fromString('JC-9999'))
    )
);

check(
    'add() twice with the same identity is refused — a collection does not silently replace',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            $jobCards = new InMemoryJobCards();
            $jobCards->add(aJobCard());
            $jobCards->add(aJobCard());
        }
    )
);

check(
    'remove() removes, and remove() on a missing id throws JobCardNotFound',
    static function (): bool {
        $jobCards = new InMemoryJobCards();
        $jobCards->add(aJobCard());
        $jobCards->remove(JobCardId::fromString('JC-1001'));

        return $jobCards->count() === 0
            && throws(
                JobCardNotFound::class,
                static fn() => $jobCards->remove(JobCardId::fromString('JC-1001'))
            );
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// The queries, in the workshop's words
// ═══════════════════════════════════════════════════════════════════════════

check(
    'forVehicle() returns only that vehicle\'s job cards, as a list',
    static function (): bool {
        $jobCards = new InMemoryJobCards();
        $jobCards->add(aJobCard('JC-1', 'VEH-A'));
        $jobCards->add(aJobCard('JC-2', 'VEH-B'));
        $jobCards->add(aJobCard('JC-3', 'VEH-A'));

        $found = $jobCards->forVehicle(VehicleId::fromString('VEH-A'));

        if (array_keys($found) !== [0, 1]) {
            return false;
        }

        foreach ($found as $jobCard) {
            if ($jobCard->vehicleId()->value() !== 'VEH-A') {
                return false;
            }
        }

        return true;
    }
);

check(
    'forVehicle() returns an empty list for a vehicle with no history',
    static function (): bool {
        $jobCards = new InMemoryJobCards();
        $jobCards->add(aJobCard('JC-1', 'VEH-A'));

        return $jobCards->forVehicle(VehicleId::fromString('VEH-Z')) === [];
    }
);

check(
    'awaitingAuthorisation() returns only those waiting on an account holder',
    static function (): bool {
        $jobCards = new InMemoryJobCards();
        $jobCards->add(aJobCard('JC-1'));                        // Booked
        $jobCards->add(aJobCardAwaitingAuthorisation('JC-2'));
        $jobCards->add(aJobCardAwaitingAuthorisation('JC-3'));

        $waiting = $jobCards->awaitingAuthorisation();

        return array_keys($waiting) === [0, 1] && count($waiting) === 2;
    }
);

check(
    'A job card that is authorised drops out of awaitingAuthorisation()',
    static function (): bool {
        $jobCards = new InMemoryJobCards();
        $jobCard  = aJobCardAwaitingAuthorisation('JC-2');
        $jobCards->add($jobCard);

        if (count($jobCards->awaitingAuthorisation()) !== 1) {
            return false;
        }

        // The repository holds a REFERENCE, so this is visible without saving
        // anything. A SQLite implementation could not do that, and Lesson 3.2
        // is about what happens next.
        $jobCard->authoriseAdditionalWork();

        return $jobCards->awaitingAuthorisation() === [];
    }
);

check(
    'count() tracks adds and removes',
    static function (): bool {
        $jobCards = new InMemoryJobCards();
        $jobCards->add(aJobCard('JC-1'));
        $jobCards->add(aJobCard('JC-2'));
        $jobCards->remove(JobCardId::fromString('JC-1'));

        return $jobCards->count() === 1;
    }
);


acceptance_report();
