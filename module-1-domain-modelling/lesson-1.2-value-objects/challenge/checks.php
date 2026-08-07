<?php
declare(strict_types=1);

/**
 * checks.php — Lesson 1.2 acceptance checks
 * ==========================================
 *
 * THE SPECIFICATION for Workshop\Currency, Workshop\Money and
 * Workshop\RegistrationNumber.
 *
 * DO NOT EDIT. Run against your code by starter.php and against the reference
 * implementation by solution/run.php — it must be the same file in both cases
 * or it proves nothing.
 */

use Workshop\Currency;
use Workshop\Money;
use Workshop\RegistrationNumber;

acceptance('Lesson 1.2 — Value objects: Currency, Money, RegistrationNumber');


// ═══════════════════════════════════════════════════════════════════════════
// Currency
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\Currency exists and is a backed enum',
    static function (): bool {
        if (!enum_exists(Currency::class)) {
            return false;
        }
        return (new ReflectionEnum(Currency::class))->isBacked();
    }
);

check(
    'It has exactly the five cases EUR, USD, GBP, JPY, KWD',
    static function (): bool {
        $values = array_map(static fn(Currency $c): string => $c->value, Currency::cases());
        sort($values);
        return $values === ['EUR', 'GBP', 'JPY', 'KWD', 'USD'];
    }
);

check(
    'minorUnits(): EUR, USD and GBP are 2',
    static fn(): bool => Currency::EUR->minorUnits() === 2
        && Currency::USD->minorUnits() === 2
        && Currency::GBP->minorUnits() === 2
);

check(
    'minorUnits(): JPY is 0',
    static fn(): bool => Currency::JPY->minorUnits() === 0
);

check(
    'minorUnits(): KWD is 3',
    static fn(): bool => Currency::KWD->minorUnits() === 3
);

check(
    'format(): EUR 4500 -> "EUR 45.00"',
    static fn(): bool => Currency::EUR->format(4500) === 'EUR 45.00'
);

check(
    'format(): EUR 0 -> "EUR 0.00"',
    static fn(): bool => Currency::EUR->format(0) === 'EUR 0.00'
);

check(
    'format(): EUR 7 -> "EUR 0.07"  (the padding case)',
    static fn(): bool => Currency::EUR->format(7) === 'EUR 0.07'
);

check(
    'format(): JPY 4500 -> "JPY 4500"  (no decimal point at all)',
    static fn(): bool => Currency::JPY->format(4500) === 'JPY 4500'
);

check(
    'format(): KWD 4500 -> "KWD 4.500"  (three minor units)',
    static fn(): bool => Currency::KWD->format(4500) === 'KWD 4.500'
);


// ═══════════════════════════════════════════════════════════════════════════
// Money — shape
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\Money exists',
    static fn(): bool => class_exists(Money::class)
);

check(
    'It is declared in domain/workshop/Money.php',
    static fn(): bool => declared_in(Money::class, 'domain/workshop/Money.php')
);

check(
    'Money is a final readonly class',
    static function (): bool {
        $reflection = new ReflectionClass(Money::class);
        return $reflection->isFinal() && $reflection->isReadOnly();
    }
);

check(
    'Money has no public constructor',
    static function (): bool {
        $constructor = (new ReflectionClass(Money::class))->getConstructor();
        return $constructor === null || !$constructor->isPublic();
    }
);

check(
    'No float appears anywhere in Money',
    // no_float_in() lives in lib/acceptance.php. It strips comments first, so a
    // doc block explaining why floats are avoided does not fail the check.
    static fn(): bool => no_float_in(Money::class)
);

check(
    'No float appears anywhere in Currency either — format() must be exact',
    static fn(): bool => no_float_in(Currency::class)
);


// ═══════════════════════════════════════════════════════════════════════════
// Money — construction
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Money::of() builds a value',
    static fn(): bool => Money::of(4500, Currency::EUR)->amountMinor() === 4500
);

check(
    'Money::of() keeps its currency',
    static fn(): bool => Money::of(4500, Currency::EUR)->currency() === Currency::EUR
);

check(
    'Money::zero() is zero',
    static fn(): bool => Money::zero(Currency::EUR)->amountMinor() === 0
        && Money::zero(Currency::EUR)->isZero() === true
);

check(
    'INVARIANT I1: a negative amount is refused with InvalidArgumentException',
    static fn(): bool => throws(
        InvalidArgumentException::class,
        static fn() => Money::of(-1, Currency::EUR)
    )
);

check(
    'Zero is allowed — it is not negative',
    static fn(): bool => succeeds(static fn() => Money::of(0, Currency::EUR))
);


// ═══════════════════════════════════════════════════════════════════════════
// Money — arithmetic
// ═══════════════════════════════════════════════════════════════════════════

check(
    'plus() adds',
    static fn(): bool => Money::of(4500, Currency::EUR)
        ->plus(Money::of(3000, Currency::EUR))
        ->amountMinor() === 7500
);

check(
    'plus() returns a NEW instance; the original is unchanged',
    static function (): bool {
        $original = Money::of(4500, Currency::EUR);
        $sum      = $original->plus(Money::of(3000, Currency::EUR));
        return $original->amountMinor() === 4500
            && $sum->amountMinor() === 7500
            && $original !== $sum;
    }
);

check(
    'INVARIANT I1: plus() across currencies is refused with DomainException',
    static fn(): bool => throws(
        DomainException::class,
        static fn() => Money::of(4500, Currency::EUR)->plus(Money::of(3000, Currency::USD))
    )
);

check(
    'minus() subtracts',
    static fn(): bool => Money::of(4500, Currency::EUR)
        ->minus(Money::of(500, Currency::EUR))
        ->amountMinor() === 4000
);

check(
    'minus() down to exactly zero is allowed',
    static fn(): bool => Money::of(4500, Currency::EUR)
        ->minus(Money::of(4500, Currency::EUR))
        ->isZero() === true
);

check(
    'INVARIANT I1: minus() below zero is refused with DomainException',
    static fn(): bool => throws(
        DomainException::class,
        static fn() => Money::of(4500, Currency::EUR)->minus(Money::of(4501, Currency::EUR))
    )
);

check(
    'minus() across currencies is refused with DomainException',
    static fn(): bool => throws(
        DomainException::class,
        static fn() => Money::of(4500, Currency::EUR)->minus(Money::of(1, Currency::USD))
    )
);

check(
    'times() multiplies',
    static fn(): bool => Money::of(4500, Currency::EUR)->times(3)->amountMinor() === 13500
);

check(
    'times(0) is zero, not an error',
    static fn(): bool => Money::of(4500, Currency::EUR)->times(0)->isZero() === true
);

check(
    'times() with a negative multiplier is refused with InvalidArgumentException',
    static fn(): bool => throws(
        InvalidArgumentException::class,
        static fn() => Money::of(4500, Currency::EUR)->times(-1)
    )
);


// ═══════════════════════════════════════════════════════════════════════════
// Money — comparison and rendering
// ═══════════════════════════════════════════════════════════════════════════

check(
    'equals(): same amount and currency are equal',
    static fn(): bool => Money::of(4500, Currency::EUR)->equals(Money::of(4500, Currency::EUR)) === true
);

check(
    'equals(): same amount, different currency, NOT equal',
    static fn(): bool => Money::of(4500, Currency::EUR)->equals(Money::of(4500, Currency::USD)) === false
);

check(
    'equals(): different amount, same currency, NOT equal',
    static fn(): bool => Money::of(4500, Currency::EUR)->equals(Money::of(4501, Currency::EUR)) === false
);

check(
    'isGreaterThan() compares',
    static fn(): bool => Money::of(4501, Currency::EUR)->isGreaterThan(Money::of(4500, Currency::EUR)) === true
        && Money::of(4500, Currency::EUR)->isGreaterThan(Money::of(4500, Currency::EUR)) === false
);

check(
    'isGreaterThan() across currencies is refused with DomainException',
    static fn(): bool => throws(
        DomainException::class,
        static fn() => Money::of(4500, Currency::EUR)->isGreaterThan(Money::of(1, Currency::USD))
    )
);

check(
    '__toString() delegates to Currency::format()',
    static fn(): bool => (string) Money::of(4500, Currency::EUR) === 'EUR 45.00'
        && (string) Money::of(4500, Currency::JPY) === 'JPY 4500'
);


// ═══════════════════════════════════════════════════════════════════════════
// RegistrationNumber
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\RegistrationNumber exists',
    static fn(): bool => class_exists(RegistrationNumber::class)
);

check(
    'It is declared in domain/workshop/RegistrationNumber.php',
    static fn(): bool => declared_in(RegistrationNumber::class, 'domain/workshop/RegistrationNumber.php')
);

check(
    'RegistrationNumber is a final readonly class with no public constructor',
    static function (): bool {
        $reflection  = new ReflectionClass(RegistrationNumber::class);
        $constructor = $reflection->getConstructor();
        return $reflection->isFinal()
            && $reflection->isReadOnly()
            && ($constructor === null || !$constructor->isPublic());
    }
);

check(
    'A clean registration survives unchanged',
    static fn(): bool => RegistrationNumber::fromString('AB12CDE')->value() === 'AB12CDE'
);

check(
    'INVARIANT I2: lowercase is uppercased',
    static fn(): bool => RegistrationNumber::fromString('ab12cde')->value() === 'AB12CDE'
);

check(
    'INVARIANT I2: hyphens are stripped',
    static fn(): bool => RegistrationNumber::fromString('AB-12-CDE')->value() === 'AB12CDE'
);

check(
    'INVARIANT I2: spaces are stripped, inside and out',
    static fn(): bool => RegistrationNumber::fromString('  ab 12 cde  ')->value() === 'AB12CDE'
);

check(
    'INVARIANT I2: all four spellings are equal to each other',
    static function (): bool {
        $canonical = RegistrationNumber::fromString('AB12CDE');
        foreach (['ab12cde', 'AB-12-CDE', '  ab 12 cde  ', 'A B 1 2 C D E'] as $spelling) {
            if (!$canonical->equals(RegistrationNumber::fromString($spelling))) {
                return false;
            }
        }
        return true;
    }
);

check(
    'Two genuinely different registrations are not equal',
    static fn(): bool => RegistrationNumber::fromString('AB12CDE')
        ->equals(RegistrationNumber::fromString('XY98ZAB')) === false
);

check(
    '__toString() gives the normalised form',
    static fn(): bool => (string) RegistrationNumber::fromString(' ab-12-cde ') === 'AB12CDE'
);

check(
    'Refused: empty string',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => RegistrationNumber::fromString(''))
);

check(
    'Refused: only whitespace',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => RegistrationNumber::fromString('   '))
);

check(
    'Refused: too short (1 character)',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => RegistrationNumber::fromString('A'))
);

check(
    'Refused: too long (9 characters)',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => RegistrationNumber::fromString('AB12CDEFG'))
);

check(
    'Accepted: exactly 2 characters, one letter one digit',
    static fn(): bool => RegistrationNumber::fromString('A1')->value() === 'A1'
);

check(
    'Accepted: exactly 8 characters',
    static fn(): bool => RegistrationNumber::fromString('AB12CDEF')->value() === 'AB12CDEF'
);

check(
    'Refused: no digit',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => RegistrationNumber::fromString('ABCDEFG'))
);

check(
    'Refused: no letter',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => RegistrationNumber::fromString('12345'))
);

check(
    'Refused: illegal character (!)',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => RegistrationNumber::fromString('AB!12'))
);

check(
    'Refused: illegal character (underscore)',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => RegistrationNumber::fromString('AB_12'))
);


// ═══════════════════════════════════════════════════════════════════════════
// Rule 1 — the domain layer imports nothing
// ═══════════════════════════════════════════════════════════════════════════

check(
    'RULE 1: none of the three files imports anything outside the Workshop namespace',
    static function (): bool {
        foreach ([Currency::class, Money::class, RegistrationNumber::class] as $class) {
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
                    continue;          // a global built-in is not a dependency
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
