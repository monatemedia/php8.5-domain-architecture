<?php
declare(strict_types=1);

/**
 * checks.php — Lesson 1.3 acceptance checks
 * ==========================================
 *
 * THE SPECIFICATION for Workshop\VehicleId, Workshop\OdometerReading and
 * Workshop\Vehicle.
 *
 * DO NOT EDIT.
 *
 * This is the first checks file that depends on earlier work:
 * Workshop\RegistrationNumber, from Lesson 1.2. If that is missing, most of the
 * Vehicle checks below will fail with "Class not found" — fix 1.2 first.
 */

use Workshop\OdometerReading;
use Workshop\RegistrationNumber;
use Workshop\Vehicle;
use Workshop\VehicleId;

acceptance('Lesson 1.3 — Entities and identity: VehicleId, OdometerReading, Vehicle');


// ═══════════════════════════════════════════════════════════════════════════
// Carried forward from Lesson 1.2
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\RegistrationNumber is available (built in Lesson 1.2)',
    static fn(): bool => class_exists(RegistrationNumber::class)
);


// ═══════════════════════════════════════════════════════════════════════════
// VehicleId
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\VehicleId exists',
    static fn(): bool => class_exists(VehicleId::class)
);

check(
    'It is declared in domain/workshop/VehicleId.php',
    static fn(): bool => declared_in(VehicleId::class, 'domain/workshop/VehicleId.php')
);

check(
    'VehicleId is a final readonly class with no public constructor',
    static function (): bool {
        $reflection  = new ReflectionClass(VehicleId::class);
        $constructor = $reflection->getConstructor();
        return $reflection->isFinal()
            && $reflection->isReadOnly()
            && ($constructor === null || !$constructor->isPublic());
    }
);

check(
    'VehicleId::generate() produces a non-empty identity with no arguments',
    static fn(): bool => VehicleId::generate()->value() !== ''
);

check(
    'VehicleId::generate() produces 500 distinct identities',
    static function (): bool {
        $seen = [];
        for ($i = 0; $i < 500; $i++) {
            $seen[VehicleId::generate()->value()] = true;
        }
        return count($seen) === 500;
    }
);

check(
    'VehicleId::fromString() round-trips',
    static fn(): bool => VehicleId::fromString('VEH-4471')->value() === 'VEH-4471'
);

check(
    'VehicleId::fromString() refuses an empty value with InvalidArgumentException',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => VehicleId::fromString(''))
);

check(
    'VehicleId::fromString() refuses a whitespace-only value',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => VehicleId::fromString('   '))
);

check(
    'VehicleId::equals() compares by value, not by instance',
    static fn(): bool => VehicleId::fromString('VEH-4471')->equals(VehicleId::fromString('VEH-4471')) === true
        && VehicleId::fromString('VEH-4471')->equals(VehicleId::fromString('VEH-9999')) === false
);

check(
    'VehicleId casts to its value',
    static fn(): bool => (string) VehicleId::fromString('VEH-4471') === 'VEH-4471'
);


// ═══════════════════════════════════════════════════════════════════════════
// OdometerReading
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\OdometerReading exists',
    static fn(): bool => class_exists(OdometerReading::class)
);

check(
    'It is declared in domain/workshop/OdometerReading.php',
    static fn(): bool => declared_in(OdometerReading::class, 'domain/workshop/OdometerReading.php')
);

check(
    'OdometerReading is a final readonly class with no public constructor',
    static function (): bool {
        $reflection  = new ReflectionClass(OdometerReading::class);
        $constructor = $reflection->getConstructor();
        return $reflection->isFinal()
            && $reflection->isReadOnly()
            && ($constructor === null || !$constructor->isPublic());
    }
);

check(
    'OdometerReading::of() keeps its value',
    static fn(): bool => OdometerReading::of(84500)->kilometres() === 84500
);

check(
    'Zero is a valid reading — a new vehicle has done nothing',
    static fn(): bool => OdometerReading::of(0)->kilometres() === 0
);

check(
    'A negative reading is refused with InvalidArgumentException',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => OdometerReading::of(-1))
);

check(
    'A reading above 2,000,000 is refused with InvalidArgumentException',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => OdometerReading::of(2000001))
);

check(
    'Exactly 2,000,000 is accepted — the bound is inclusive',
    static fn(): bool => succeeds(static fn() => OdometerReading::of(2000000))
);

check(
    'isLessThan() compares correctly in both directions',
    static fn(): bool => OdometerReading::of(84499)->isLessThan(OdometerReading::of(84500)) === true
        && OdometerReading::of(84500)->isLessThan(OdometerReading::of(84499)) === false
        && OdometerReading::of(84500)->isLessThan(OdometerReading::of(84500)) === false
);

check(
    'equals() is by value',
    static fn(): bool => OdometerReading::of(84500)->equals(OdometerReading::of(84500)) === true
        && OdometerReading::of(84500)->equals(OdometerReading::of(84501)) === false
);

check(
    '__toString() formats with separators and a unit: "84,500 km"',
    static fn(): bool => (string) OdometerReading::of(84500) === '84,500 km'
);


// ═══════════════════════════════════════════════════════════════════════════
// Vehicle — shape
// ═══════════════════════════════════════════════════════════════════════════

/** @return callable(): Vehicle */
function aVehicle(string $id = 'VEH-4471', string $registration = 'AB12CDE', int $km = 84500): callable
{
    return static fn(): Vehicle => new Vehicle(
        VehicleId::fromString($id),
        RegistrationNumber::fromString($registration),
        OdometerReading::of($km)
    );
}

check(
    'Workshop\Vehicle exists',
    static fn(): bool => class_exists(Vehicle::class)
);

check(
    'It is declared in domain/workshop/Vehicle.php',
    static fn(): bool => declared_in(Vehicle::class, 'domain/workshop/Vehicle.php')
);

check(
    'Vehicle is final',
    static fn(): bool => (new ReflectionClass(Vehicle::class))->isFinal()
);

check(
    'Vehicle is NOT readonly — an entity has a lifetime',
    static fn(): bool => (new ReflectionClass(Vehicle::class))->isReadOnly() === false
);

check(
    'Vehicle has a public constructor',
    static function (): bool {
        $constructor = (new ReflectionClass(Vehicle::class))->getConstructor();
        return $constructor !== null && $constructor->isPublic();
    }
);

check(
    'Vehicle can be constructed from the three value objects',
    static fn(): bool => succeeds(aVehicle())
);

check(
    'Vehicle refuses a raw string registration — TypeError before it runs',
    static fn(): bool => throws(
        TypeError::class,
        static fn() => new Vehicle(
            VehicleId::fromString('VEH-4471'),
            'AB12CDE',                              // @phpstan-ignore-line
            OdometerReading::of(84500)
        )
    )
);


// ═══════════════════════════════════════════════════════════════════════════
// Vehicle — accessors
// ═══════════════════════════════════════════════════════════════════════════

check(
    'id() returns the identity it was given',
    static fn(): bool => (aVehicle())()->id()->value() === 'VEH-4471'
);

check(
    'registration() returns a RegistrationNumber, not a string',
    static fn(): bool => (aVehicle())()->registration() instanceof RegistrationNumber
);

check(
    'odometer() returns an OdometerReading, not an int',
    static fn(): bool => (aVehicle())()->odometer() instanceof OdometerReading
);


// ═══════════════════════════════════════════════════════════════════════════
// Vehicle — identity
// ═══════════════════════════════════════════════════════════════════════════

check(
    'IDENTITY: two vehicles with identical attributes but different ids are NOT equal',
    static function (): bool {
        $a = (aVehicle('VEH-0001'))();
        $b = (aVehicle('VEH-0002'))();
        return $a->equals($b) === false;
    }
);

check(
    'IDENTITY: two objects with the same id ARE equal, whatever else differs',
    static function (): bool {
        $a = (aVehicle('VEH-0001', 'AB12CDE', 84500))();
        $b = (aVehicle('VEH-0001', 'XY98ZAB', 121400))();
        return $a->equals($b) === true;
    }
);

check(
    'IDENTITY: a vehicle whose every attribute has changed is still itself',
    static function (): bool {
        $vehicle = (aVehicle('VEH-0001', 'AB12CDE', 84500))();
        $sameId  = (aVehicle('VEH-0001', 'AB12CDE', 84500))();

        $vehicle->reRegister(RegistrationNumber::fromString('XY98ZAB'));
        $vehicle->recordOdometer(OdometerReading::of(121400));

        return $vehicle->equals($sameId) === true;
    }
);

check(
    'reRegister() changes the registration and not the identity',
    static function (): bool {
        $vehicle = (aVehicle())();
        $vehicle->reRegister(RegistrationNumber::fromString('XY98ZAB'));
        return $vehicle->registration()->value() === 'XY98ZAB'
            && $vehicle->id()->value() === 'VEH-4471';
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// Vehicle — invariant I4
// ═══════════════════════════════════════════════════════════════════════════

check(
    'A higher reading is accepted',
    static function (): bool {
        $vehicle = (aVehicle())();
        $vehicle->recordOdometer(OdometerReading::of(121400));
        return $vehicle->odometer()->kilometres() === 121400;
    }
);

check(
    'INVARIANT I4: a lower reading is refused with DomainException',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            $vehicle = (aVehicle())();
            $vehicle->recordOdometer(OdometerReading::of(84499));
        }
    )
);

check(
    'INVARIANT I4, read literally: the SAME reading again is ACCEPTED',
    static function (): bool {
        $vehicle = (aVehicle())();
        $vehicle->recordOdometer(OdometerReading::of(84500));
        return $vehicle->odometer()->kilometres() === 84500;
    }
);

check(
    'A refused reading leaves the vehicle unchanged',
    static function (): bool {
        $vehicle = (aVehicle())();
        try {
            $vehicle->recordOdometer(OdometerReading::of(1));
        } catch (DomainException) {
            // expected
        }
        return $vehicle->odometer()->kilometres() === 84500;
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// No way past the rules
// ═══════════════════════════════════════════════════════════════════════════

check(
    'No public property of Vehicle can be assigned from outside',
    static function (): bool {
        $reflection = new ReflectionClass(Vehicle::class);

        foreach ($reflection->getProperties() as $property) {
            if (!$property->isPublic() || $property->isStatic()) {
                continue;
            }

            $vehicle = (aVehicle())();
            $name    = $property->getName();

            if (!$property->isInitialized($vehicle)) {
                continue;
            }

            try {
                $vehicle->$name = $property->getValue($vehicle);
            } catch (Throwable) {
                continue;
            }

            return false;
        }

        return true;
    }
);

check(
    'Vehicle has no public method whose name begins with "set"',
    static function (): bool {
        foreach ((new ReflectionClass(Vehicle::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with(strtolower($method->getName()), 'set')) {
                return false;
            }
        }
        return true;
    }
);

check(
    'RULE 1: none of the three files imports anything outside the Workshop namespace',
    static function (): bool {
        foreach ([VehicleId::class, OdometerReading::class, Vehicle::class] as $class) {
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
