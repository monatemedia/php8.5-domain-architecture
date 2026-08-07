<?php
declare(strict_types=1);

/**
 * checks.php — Lesson 1.4 acceptance checks
 * ==========================================
 *
 * THE SPECIFICATION for Workshop\LabourUnits and Workshop\LabourLine.
 *
 * DO NOT EDIT.
 *
 * Depends on Workshop\Money and Workshop\Currency from Lesson 1.2.
 */

use Workshop\Currency;
use Workshop\LabourLine;
use Workshop\LabourUnits;
use Workshop\Money;

acceptance('Lesson 1.4 — Where invariants live: LabourUnits, LabourLine');


// ═══════════════════════════════════════════════════════════════════════════
// Carried forward
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\Money and Workshop\Currency are available (Lesson 1.2)',
    static fn(): bool => class_exists(Money::class) && enum_exists(Currency::class)
);


// ═══════════════════════════════════════════════════════════════════════════
// LabourUnits — shape
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\LabourUnits exists',
    static fn(): bool => class_exists(LabourUnits::class)
);

check(
    'It is declared in domain/workshop/LabourUnits.php',
    static fn(): bool => declared_in(LabourUnits::class, 'domain/workshop/LabourUnits.php')
);

check(
    'LabourUnits is a final readonly class with no public constructor',
    static function (): bool {
        $reflection  = new ReflectionClass(LabourUnits::class);
        $constructor = $reflection->getConstructor();
        return $reflection->isFinal()
            && $reflection->isReadOnly()
            && ($constructor === null || !$constructor->isPublic());
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// LabourUnits — INVARIANT I3, the rounding table
// ═══════════════════════════════════════════════════════════════════════════

check(
    'INVARIANT I3: the full rounding table, exactly',
    static function (): bool {
        $expected = [
            0  => 5,    // the minimum applies even to nothing
            1  => 5,
            5  => 5,
            6  => 5,
            29 => 5,
            30 => 5,    // exactly five tenths
            31 => 6,    // rounds UP
            36 => 6,
            37 => 7,    // rounds UP
            60 => 10,
            61 => 11,
            90 => 15,
        ];

        foreach ($expected as $minutes => $tenths) {
            if (LabourUnits::fromMinutes($minutes)->tenths() !== $tenths) {
                return false;
            }
        }

        return true;
    }
);

check(
    'INVARIANT I3: rounding is UP, never down — 31 minutes is 6 tenths, not 5',
    static fn(): bool => LabourUnits::fromMinutes(31)->tenths() === 6
);

check(
    'INVARIANT I3: the five-tenth minimum applies to zero minutes',
    static fn(): bool => LabourUnits::fromMinutes(0)->tenths() === 5
);

check(
    'Negative minutes are refused with InvalidArgumentException',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => LabourUnits::fromMinutes(-1))
);

check(
    'ofTenths() rebuilds from storage',
    static fn(): bool => LabourUnits::ofTenths(23)->tenths() === 23
);

check(
    'ofTenths() accepts exactly five — the bound is inclusive',
    static fn(): bool => succeeds(static fn() => LabourUnits::ofTenths(5))
);

check(
    'ofTenths() refuses four — below the per-line minimum, so it is corrupt data',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => LabourUnits::ofTenths(4))
);

check(
    'ofTenths() refuses zero',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => LabourUnits::ofTenths(0))
);

check(
    'plus() adds tenths and returns a new instance',
    static function (): bool {
        $a   = LabourUnits::ofTenths(5);
        $sum = $a->plus(LabourUnits::ofTenths(7));
        return $sum->tenths() === 12 && $a->tenths() === 5 && $a !== $sum;
    }
);

check(
    'equals() is by value',
    static fn(): bool => LabourUnits::ofTenths(5)->equals(LabourUnits::ofTenths(5)) === true
        && LabourUnits::ofTenths(5)->equals(LabourUnits::ofTenths(6)) === false
);

check(
    '__toString(): 5 tenths is "0.5 h"',
    static fn(): bool => (string) LabourUnits::ofTenths(5) === '0.5 h'
);

check(
    '__toString(): 10 tenths is "1.0 h"',
    static fn(): bool => (string) LabourUnits::ofTenths(10) === '1.0 h'
);

check(
    '__toString(): 23 tenths is "2.3 h"',
    static fn(): bool => (string) LabourUnits::ofTenths(23) === '2.3 h'
);

check(
    'No float appears anywhere in LabourUnits',
    static fn(): bool => no_float_in(LabourUnits::class)
);


// ═══════════════════════════════════════════════════════════════════════════
// LabourLine
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\LabourLine exists',
    static fn(): bool => class_exists(LabourLine::class)
);

check(
    'It is declared in domain/workshop/LabourLine.php',
    static fn(): bool => declared_in(LabourLine::class, 'domain/workshop/LabourLine.php')
);

check(
    'LabourLine is a final readonly class with no public constructor',
    static function (): bool {
        $reflection  = new ReflectionClass(LabourLine::class);
        $constructor = $reflection->getConstructor();
        return $reflection->isFinal()
            && $reflection->isReadOnly()
            && ($constructor === null || !$constructor->isPublic());
    }
);

check(
    'A line can be created',
    static fn(): bool => succeeds(static fn() => LabourLine::create(
        'Diagnostic',
        LabourUnits::fromMinutes(27),
        Money::of(150, Currency::EUR)
    ))
);

check(
    'description() is trimmed',
    static fn(): bool => LabourLine::create(
        '  Rear brake discs  ',
        LabourUnits::fromMinutes(90),
        Money::of(150, Currency::EUR)
    )->description() === 'Rear brake discs'
);

check(
    'An empty description is refused with InvalidArgumentException',
    static fn(): bool => throws(
        InvalidArgumentException::class,
        static fn() => LabourLine::create('', LabourUnits::fromMinutes(27), Money::of(150, Currency::EUR))
    )
);

check(
    'A whitespace-only description is refused',
    static fn(): bool => throws(
        InvalidArgumentException::class,
        static fn() => LabourLine::create('   ', LabourUnits::fromMinutes(27), Money::of(150, Currency::EUR))
    )
);

check(
    'units() and ratePerTenth() return the value objects, not primitives',
    static function (): bool {
        $line = LabourLine::create('Diagnostic', LabourUnits::fromMinutes(27), Money::of(150, Currency::EUR));
        return $line->units() instanceof LabourUnits && $line->ratePerTenth() instanceof Money;
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// LabourLine — the total is DERIVED
// ═══════════════════════════════════════════════════════════════════════════

check(
    'total() is rate multiplied by tenths',
    static function (): bool {
        // 27 minutes -> 5 tenths (the minimum). 5 x 150 = 750.
        $line = LabourLine::create('Diagnostic', LabourUnits::fromMinutes(27), Money::of(150, Currency::EUR));
        return $line->total()->amountMinor() === 750;
    }
);

check(
    'total() on a longer job',
    static function (): bool {
        // 90 minutes -> 15 tenths. 15 x 150 = 2250.
        $line = LabourLine::create('Rear brake discs', LabourUnits::fromMinutes(90), Money::of(150, Currency::EUR));
        return $line->total()->amountMinor() === 2250;
    }
);

check(
    'total() keeps the currency',
    static function (): bool {
        $line = LabourLine::create('Diagnostic', LabourUnits::fromMinutes(27), Money::of(150, Currency::JPY));
        return $line->total()->currency() === Currency::JPY;
    }
);

check(
    'A zero rate is accepted — the workshop does goodwill work',
    static function (): bool {
        $line = LabourLine::create('Goodwill check-over', LabourUnits::fromMinutes(27), Money::zero(Currency::EUR));
        return $line->total()->isZero() === true;
    }
);

check(
    'The total is DERIVED, not stored — no property holds it',
    static function (): bool {
        foreach ((new ReflectionClass(LabourLine::class))->getProperties() as $property) {
            $name = strtolower($property->getName());
            if (str_contains($name, 'total')) {
                return false;
            }
        }
        return true;
    }
);

check(
    'total() is stable across repeated calls',
    static function (): bool {
        $line = LabourLine::create('Diagnostic', LabourUnits::fromMinutes(27), Money::of(150, Currency::EUR));
        return $line->total()->equals($line->total());
    }
);

check(
    'No float appears anywhere in LabourLine',
    static fn(): bool => no_float_in(LabourLine::class)
);


// ═══════════════════════════════════════════════════════════════════════════
// Rule 1
// ═══════════════════════════════════════════════════════════════════════════

check(
    'RULE 1: neither file imports anything outside the Workshop namespace',
    static function (): bool {
        foreach ([LabourUnits::class, LabourLine::class] as $class) {
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
