<?php
declare(strict_types=1);

/**
 * checks.php — Lesson 2.4 acceptance checks
 * ==========================================
 *
 * THE SPECIFICATION for TaxRate and CourtesyVehicleEligibility — one value
 * object and one domain service, and the point of the lesson is knowing which
 * is which.
 *
 * DO NOT EDIT.
 */

use Workshop\AccountHolderId;
use Workshop\CourtesyVehicleEligibility;
use Workshop\Currency;
use Workshop\JobCard;
use Workshop\JobCardId;
use Workshop\LabourLine;
use Workshop\LabourUnits;
use Workshop\Money;
use Workshop\TaxRate;
use Workshop\VehicleId;

acceptance('Lesson 2.4 — Domain services: TaxRate and CourtesyVehicleEligibility');


// ═══════════════════════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════════════════════

function eur(int $minor): Money
{
    return Money::of($minor, Currency::EUR);
}

/**
 * A job card in progress, with a labour line worth $tenths x 150.
 * 90 minutes -> 15 tenths -> 2250.
 */
function aJobCardWorth(int $minutes = 90, Currency $currency = Currency::EUR): JobCard
{
    $jobCard = JobCard::open(
        JobCardId::fromString('JC-1001'),
        VehicleId::fromString('VEH-4471'),
        AccountHolderId::fromString('AH-0007'),
        $currency
    );

    $jobCard->startWork();
    $jobCard->addLine(LabourLine::create(
        'Rear brake discs',
        LabourUnits::fromMinutes($minutes),
        Money::of(150, $currency)
    ));

    return $jobCard;
}


// ═══════════════════════════════════════════════════════════════════════════
// TaxRate — a VALUE OBJECT
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\TaxRate exists, in domain/workshop/TaxRate.php',
    static fn(): bool => class_exists(TaxRate::class)
        && declared_in(TaxRate::class, 'domain/workshop/TaxRate.php')
);

check(
    'TaxRate is a final readonly class with no public constructor',
    static function (): bool {
        $r = new ReflectionClass(TaxRate::class);
        $c = $r->getConstructor();
        return $r->isFinal() && $r->isReadOnly() && ($c === null || !$c->isPublic());
    }
);

check(
    'Basis points: 2000 is 20%, and ofPercent(20) agrees',
    static fn(): bool => TaxRate::ofBasisPoints(2000)->basisPoints() === 2000
        && TaxRate::ofPercent(20)->basisPoints() === 2000
        && TaxRate::ofPercent(20)->equals(TaxRate::ofBasisPoints(2000))
);

check(
    'Fractional percentages survive: 12.5% is 1250 basis points',
    static fn(): bool => TaxRate::ofBasisPoints(1250)->basisPoints() === 1250
);

check(
    'zero() is zero, and isZero() says so',
    static fn(): bool => TaxRate::zero()->isZero() === true
        && TaxRate::ofBasisPoints(0)->isZero() === true
        && TaxRate::ofBasisPoints(1)->isZero() === false
);

check(
    'A negative rate is refused with InvalidArgumentException',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => TaxRate::ofBasisPoints(-1))
);

check(
    'A rate above 100% is refused — almost always a units error',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => TaxRate::ofBasisPoints(10001))
        && succeeds(static fn() => TaxRate::ofBasisPoints(10000))
);

check(
    'applyTo() gives the TAX, not the total: 20% of 4500 is 900',
    static function (): bool {
        $tax = TaxRate::ofPercent(20)->applyTo(eur(4500));
        return $tax->amountMinor() === 900 && $tax->currency() === Currency::EUR;
    }
);

check(
    'addTo() gives the total INCLUDING tax: 4500 + 900 = 5400',
    static fn(): bool => TaxRate::ofPercent(20)->addTo(eur(4500))->amountMinor() === 5400
);

check(
    'Rounding is half up, and integer throughout: 20% of 999 is 200',
    static function (): bool {
        // 999 * 0.2 = 199.8, which rounds to 200. A float would give 199 on
        // some machines and 200 on others.
        return TaxRate::ofPercent(20)->applyTo(eur(999))->amountMinor() === 200;
    }
);

check(
    'A fractional rate rounds correctly: 12.5% of 1000 is 125',
    static fn(): bool => TaxRate::ofBasisPoints(1250)->applyTo(eur(1000))->amountMinor() === 125
);

check(
    'A zero rate adds nothing',
    static fn(): bool => TaxRate::zero()->applyTo(eur(4500))->isZero() === true
        && TaxRate::zero()->addTo(eur(4500))->amountMinor() === 4500
);

check(
    'Tax on a zero amount is zero',
    static fn(): bool => TaxRate::ofPercent(20)->applyTo(Money::zero(Currency::EUR))->isZero() === true
);

check(
    'The currency is carried through, not lost',
    static fn(): bool => TaxRate::ofPercent(20)->applyTo(Money::of(4500, Currency::JPY))->currency() === Currency::JPY
);

check(
    '__toString(): 2000 -> "20.00%", 1250 -> "12.50%"',
    static fn(): bool => (string) TaxRate::ofBasisPoints(2000) === '20.00%'
        && (string) TaxRate::ofBasisPoints(1250) === '12.50%'
        && (string) TaxRate::ofBasisPoints(505) === '5.05%'
);

check(
    'No float anywhere in TaxRate — it is money arithmetic',
    static fn(): bool => no_float_in(TaxRate::class)
);

check(
    'PLACEMENT: applying tax is a method on the RATE, not a separate service',
    static function (): bool {
        // The operation needs a rate and an amount and nothing else, so by
        // Lesson 1.4's test it belongs on the value that owns the knowledge.
        // A TaxCalculator service would be the same arithmetic with an extra
        // class and an extra injection.
        return method_exists(TaxRate::class, 'applyTo')
            && !class_exists('Workshop\TaxCalculator')
            && !class_exists('Workshop\TaxService');
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// CourtesyVehicleEligibility — a DOMAIN SERVICE
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\CourtesyVehicleEligibility exists, in the right file',
    static fn(): bool => class_exists(CourtesyVehicleEligibility::class)
        && declared_in(CourtesyVehicleEligibility::class, 'domain/workshop/CourtesyVehicleEligibility.php')
);

check(
    'It is a final readonly class with no public constructor',
    static function (): bool {
        $r = new ReflectionClass(CourtesyVehicleEligibility::class);
        $c = $r->getConstructor();
        return $r->isFinal() && $r->isReadOnly() && ($c === null || !$c->isPublic());
    }
);

check(
    'STATELESS: it holds only its policy, and nothing that changes between calls',
    static function (): bool {
        $properties = (new ReflectionClass(CourtesyVehicleEligibility::class))->getProperties();

        // One property: the threshold. A service that accumulated anything
        // across calls would not be a domain service — Rule 5.
        if (count($properties) !== 1) {
            return false;
        }

        return (string) $properties[0]->getType() === 'Workshop\Money';
    }
);

check(
    'THE POLICY IS PASSED IN: requiringSpendOf() takes the threshold',
    static function (): bool {
        $service = CourtesyVehicleEligibility::requiringSpendOf(eur(15000));
        return $service->minimumSpend()->amountMinor() === 15000;
    }
);

check(
    'A job card at or above the minimum qualifies',
    static function (): bool {
        // 90 minutes -> 15 tenths x 150 = 2250.
        $service = CourtesyVehicleEligibility::requiringSpendOf(eur(2250));
        return $service->isEligible(aJobCardWorth()) === true;
    }
);

check(
    'A job card below the minimum does not',
    static function (): bool {
        $service = CourtesyVehicleEligibility::requiringSpendOf(eur(2251));
        return $service->isEligible(aJobCardWorth()) === false;
    }
);

check(
    'unconditional() qualifies anything not terminal — the fleet contract',
    static function (): bool {
        $service = CourtesyVehicleEligibility::unconditional(Currency::EUR);

        $tiny = JobCard::open(
            JobCardId::fromString('JC-2002'),
            VehicleId::fromString('VEH-1'),
            AccountHolderId::fromString('AH-1'),
            Currency::EUR
        );

        return $service->isEligible($tiny) === true;
    }
);

check(
    'A terminal job card never qualifies, however large',
    static function (): bool {
        $service = CourtesyVehicleEligibility::unconditional(Currency::EUR);

        $jobCard = aJobCardWorth();
        $jobCard->cancel();

        return $service->isEligible($jobCard) === false;
    }
);

check(
    'reasonsAgainst() is empty when it qualifies',
    static function (): bool {
        $service = CourtesyVehicleEligibility::requiringSpendOf(eur(1000));
        return $service->reasonsAgainst(aJobCardWorth()) === [];
    }
);

check(
    'reasonsAgainst() explains, in business language, and returns a list',
    static function (): bool {
        $service = CourtesyVehicleEligibility::requiringSpendOf(eur(15000));
        $reasons = $service->reasonsAgainst(aJobCardWorth());

        if (count($reasons) !== 1 || array_keys($reasons) !== [0]) {
            return false;
        }

        // It must name the shortfall rather than merely saying no.
        return str_contains($reasons[0], '150.00') && str_contains($reasons[0], '22.50');
    }
);

check(
    'Two failing rules give two reasons',
    static function (): bool {
        $service = CourtesyVehicleEligibility::requiringSpendOf(eur(15000));

        $jobCard = aJobCardWorth();
        $jobCard->cancel();

        return count($service->reasonsAgainst($jobCard)) === 2;
    }
);

check(
    'A currency mismatch is refused with DomainException',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            $service = CourtesyVehicleEligibility::requiringSpendOf(Money::of(15000, Currency::USD));
            $service->isEligible(aJobCardWorth(90, Currency::EUR));
        }
    )
);

check(
    'DOMAIN, NOT APPLICATION: it takes a JobCard, never a JobCardId',
    static function (): bool {
        foreach (['isEligible', 'reasonsAgainst'] as $name) {
            $params = (new ReflectionMethod(CourtesyVehicleEligibility::class, $name))->getParameters();

            if (count($params) !== 1 || (string) $params[0]->getType() !== 'Workshop\JobCard') {
                return false;
            }
        }
        return true;
    }
);

check(
    'PLACEMENT: JobCard did NOT grow an eligibility method',
    static function (): bool {
        // The threshold is negotiated — 0 for fleet accounts, and it moves.
        // A policy welded into an entity is a bug with a commencement date.
        foreach (get_class_methods(JobCard::class) as $method) {
            if (str_contains(strtolower($method), 'eligib')) {
                return false;
            }
        }
        return true;
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// Rule 1
// ═══════════════════════════════════════════════════════════════════════════

check(
    'RULE 1: neither file imports anything outside the Workshop namespace',
    static function (): bool {
        foreach ([TaxRate::class, CourtesyVehicleEligibility::class] as $class) {
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
