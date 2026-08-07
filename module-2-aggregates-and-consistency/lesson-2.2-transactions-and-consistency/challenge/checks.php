<?php
declare(strict_types=1);

/**
 * checks.php — Lesson 2.2 acceptance checks
 * ==========================================
 *
 * THE SPECIFICATION for CourtesyVehicleId, CourtesyFleet, and the one addition
 * to CourtesyVehicle.
 *
 * DO NOT EDIT.
 *
 * Depends on JobCardId from Lesson 2.1 and CourtesyVehicle from Lesson 1.1.
 */

use Workshop\CourtesyFleet;
use Workshop\CourtesyVehicle;
use Workshop\CourtesyVehicleId;
use Workshop\JobCardId;

acceptance('Lesson 2.2 — Transactions and consistency: the CourtesyFleet aggregate');


// ═══════════════════════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════════════════════

/** @return array{0: CourtesyFleet, 1: list<CourtesyVehicleId>} */
function aFleetOfThree(): array
{
    $fleet = CourtesyFleet::create();

    $ids = [];
    foreach (['CV-01', 'CV-02', 'CV-03'] as $code) {
        $ids[] = $fleet->addVehicle($code);
    }

    return [$fleet, $ids];
}

function jc(string $reference): JobCardId
{
    return JobCardId::fromString($reference);
}


// ═══════════════════════════════════════════════════════════════════════════
// Carried forward
// ═══════════════════════════════════════════════════════════════════════════

check(
    'JobCardId (2.1) and CourtesyVehicle (1.1) are available',
    static fn(): bool => class_exists(JobCardId::class) && class_exists(CourtesyVehicle::class)
);


// ═══════════════════════════════════════════════════════════════════════════
// CourtesyVehicleId
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\CourtesyVehicleId exists, in domain/workshop/CourtesyVehicleId.php',
    static fn(): bool => class_exists(CourtesyVehicleId::class)
        && declared_in(CourtesyVehicleId::class, 'domain/workshop/CourtesyVehicleId.php')
);

check(
    'CourtesyVehicleId is a final readonly class with no public constructor',
    static function (): bool {
        $r = new ReflectionClass(CourtesyVehicleId::class);
        $c = $r->getConstructor();
        return $r->isFinal() && $r->isReadOnly() && ($c === null || !$c->isPublic());
    }
);

check(
    'CourtesyVehicleId generates distinct identities and round-trips',
    static fn(): bool => !CourtesyVehicleId::generate()->equals(CourtesyVehicleId::generate())
        && CourtesyVehicleId::fromString('CVEH-1')->value() === 'CVEH-1'
        && throws(InvalidArgumentException::class, static fn() => CourtesyVehicleId::fromString('  '))
);


// ═══════════════════════════════════════════════════════════════════════════
// CourtesyVehicle — the addition, with nothing removed
// ═══════════════════════════════════════════════════════════════════════════

check(
    'CourtesyVehicle now has an id, minted internally',
    static fn(): bool => (new CourtesyVehicle('CV-01'))->id() instanceof CourtesyVehicleId
);

check(
    'Two courtesy vehicles with the same fleet code have DIFFERENT ids',
    static fn(): bool => !(new CourtesyVehicle('CV-01'))->id()->equals((new CourtesyVehicle('CV-01'))->id())
);

check(
    'CourtesyVehicle::__construct() keeps its Lesson 1.1 signature exactly',
    static function (): bool {
        $params = (new ReflectionMethod(CourtesyVehicle::class, '__construct'))->getParameters();
        return count($params) === 1 && (string) $params[0]->getType() === 'string';
    }
);

check(
    'Everything Lesson 1.1 asserted about CourtesyVehicle still holds',
    static function (): bool {
        $vehicle = new CourtesyVehicle('CV-01');

        if ($vehicle->fleetCode() !== 'CV-01' || !$vehicle->isAvailable() || $vehicle->allocatedToJobCard() !== null) {
            return false;
        }

        $vehicle->allocateTo('JC-1001');
        if ($vehicle->isAvailable() || $vehicle->allocatedToJobCard() !== 'JC-1001') {
            return false;
        }

        if (!throws(DomainException::class, static fn() => $vehicle->allocateTo('JC-4471'))) {
            return false;
        }

        $vehicle->returnToFleet();

        return $vehicle->isAvailable()
            && throws(DomainException::class, static fn() => $vehicle->returnToFleet())
            && throws(InvalidArgumentException::class, static fn() => new CourtesyVehicle('  '));
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// CourtesyFleet — shape and membership
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\CourtesyFleet exists, in domain/workshop/CourtesyFleet.php',
    static fn(): bool => class_exists(CourtesyFleet::class)
        && declared_in(CourtesyFleet::class, 'domain/workshop/CourtesyFleet.php')
);

check(
    'CourtesyFleet is final and NOT readonly',
    static function (): bool {
        $r = new ReflectionClass(CourtesyFleet::class);
        return $r->isFinal() && $r->isReadOnly() === false;
    }
);

check(
    'CourtesyFleet::create() gives an empty fleet',
    static fn(): bool => CourtesyFleet::create()->size() === 0
        && CourtesyFleet::create()->availableVehicles() === []
);

check(
    'addVehicle() returns an identity and grows the fleet',
    static function (): bool {
        $fleet = CourtesyFleet::create();
        $id    = $fleet->addVehicle('CV-01');

        return $id instanceof CourtesyVehicleId
            && $fleet->size() === 1
            && $fleet->has($id)
            && $fleet->fleetCodeOf($id) === 'CV-01';
    }
);

check(
    'BOUNDARY: addVehicle() takes a fleet code, NOT a CourtesyVehicle',
    static function (): bool {
        $params = (new ReflectionMethod(CourtesyFleet::class, 'addVehicle'))->getParameters();
        return count($params) === 1 && (string) $params[0]->getType() === 'string';
    }
);

check(
    'BOUNDARY: no public method of CourtesyFleet returns a CourtesyVehicle',
    static function (): bool {
        foreach ((new ReflectionClass(CourtesyFleet::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = (string) $method->getReturnType();
            if (str_contains($type, 'CourtesyVehicle') && !str_contains($type, 'CourtesyVehicleId')) {
                return false;
            }
        }
        return true;
    }
);

check(
    'An empty fleet code is refused with InvalidArgumentException',
    static fn(): bool => throws(
        InvalidArgumentException::class,
        static fn() => CourtesyFleet::create()->addVehicle('   ')
    )
);

check(
    'A duplicate fleet code is refused with DomainException',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            $fleet = CourtesyFleet::create();
            $fleet->addVehicle('CV-01');
            $fleet->addVehicle('CV-01');
        }
    )
);

check(
    'A refused addVehicle() does not grow the fleet',
    static function (): bool {
        $fleet = CourtesyFleet::create();
        $fleet->addVehicle('CV-01');
        try {
            $fleet->addVehicle('CV-01');
        } catch (DomainException) {
            // expected
        }
        return $fleet->size() === 1;
    }
);

check(
    'has() and fleetCodeOf() reject a vehicle from outside the fleet',
    static function (): bool {
        [$fleet] = aFleetOfThree();
        $stranger = CourtesyVehicleId::generate();

        return $fleet->has($stranger) === false
            && throws(DomainException::class, static fn() => $fleet->fleetCodeOf($stranger));
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// CourtesyFleet — allocation, and INVARIANT I10
// ═══════════════════════════════════════════════════════════════════════════

check(
    'allocate() lends a vehicle out',
    static function (): bool {
        [$fleet, $ids] = aFleetOfThree();
        $fleet->allocate($ids[0], jc('JC-1001'));

        return $fleet->isAvailable($ids[0]) === false
            && $fleet->allocationFor(jc('JC-1001'))?->equals($ids[0]) === true
            && $fleet->jobCardFor($ids[0])?->value() === 'JC-1001';
    }
);

check(
    'availableVehicles() excludes what is out, and returns identities',
    static function (): bool {
        [$fleet, $ids] = aFleetOfThree();
        $fleet->allocate($ids[0], jc('JC-1001'));

        $available = $fleet->availableVehicles();

        if (array_keys($available) !== [0, 1]) {
            return false;
        }
        foreach ($available as $id) {
            if (!$id instanceof CourtesyVehicleId) {
                return false;
            }
            if ($id->equals($ids[0])) {
                return false;
            }
        }
        return true;
    }
);

check(
    'INVARIANT I10: a vehicle already out cannot be allocated again',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            [$fleet, $ids] = aFleetOfThree();
            $fleet->allocate($ids[0], jc('JC-1001'));
            $fleet->allocate($ids[0], jc('JC-4471'));
        }
    )
);

check(
    'INVARIANT I10 holds across the fleet, not just one object',
    static function (): bool {
        // The example-02 failure, retried through the aggregate: two separate
        // allocation attempts on the same vehicle for two different job cards.
        // There is only one instance of the vehicle, so the second is refused.
        [$fleet, $ids] = aFleetOfThree();
        $fleet->allocate($ids[0], jc('JC-1001'));

        try {
            $fleet->allocate($ids[0], jc('JC-4471'));
        } catch (DomainException) {
            // expected
        }

        return $fleet->jobCardFor($ids[0])?->value() === 'JC-1001'
            && $fleet->allocationFor(jc('JC-4471')) === null;
    }
);

check(
    'THE MIRROR RULE: a job card cannot have two courtesy vehicles',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            [$fleet, $ids] = aFleetOfThree();
            $fleet->allocate($ids[0], jc('JC-1001'));
            $fleet->allocate($ids[1], jc('JC-1001'));
        }
    )
);

check(
    'A refused allocation leaves both vehicles as they were',
    static function (): bool {
        [$fleet, $ids] = aFleetOfThree();
        $fleet->allocate($ids[0], jc('JC-1001'));

        try {
            $fleet->allocate($ids[1], jc('JC-1001'));
        } catch (DomainException) {
            // expected
        }

        return $fleet->isAvailable($ids[1]) === true
            && $fleet->jobCardFor($ids[0])?->value() === 'JC-1001';
    }
);

check(
    'Two different vehicles on two different job cards is fine',
    static function (): bool {
        [$fleet, $ids] = aFleetOfThree();
        $fleet->allocate($ids[0], jc('JC-1001'));
        $fleet->allocate($ids[1], jc('JC-4471'));

        $available = $fleet->availableVehicles();

        return count($available) === 1
            && $available[0]->equals($ids[2])
            && $fleet->jobCardFor($ids[0])?->value() === 'JC-1001'
            && $fleet->jobCardFor($ids[1])?->value() === 'JC-4471';
    }
);

check(
    'Allocating a vehicle that is not in the fleet is refused',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            [$fleet] = aFleetOfThree();
            $fleet->allocate(CourtesyVehicleId::generate(), jc('JC-1001'));
        }
    )
);


// ═══════════════════════════════════════════════════════════════════════════
// CourtesyFleet — return
// ═══════════════════════════════════════════════════════════════════════════

check(
    'returnFromJobCard() takes the vehicle back',
    static function (): bool {
        [$fleet, $ids] = aFleetOfThree();
        $fleet->allocate($ids[0], jc('JC-1001'));
        $fleet->returnFromJobCard(jc('JC-1001'));

        return $fleet->isAvailable($ids[0]) === true
            && $fleet->allocationFor(jc('JC-1001')) === null
            && $fleet->jobCardFor($ids[0]) === null;
    }
);

check(
    'A returned vehicle can be allocated again',
    static function (): bool {
        [$fleet, $ids] = aFleetOfThree();
        $fleet->allocate($ids[0], jc('JC-1001'));
        $fleet->returnFromJobCard(jc('JC-1001'));
        $fleet->allocate($ids[0], jc('JC-4471'));

        return $fleet->jobCardFor($ids[0])?->value() === 'JC-4471';
    }
);

check(
    'Returning for a job card that has nothing out is refused',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            [$fleet] = aFleetOfThree();
            $fleet->returnFromJobCard(jc('JC-9999'));
        }
    )
);

check(
    'allocationFor() and jobCardFor() return null rather than throwing when nothing is out',
    static function (): bool {
        [$fleet, $ids] = aFleetOfThree();
        return $fleet->allocationFor(jc('JC-9999')) === null
            && $fleet->jobCardFor($ids[0]) === null;
    }
);

check(
    'jobCardFor() refuses a vehicle that is not in the fleet',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            [$fleet] = aFleetOfThree();
            $fleet->jobCardFor(CourtesyVehicleId::generate());
        }
    )
);


// ═══════════════════════════════════════════════════════════════════════════
// No way past the rules
// ═══════════════════════════════════════════════════════════════════════════

check(
    'No public property of CourtesyFleet can be assigned from outside',
    static function (): bool {
        $reflection = new ReflectionClass(CourtesyFleet::class);

        foreach ($reflection->getProperties() as $property) {
            if (!$property->isPublic() || $property->isStatic()) {
                continue;
            }

            [$fleet] = aFleetOfThree();
            $name = $property->getName();

            if (!$property->isInitialized($fleet)) {
                continue;
            }

            try {
                $fleet->$name = $property->getValue($fleet);
            } catch (Throwable) {
                continue;
            }

            return false;
        }

        return true;
    }
);

check(
    'No new class has a public method whose name begins with "set"',
    static function (): bool {
        foreach ([CourtesyFleet::class, CourtesyVehicleId::class, CourtesyVehicle::class] as $class) {
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (str_starts_with(strtolower($method->getName()), 'set')) {
                    return false;
                }
            }
        }
        return true;
    }
);

check(
    'RULE 1: none of the files imports anything outside the Workshop namespace',
    static function (): bool {
        foreach ([CourtesyFleet::class, CourtesyVehicleId::class, CourtesyVehicle::class] as $class) {
            $file = (new ReflectionClass($class))->getFileName();
            if ($file === false) {
                return false;
            }

            $source = (string) file_get_contents($file);
            preg_match_all(
                '/^use\s+(?:function\s+|const\s+)?([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)/m',
                $source,
                $matches
            );

            foreach ($matches[1] as $imported) {
                $imported = ltrim($imported, '\\');
                if (!str_contains($imported, '\\')) {
                    continue;
                }
                if (str_starts_with($imported, 'Workshop\\')) {
                    continue;
                }
                return false;
            }
        }

        return true;
    }
);


acceptance_report();
