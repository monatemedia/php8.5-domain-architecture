<?php
declare(strict_types=1);

/**
 * checks.php — Lesson 2.1 acceptance checks
 * ==========================================
 *
 * THE SPECIFICATION for the JobCard aggregate and everything it is built from.
 *
 * DO NOT EDIT.
 *
 * Depends on Module 1: Currency, Money, VehicleId, LabourUnits, LabourLine.
 */

use Workshop\AccountHolderId;
use Workshop\Currency;
use Workshop\JobCard;
use Workshop\JobCardId;
use Workshop\JobCardStatus;
use Workshop\LabourLine;
use Workshop\LabourUnits;
use Workshop\Line;
use Workshop\LineId;
use Workshop\Money;
use Workshop\PartLine;
use Workshop\PartNumber;
use Workshop\VehicleId;

acceptance('Lesson 2.1 — Aggregate roots and boundaries: the JobCard aggregate');


// ═══════════════════════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════════════════════

function eur(int $minor): Money
{
    return Money::of($minor, Currency::EUR);
}

/** A labour line worth 15 tenths x 150 = 2250. */
function aLabourLine(string $description = 'Rear brake discs'): LabourLine
{
    return LabourLine::create($description, LabourUnits::fromMinutes(90), eur(150));
}

/** A part line worth 2 x 4100 = 8200. */
function aPartLine(string $description = 'Front pads', int $quantity = 2): PartLine
{
    return PartLine::create(PartNumber::fromString('K9K-1234'), $description, $quantity, eur(4100));
}

function anOpenJobCard(Currency $currency = Currency::EUR): JobCard
{
    return JobCard::open(
        JobCardId::fromString('JC-1001'),
        VehicleId::fromString('VEH-4471'),
        AccountHolderId::fromString('AH-0007'),
        $currency
    );
}

/** Booked -> InProgress -> Completed, with one labour line. */
function aCompletedJobCard(): JobCard
{
    $jobCard = anOpenJobCard();
    $jobCard->startWork();
    $jobCard->addLine(aLabourLine());
    $jobCard->complete();
    return $jobCard;
}


// ═══════════════════════════════════════════════════════════════════════════
// Carried forward from Module 1
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Module 1 is available: Currency, Money, VehicleId, LabourUnits, LabourLine',
    static fn(): bool => enum_exists(Currency::class)
        && class_exists(Money::class)
        && class_exists(VehicleId::class)
        && class_exists(LabourUnits::class)
        && class_exists(LabourLine::class)
);


// ═══════════════════════════════════════════════════════════════════════════
// LineId
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\LineId exists, in domain/workshop/LineId.php',
    static fn(): bool => class_exists(LineId::class)
        && declared_in(LineId::class, 'domain/workshop/LineId.php')
);

check(
    'LineId is a final readonly class with no public constructor',
    static function (): bool {
        $r = new ReflectionClass(LineId::class);
        $c = $r->getConstructor();
        return $r->isFinal() && $r->isReadOnly() && ($c === null || !$c->isPublic());
    }
);

check(
    'LineId::generate() produces 200 distinct identities',
    static function (): bool {
        $seen = [];
        for ($i = 0; $i < 200; $i++) {
            $seen[LineId::generate()->value()] = true;
        }
        return count($seen) === 200;
    }
);

check(
    'LineId::fromString() round-trips and refuses empty',
    static fn(): bool => LineId::fromString('LN-1')->value() === 'LN-1'
        && throws(InvalidArgumentException::class, static fn() => LineId::fromString('  '))
);

check(
    'LineId::equals() is by value',
    static fn(): bool => LineId::fromString('LN-1')->equals(LineId::fromString('LN-1'))
        && !LineId::fromString('LN-1')->equals(LineId::fromString('LN-2'))
);


// ═══════════════════════════════════════════════════════════════════════════
// Line — the interface
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\Line is an interface',
    static fn(): bool => interface_exists(Line::class)
);

check(
    'Line declares exactly id(), description() and total()',
    static function (): bool {
        $methods = array_map(
            static fn(ReflectionMethod $m): string => $m->getName(),
            (new ReflectionClass(Line::class))->getMethods()
        );
        sort($methods);
        return $methods === ['description', 'id', 'total'];
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// LabourLine — the addition, with nothing removed
// ═══════════════════════════════════════════════════════════════════════════

check(
    'LabourLine implements Line',
    static fn(): bool => is_subclass_of(LabourLine::class, Line::class)
);

check(
    'LabourLine::create() keeps its Lesson 1.4 signature exactly',
    static function (): bool {
        $params = (new ReflectionMethod(LabourLine::class, 'create'))->getParameters();
        if (count($params) !== 3) {
            return false;
        }
        $types = array_map(
            static fn(ReflectionParameter $p): string => (string) $p->getType(),
            $params
        );
        return $types === ['string', 'Workshop\LabourUnits', 'Workshop\Money'];
    }
);

check(
    'LabourLine now has an id, minted internally',
    static fn(): bool => aLabourLine()->id() instanceof LineId
);

check(
    'Two labour lines with identical attributes have DIFFERENT ids',
    static fn(): bool => !aLabourLine()->id()->equals(aLabourLine()->id())
);

check(
    'LabourLine still totals correctly: 90 min -> 15 tenths x 150 = 2250',
    static fn(): bool => aLabourLine()->total()->amountMinor() === 2250
);

check(
    'LabourLine is still final readonly with a non-public constructor',
    static function (): bool {
        $r = new ReflectionClass(LabourLine::class);
        $c = $r->getConstructor();
        return $r->isFinal() && $r->isReadOnly() && ($c === null || !$c->isPublic());
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// PartNumber
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\PartNumber exists, in domain/workshop/PartNumber.php',
    static fn(): bool => class_exists(PartNumber::class)
        && declared_in(PartNumber::class, 'domain/workshop/PartNumber.php')
);

check(
    'PartNumber is a final readonly class with no public constructor',
    static function (): bool {
        $r = new ReflectionClass(PartNumber::class);
        $c = $r->getConstructor();
        return $r->isFinal() && $r->isReadOnly() && ($c === null || !$c->isPublic());
    }
);

check(
    'PartNumber normalises: lowercase is uppercased, spaces stripped',
    static fn(): bool => PartNumber::fromString('k9k-1234')->value() === 'K9K-1234'
        && PartNumber::fromString(' K9K 1234 ')->value() === 'K9K1234'
);

check(
    'PartNumber KEEPS hyphens — unlike RegistrationNumber',
    static fn(): bool => PartNumber::fromString('K9K-1234')->value() === 'K9K-1234'
        && !PartNumber::fromString('K9K-1234')->equals(PartNumber::fromString('K9K1234'))
);

check(
    'PartNumber accepts the length bounds: 3 and 24 characters',
    static fn(): bool => succeeds(static fn() => PartNumber::fromString('ABC'))
        && succeeds(static fn() => PartNumber::fromString(str_repeat('A', 24)))
);

check(
    'PartNumber refuses: too short, too long, illegal character',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => PartNumber::fromString('AB'))
        && throws(InvalidArgumentException::class, static fn() => PartNumber::fromString(str_repeat('A', 25)))
        && throws(InvalidArgumentException::class, static fn() => PartNumber::fromString('K9K_1234'))
);

check(
    'PartNumber refuses a leading or trailing hyphen',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => PartNumber::fromString('-K9K'))
        && throws(InvalidArgumentException::class, static fn() => PartNumber::fromString('K9K-'))
);

check(
    'PartNumber refuses empty and whitespace-only',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => PartNumber::fromString(''))
        && throws(InvalidArgumentException::class, static fn() => PartNumber::fromString('   '))
);


// ═══════════════════════════════════════════════════════════════════════════
// PartLine
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\PartLine exists and implements Line',
    static fn(): bool => class_exists(PartLine::class) && is_subclass_of(PartLine::class, Line::class)
);

check(
    'PartLine is a final readonly class with no public constructor',
    static function (): bool {
        $r = new ReflectionClass(PartLine::class);
        $c = $r->getConstructor();
        return $r->isFinal() && $r->isReadOnly() && ($c === null || !$c->isPublic());
    }
);

check(
    'PartLine keeps what it was given',
    static function (): bool {
        $line = aPartLine();
        return $line->partNumber()->value() === 'K9K-1234'
            && $line->description() === 'Front pads'
            && $line->quantity() === 2
            && $line->unitPrice()->amountMinor() === 4100;
    }
);

check(
    'PartLine::total() is unit price x quantity: 2 x 4100 = 8200',
    static fn(): bool => aPartLine()->total()->amountMinor() === 8200
);

check(
    'PartLine trims its description',
    static fn(): bool => aPartLine('  Front pads  ')->description() === 'Front pads'
);

check(
    'PartLine refuses an empty description',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => aPartLine('   '))
);

check(
    'PartLine refuses a quantity below one',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => aPartLine('Front pads', 0))
        && throws(InvalidArgumentException::class, static fn() => aPartLine('Front pads', -1))
);

check(
    'PartLine accepts a zero unit price — parts under warranty',
    static function (): bool {
        $line = PartLine::create(PartNumber::fromString('K9K-1234'), 'Warranty part', 3, Money::zero(Currency::EUR));
        return $line->total()->isZero();
    }
);

check(
    'Two part lines with identical attributes have different ids',
    static fn(): bool => !aPartLine()->id()->equals(aPartLine()->id())
);


// ═══════════════════════════════════════════════════════════════════════════
// JobCardStatus — the transition table
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\JobCardStatus is a backed enum with the seven expected values',
    static function (): bool {
        if (!enum_exists(JobCardStatus::class) || !(new ReflectionEnum(JobCardStatus::class))->isBacked()) {
            return false;
        }
        $values = array_map(static fn(JobCardStatus $s): string => $s->value, JobCardStatus::cases());
        sort($values);
        return $values === [
            'awaiting_authorisation',
            'awaiting_parts',
            'booked',
            'cancelled',
            'completed',
            'in_progress',
            'invoiced',
        ];
    }
);

check(
    'The transition table matches DOMAIN.md exactly',
    static function (): bool {
        $expected = [
            'booked'                 => ['in_progress', 'cancelled'],
            'in_progress'            => ['awaiting_authorisation', 'awaiting_parts', 'completed', 'cancelled'],
            'awaiting_authorisation' => ['in_progress'],
            'awaiting_parts'         => ['in_progress'],
            'completed'              => ['invoiced'],
            'invoiced'               => [],
            'cancelled'              => [],
        ];

        foreach (JobCardStatus::cases() as $status) {
            $actual = array_map(
                static fn(JobCardStatus $s): string => $s->value,
                $status->allowedNext()
            );
            sort($actual);

            $want = $expected[$status->value];
            sort($want);

            if ($actual !== $want) {
                return false;
            }
        }

        return true;
    }
);

check(
    'Invoiced and Cancelled are terminal; nothing else is',
    static function (): bool {
        foreach (JobCardStatus::cases() as $status) {
            $shouldBeTerminal = in_array($status, [JobCardStatus::Invoiced, JobCardStatus::Cancelled], true);
            if ($status->isTerminal() !== $shouldBeTerminal) {
                return false;
            }
        }
        return true;
    }
);

check(
    'No state may transition to itself',
    static function (): bool {
        foreach (JobCardStatus::cases() as $status) {
            if ($status->canTransitionTo($status)) {
                return false;
            }
        }
        return true;
    }
);

check(
    'Completed CANNOT become Cancelled — that is what a credit note is for',
    static fn(): bool => JobCardStatus::Completed->canTransitionTo(JobCardStatus::Cancelled) === false
);

check(
    'Exactly 9 of the 49 possible transitions are legal',
    static function (): bool {
        $legal = 0;
        foreach (JobCardStatus::cases() as $from) {
            foreach (JobCardStatus::cases() as $to) {
                $legal += $from->canTransitionTo($to) ? 1 : 0;
            }
        }
        return $legal === 9;
    }
);

check(
    'label(): awaiting_authorisation -> "Awaiting Authorisation"',
    static fn(): bool => JobCardStatus::AwaitingAuthorisation->label() === 'Awaiting Authorisation'
        && JobCardStatus::Booked->label() === 'Booked'
);


// ═══════════════════════════════════════════════════════════════════════════
// JobCardId and AccountHolderId
// ═══════════════════════════════════════════════════════════════════════════

check(
    'JobCardId and AccountHolderId exist in the right files',
    static fn(): bool => class_exists(JobCardId::class)
        && declared_in(JobCardId::class, 'domain/workshop/JobCardId.php')
        && class_exists(AccountHolderId::class)
        && declared_in(AccountHolderId::class, 'domain/workshop/AccountHolderId.php')
);

check(
    'Both generate distinct identities and round-trip from a string',
    static fn(): bool => !JobCardId::generate()->equals(JobCardId::generate())
        && !AccountHolderId::generate()->equals(AccountHolderId::generate())
        && JobCardId::fromString('JC-1')->value() === 'JC-1'
        && AccountHolderId::fromString('AH-1')->value() === 'AH-1'
);

check(
    'Both refuse an empty value',
    static fn(): bool => throws(InvalidArgumentException::class, static fn() => JobCardId::fromString(' '))
        && throws(InvalidArgumentException::class, static fn() => AccountHolderId::fromString(' '))
);


// ═══════════════════════════════════════════════════════════════════════════
// JobCard — shape and boundary
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\JobCard exists, in domain/workshop/JobCard.php',
    static fn(): bool => class_exists(JobCard::class)
        && declared_in(JobCard::class, 'domain/workshop/JobCard.php')
);

check(
    'JobCard is final and NOT readonly — an aggregate root has a lifetime',
    static function (): bool {
        $r = new ReflectionClass(JobCard::class);
        return $r->isFinal() && $r->isReadOnly() === false;
    }
);

check(
    'JobCard::open() builds one, in Booked, with no lines',
    static function (): bool {
        $jobCard = anOpenJobCard();
        return $jobCard->status() === JobCardStatus::Booked
            && $jobCard->lineCount() === 0
            && $jobCard->lines() === [];
    }
);

check(
    'BOUNDARY: JobCard holds identities, never Vehicle or AccountHolder objects',
    static function (): bool {
        foreach ((new ReflectionClass(JobCard::class))->getProperties() as $property) {
            $type = (string) $property->getType();
            if (str_contains($type, 'Workshop\Vehicle') && !str_contains($type, 'VehicleId')) {
                return false;
            }
            if (str_contains($type, 'AccountHolder') && !str_contains($type, 'AccountHolderId')) {
                return false;
            }
        }

        $jobCard = anOpenJobCard();
        return $jobCard->vehicleId() instanceof VehicleId
            && $jobCard->accountHolderId() instanceof AccountHolderId;
    }
);

check(
    'JobCard remembers its identity and currency',
    static function (): bool {
        $jobCard = anOpenJobCard();
        return $jobCard->id()->value() === 'JC-1001'
            && $jobCard->currency() === Currency::EUR;
    }
);

check(
    'INVARIANT I5: total() is Money::zero in the job card currency when empty',
    static function (): bool {
        $total = anOpenJobCard()->total();
        return $total->isZero() && $total->currency() === Currency::EUR;
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// JobCard — lines
// ═══════════════════════════════════════════════════════════════════════════

check(
    'addLine() accepts both kinds of line',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $jobCard->addLine(aLabourLine());
        $jobCard->addLine(aPartLine());
        return $jobCard->lineCount() === 2;
    }
);

check(
    'INVARIANT I5: total() is the sum of the lines — 2250 + 8200 = 10450',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $jobCard->addLine(aLabourLine());
        $jobCard->addLine(aPartLine());
        return $jobCard->total()->amountMinor() === 10450;
    }
);

check(
    'lines() returns a list, in insertion order',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $labour  = aLabourLine();
        $part    = aPartLine();
        $jobCard->addLine($labour);
        $jobCard->addLine($part);

        $lines = $jobCard->lines();
        return array_keys($lines) === [0, 1]
            && $lines[0]->id()->equals($labour->id())
            && $lines[1]->id()->equals($part->id());
    }
);

check(
    'lines() cannot be used to change the aggregate',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $jobCard->addLine(aLabourLine());

        $stolen   = $jobCard->lines();
        $stolen[] = aPartLine();

        return $jobCard->lineCount() === 1;
    }
);

check(
    'removeLine() removes the line with that identity, and only that one',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $labour  = aLabourLine();
        $part    = aPartLine();
        $jobCard->addLine($labour);
        $jobCard->addLine($part);

        $jobCard->removeLine($labour->id());

        return $jobCard->lineCount() === 1
            && $jobCard->lines()[0]->id()->equals($part->id())
            && $jobCard->total()->amountMinor() === 8200;
    }
);

check(
    'removeLine() with an unknown id is refused with DomainException',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            $jobCard = anOpenJobCard();
            $jobCard->addLine(aLabourLine());
            $jobCard->removeLine(LineId::fromString('LN-NOPE'));
        }
    )
);

check(
    'addLine() refuses a line in a different currency, with DomainException',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            $jobCard = anOpenJobCard(Currency::EUR);
            $jobCard->addLine(LabourLine::create(
                'Diagnostic',
                LabourUnits::fromMinutes(30),
                Money::of(150, Currency::USD)
            ));
        }
    )
);

check(
    'A refused line is not attached',
    static function (): bool {
        $jobCard = anOpenJobCard();
        try {
            $jobCard->addLine(LabourLine::create(
                'Diagnostic',
                LabourUnits::fromMinutes(30),
                Money::of(150, Currency::USD)
            ));
        } catch (DomainException) {
            // expected
        }
        return $jobCard->lineCount() === 0;
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// JobCard — INVARIANT I6, terminal states
// ═══════════════════════════════════════════════════════════════════════════

check(
    'INVARIANT I6: no line may be ADDED to an invoiced job card',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            $jobCard = aCompletedJobCard();
            $jobCard->invoice();
            $jobCard->addLine(aPartLine());
        }
    )
);

check(
    'INVARIANT I6: no line may be REMOVED from an invoiced job card',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            $jobCard = anOpenJobCard();
            $jobCard->startWork();
            $labour = aLabourLine();
            $jobCard->addLine($labour);
            $jobCard->complete();
            $jobCard->invoice();
            $jobCard->removeLine($labour->id());
        }
    )
);

check(
    'INVARIANT I6 applies to Cancelled too, not only Invoiced',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            $jobCard = anOpenJobCard();
            $jobCard->cancel();
            $jobCard->addLine(aPartLine());
        }
    )
);


// ═══════════════════════════════════════════════════════════════════════════
// JobCard — INVARIANT I9, the lifecycle
// ═══════════════════════════════════════════════════════════════════════════

check(
    'The happy path: Booked -> InProgress -> Completed -> Invoiced',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $jobCard->startWork();
        $jobCard->addLine(aLabourLine());
        $jobCard->complete();
        $jobCard->invoice();
        return $jobCard->status() === JobCardStatus::Invoiced;
    }
);

check(
    'INVARIANT I7: a job card cannot be invoiced before completion',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            $jobCard = anOpenJobCard();
            $jobCard->startWork();
            $jobCard->invoice();
        }
    )
);

check(
    'INVARIANT I9: a completed job card CANNOT be cancelled',
    static fn(): bool => throws(
        DomainException::class,
        static fn() => aCompletedJobCard()->cancel()
    )
);

check(
    'INVARIANT I9: Invoiced is terminal — nothing follows it',
    static function (): bool {
        foreach (['startWork', 'complete', 'invoice', 'cancel', 'awaitParts', 'partsArrived'] as $method) {
            $refused = throws(DomainException::class, static function () use ($method): void {
                $jobCard = aCompletedJobCard();
                $jobCard->invoice();
                $jobCard->{$method}();
            });
            if (!$refused) {
                return false;
            }
        }
        return true;
    }
);

check(
    'INVARIANT I9: no state transitions to itself — startWork() twice is refused',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            $jobCard = anOpenJobCard();
            $jobCard->startWork();
            $jobCard->startWork();
        }
    )
);

check(
    'A refused transition leaves the status unchanged',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $jobCard->startWork();
        try {
            $jobCard->invoice();
        } catch (DomainException) {
            // expected
        }
        return $jobCard->status() === JobCardStatus::InProgress;
    }
);

check(
    'The parts detour: InProgress -> AwaitingParts -> InProgress -> Completed',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $jobCard->startWork();
        $jobCard->awaitParts();
        if ($jobCard->status() !== JobCardStatus::AwaitingParts) {
            return false;
        }
        $jobCard->partsArrived();
        $jobCard->complete();
        return $jobCard->status() === JobCardStatus::Completed;
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// JobCard — additional work and INVARIANT I8
// ═══════════════════════════════════════════════════════════════════════════

check(
    'discoverAdditionalWork() records it and moves to Awaiting Authorisation',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $jobCard->startWork();
        $jobCard->discoverAdditionalWork('Rear discs below minimum thickness');

        return $jobCard->status() === JobCardStatus::AwaitingAuthorisation
            && $jobCard->pendingAuthorisations() === ['Rear discs below minimum thickness'];
    }
);

check(
    'discoverAdditionalWork() trims and refuses an empty description',
    static fn(): bool => throws(
        InvalidArgumentException::class,
        static function (): void {
            $jobCard = anOpenJobCard();
            $jobCard->startWork();
            $jobCard->discoverAdditionalWork('   ');
        }
    )
);

check(
    'ORDER: a refused discoverAdditionalWork() records nothing',
    static function (): bool {
        // Booked has no path to AwaitingAuthorisation, so this must be refused
        // AND must not leave a pending item behind.
        $jobCard = anOpenJobCard();
        try {
            $jobCard->discoverAdditionalWork('Rear discs');
        } catch (DomainException) {
            // expected
        }
        return $jobCard->pendingAuthorisations() === []
            && $jobCard->status() === JobCardStatus::Booked;
    }
);

check(
    'authoriseAdditionalWork() clears the list and returns to In Progress',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $jobCard->startWork();
        $jobCard->discoverAdditionalWork('Rear discs');
        $jobCard->authoriseAdditionalWork();

        return $jobCard->status() === JobCardStatus::InProgress
            && $jobCard->pendingAuthorisations() === [];
    }
);

check(
    'ORDER: a second discoverAdditionalWork() is refused and records nothing',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $jobCard->startWork();
        $jobCard->discoverAdditionalWork('Rear discs');

        // Awaiting Authorisation cannot transition to itself, so this is
        // refused — and must not append.
        try {
            $jobCard->discoverAdditionalWork('Also the handbrake cable');
        } catch (DomainException) {
            // expected
        }

        return $jobCard->pendingAuthorisations() === ['Rear discs'];
    }
);

check(
    'authoriseAdditionalWork() is refused when nothing is awaiting authorisation',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            $jobCard = anOpenJobCard();
            $jobCard->startWork();
            $jobCard->authoriseAdditionalWork();
        }
    )
);

check(
    'authoriseAdditionalWork() is refused from Awaiting Parts — a legal transition, but nothing was proposed',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            $jobCard = anOpenJobCard();
            $jobCard->startWork();
            $jobCard->awaitParts();
            $jobCard->authoriseAdditionalWork();
        }
    )
);

check(
    'INVARIANT I8: a job card awaiting authorisation cannot be completed',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            $jobCard = anOpenJobCard();
            $jobCard->startWork();
            $jobCard->discoverAdditionalWork('Rear discs');
            $jobCard->complete();
        }
    )
);

check(
    'Full path: discover, authorise, complete, invoice',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $jobCard->startWork();
        $jobCard->addLine(aLabourLine());
        $jobCard->discoverAdditionalWork('Rear discs below minimum thickness');
        $jobCard->authoriseAdditionalWork();
        $jobCard->addLine(aPartLine());
        $jobCard->complete();
        $jobCard->invoice();

        return $jobCard->status() === JobCardStatus::Invoiced
            && $jobCard->total()->amountMinor() === 10450
            && $jobCard->pendingAuthorisations() === [];
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// No way past the rules
// ═══════════════════════════════════════════════════════════════════════════

check(
    'No public property of JobCard can be assigned from outside',
    static function (): bool {
        $reflection = new ReflectionClass(JobCard::class);

        foreach ($reflection->getProperties() as $property) {
            if (!$property->isPublic() || $property->isStatic()) {
                continue;
            }

            $jobCard = anOpenJobCard();
            $name    = $property->getName();

            if (!$property->isInitialized($jobCard)) {
                continue;
            }

            try {
                $jobCard->$name = $property->getValue($jobCard);
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
        $classes = [
            JobCard::class, JobCardId::class, AccountHolderId::class, LineId::class,
            PartNumber::class, PartLine::class, LabourLine::class,
        ];

        foreach ($classes as $class) {
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
    'RULE 1: none of the new files imports anything outside the Workshop namespace',
    static function (): bool {
        $classes = [
            JobCard::class, JobCardStatus::class, JobCardId::class, AccountHolderId::class,
            LineId::class, Line::class, PartNumber::class, PartLine::class, LabourLine::class,
        ];

        foreach ($classes as $class) {
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

check(
    'No float anywhere in JobCard or PartLine',
    static fn(): bool => no_float_in(JobCard::class) && no_float_in(PartLine::class)
);


acceptance_report();
