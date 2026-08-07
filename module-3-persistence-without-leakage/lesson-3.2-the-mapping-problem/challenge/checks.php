<?php
declare(strict_types=1);

/**
 * checks.php — Lesson 3.2 acceptance checks
 * ==========================================
 *
 * THE SPECIFICATION for reconstitute() on LabourLine, PartLine and JobCard, and
 * for Infrastructure\JobCardMapper.
 *
 * DO NOT EDIT.
 *
 * The round-trip checks are the heart of it. Everything a rich aggregate does
 * to stay honest is something a mapper has to work around, and the round trip
 * is where you find out whether you worked around it or broke it.
 */

use Infrastructure\JobCardMapper;
use Workshop\AccountHolderId;
use Workshop\Currency;
use Workshop\JobCard;
use Workshop\JobCardId;
use Workshop\JobCardOpened;
use Workshop\JobCardStatus;
use Workshop\LabourLine;
use Workshop\LabourUnits;
use Workshop\Line;
use Workshop\LineId;
use Workshop\Money;
use Workshop\PartLine;
use Workshop\PartNumber;
use Workshop\VehicleId;

acceptance('Lesson 3.2 — The mapping problem');


// ═══════════════════════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════════════════════

/** 15 tenths x 150 = 2250. */
function aLabourLine(string $description = 'Rear brake discs'): LabourLine
{
    return LabourLine::create($description, LabourUnits::fromMinutes(90), Money::of(150, Currency::EUR));
}

/** 2 x 4100 = 8200. */
function aPartLine(string $description = 'Front pads'): PartLine
{
    return PartLine::create(PartNumber::fromString('K9K-1234'), $description, 2, Money::of(4100, Currency::EUR));
}

/**
 * In Progress, one labour line and one part line. Total 10450.
 * Events drained, so the event checks below start from a known state.
 */
function aJobCardWithLines(): JobCard
{
    $jobCard = JobCard::open(
        JobCardId::fromString('JC-1001'),
        VehicleId::fromString('VEH-4471'),
        AccountHolderId::fromString('AH-0007'),
        Currency::EUR
    );

    $jobCard->startWork();
    $jobCard->addLine(aLabourLine());
    $jobCard->addLine(aPartLine());
    $jobCard->releaseEvents();

    return $jobCard;
}

/** Round-trip a job card through the mapper and back. */
function roundTrip(JobCard $jobCard): JobCard
{
    $mapper = new JobCardMapper();

    return $mapper->fromRows($mapper->toRow($jobCard), $mapper->toLineRows($jobCard));
}


// ═══════════════════════════════════════════════════════════════════════════
// reconstitute() — the compromise, on each class
// ═══════════════════════════════════════════════════════════════════════════

check(
    'LabourLine::reconstitute() exists and takes a LineId first',
    static function (): bool {
        if (!method_exists(LabourLine::class, 'reconstitute')) {
            return false;
        }
        $params = (new ReflectionMethod(LabourLine::class, 'reconstitute'))->getParameters();

        return count($params) === 4 && (string) $params[0]->getType() === 'Workshop\LineId';
    }
);

check(
    'PartLine::reconstitute() exists and takes a LineId first',
    static function (): bool {
        if (!method_exists(PartLine::class, 'reconstitute')) {
            return false;
        }
        $params = (new ReflectionMethod(PartLine::class, 'reconstitute'))->getParameters();

        return count($params) === 5 && (string) $params[0]->getType() === 'Workshop\LineId';
    }
);

check(
    'IDENTITY SURVIVES: reconstitute() keeps the LineId it is given',
    static function (): bool {
        $id = LineId::fromString('LN-FIXED');

        $labour = LabourLine::reconstitute($id, 'Diagnostic', LabourUnits::ofTenths(5), Money::of(150, Currency::EUR));
        $part   = PartLine::reconstitute($id, PartNumber::fromString('K9K-1'), 'Pads', 1, Money::of(100, Currency::EUR));

        return $labour->id()->value() === 'LN-FIXED' && $part->id()->value() === 'LN-FIXED';
    }
);

check(
    'create() still mints a NEW id — the two factories mean different things',
    static fn(): bool => !aLabourLine()->id()->equals(aLabourLine()->id())
);

check(
    'create() signatures are UNCHANGED — reconstitute() is additive',
    static function (): bool {
        $labour = (new ReflectionMethod(LabourLine::class, 'create'))->getParameters();
        $part   = (new ReflectionMethod(PartLine::class, 'create'))->getParameters();

        return count($labour) === 3 && count($part) === 4;
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// JobCard::reconstitute() — the sharpest form of the argument
// ═══════════════════════════════════════════════════════════════════════════

check(
    'JobCard::reconstitute() takes seven arguments, ending in two arrays',
    static function (): bool {
        if (!method_exists(JobCard::class, 'reconstitute')) {
            return false;
        }
        $params = (new ReflectionMethod(JobCard::class, 'reconstitute'))->getParameters();
        $types  = array_map(static fn(ReflectionParameter $p): string => (string) $p->getType(), $params);

        return $types === [
            'Workshop\JobCardId',
            'Workshop\VehicleId',
            'Workshop\AccountHolderId',
            'Workshop\Currency',
            'Workshop\JobCardStatus',
            'array',
            'array',
        ];
    }
);

check(
    'IT RECORDS NOTHING: loading is not opening',
    static function (): bool {
        $jobCard = JobCard::reconstitute(
            JobCardId::fromString('JC-1001'),
            VehicleId::fromString('VEH-4471'),
            AccountHolderId::fromString('AH-0007'),
            Currency::EUR,
            JobCardStatus::Booked,
            [],
            []
        );

        return $jobCard->releaseEvents() === [] && $jobCard->hasRecordedEvents() === false;
    }
);

check(
    'open() still DOES record — the two are not the same operation',
    static function (): bool {
        $events = JobCard::open(
            JobCardId::fromString('JC-1001'),
            VehicleId::fromString('VEH-4471'),
            AccountHolderId::fromString('AH-0007'),
            Currency::EUR
        )->releaseEvents();

        return count($events) === 1 && $events[0] instanceof JobCardOpened;
    }
);

check(
    'IT SETS THE STATUS DIRECTLY: an Invoiced job card loads as Invoiced',
    static function (): bool {
        $jobCard = JobCard::reconstitute(
            JobCardId::fromString('JC-1001'),
            VehicleId::fromString('VEH-4471'),
            AccountHolderId::fromString('AH-0007'),
            Currency::EUR,
            JobCardStatus::Invoiced,
            [aLabourLine()],
            []
        );

        return $jobCard->status() === JobCardStatus::Invoiced;
    }
);

check(
    'Even Cancelled, which no sequence of public calls could reach from Booked in one step',
    static function (): bool {
        $jobCard = JobCard::reconstitute(
            JobCardId::fromString('JC-1001'),
            VehicleId::fromString('VEH-4471'),
            AccountHolderId::fromString('AH-0007'),
            Currency::EUR,
            JobCardStatus::Cancelled,
            [],
            []
        );

        return $jobCard->status() === JobCardStatus::Cancelled;
    }
);

check(
    'A reconstituted job card still enforces I9 from where it now is',
    static fn(): bool => throws(
        DomainException::class,
        static function (): void {
            // Loaded as Invoiced — terminal. Nothing may follow, and the rules
            // apply from this moment on even though the past was not replayed.
            $jobCard = JobCard::reconstitute(
                JobCardId::fromString('JC-1001'),
                VehicleId::fromString('VEH-4471'),
                AccountHolderId::fromString('AH-0007'),
                Currency::EUR,
                JobCardStatus::Invoiced,
                [],
                []
            );

            $jobCard->cancel();
        }
    )
);

check(
    'It restores the pending authorisations',
    static function (): bool {
        $jobCard = JobCard::reconstitute(
            JobCardId::fromString('JC-1001'),
            VehicleId::fromString('VEH-4471'),
            AccountHolderId::fromString('AH-0007'),
            Currency::EUR,
            JobCardStatus::AwaitingAuthorisation,
            [],
            ['Rear discs below minimum thickness']
        );

        return $jobCard->pendingAuthorisations() === ['Rear discs below minimum thickness'];
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// The mapper — placement
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Infrastructure\JobCardMapper exists, NOT in domain/workshop/',
    static function (): bool {
        if (!class_exists(JobCardMapper::class)) {
            return false;
        }
        $file = str_replace('\\', '/', (string) (new ReflectionClass(JobCardMapper::class))->getFileName());

        return str_contains($file, 'infrastructure/') && !str_contains($file, 'domain/workshop/');
    }
);

check(
    'Its namespace is Infrastructure',
    static fn(): bool => (new ReflectionClass(JobCardMapper::class))->getNamespaceName() === 'Infrastructure'
);

check(
    'RULE 1: no domain file references the Infrastructure namespace in code',
    static function (): bool {
        foreach ([JobCard::class, LabourLine::class, PartLine::class] as $class) {
            if (str_contains(class_source_without_comments($class), 'Infrastructure')) {
                return false;
            }
        }

        return true;
    }
);

check(
    'The mapper contains no SQL and no PDO — mapping is not querying',
    static function (): bool {
        $code = class_source_without_comments(JobCardMapper::class);

        foreach (['PDO', 'SELECT', 'INSERT', 'sqlite'] as $needle) {
            if (str_contains($code, $needle)) {
                return false;
            }
        }

        return true;
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// The mapper — aggregate to rows
// ═══════════════════════════════════════════════════════════════════════════

check(
    'toRow() produces the job card\'s own row',
    static function (): bool {
        $row = (new JobCardMapper())->toRow(aJobCardWithLines());

        return $row['id'] === 'JC-1001'
            && $row['vehicle_id'] === 'VEH-4471'
            && $row['account_holder_id'] === 'AH-0007'
            && $row['currency'] === 'EUR'
            && $row['status'] === 'in_progress';
    }
);

check(
    'toRow() stores the pending authorisations as JSON',
    static function (): bool {
        $jobCard = aJobCardWithLines();
        $jobCard->discoverAdditionalWork('Rear discs');

        $row = (new JobCardMapper())->toRow($jobCard);

        return json_decode((string) $row['pending_authorisations'], true) === ['Rear discs'];
    }
);

check(
    'toLineRows() produces one row per line, with a kind discriminator',
    static function (): bool {
        $rows = (new JobCardMapper())->toLineRows(aJobCardWithLines());

        if (count($rows) !== 2 || array_keys($rows) !== [0, 1]) {
            return false;
        }

        return $rows[0]['kind'] === 'labour' && $rows[1]['kind'] === 'part';
    }
);

check(
    'A labour row carries tenths and rate; its part columns are null',
    static function (): bool {
        $row = (new JobCardMapper())->toLineRows(aJobCardWithLines())[0];

        return $row['tenths'] === 15
            && $row['rate_minor'] === 150
            && $row['part_number'] === null
            && $row['quantity'] === null
            && $row['unit_price_minor'] === null;
    }
);

check(
    'A part row carries part number, quantity and unit price; its labour columns are null',
    static function (): bool {
        $row = (new JobCardMapper())->toLineRows(aJobCardWithLines())[1];

        return $row['part_number'] === 'K9K-1234'
            && $row['quantity'] === 2
            && $row['unit_price_minor'] === 4100
            && $row['tenths'] === null
            && $row['rate_minor'] === null;
    }
);

check(
    'Every line row carries its LineId',
    static function (): bool {
        $jobCard = aJobCardWithLines();
        $ids     = array_map(static fn(Line $l): string => $l->id()->value(), $jobCard->lines());
        $rows    = (new JobCardMapper())->toLineRows($jobCard);

        return $rows[0]['line_id'] === $ids[0] && $rows[1]['line_id'] === $ids[1];
    }
);

check(
    'An unknown Line implementation is refused rather than silently dropped',
    static fn(): bool => throws(
        RuntimeException::class,
        static function (): void {
            $strange = new class implements Line {
                public function id(): LineId { return LineId::fromString('LN-X'); }
                public function description(): string { return 'Something else'; }
                public function total(): Money { return Money::zero(Currency::EUR); }
            };

            $jobCard = aJobCardWithLines();
            $jobCard->addLine($strange);

            (new JobCardMapper())->toLineRows($jobCard);
        }
    )
);


// ═══════════════════════════════════════════════════════════════════════════
// The mapper — the round trip
// ═══════════════════════════════════════════════════════════════════════════

check(
    'ROUND TRIP: identity, references, currency and status all survive',
    static function (): bool {
        $before = aJobCardWithLines();
        $after  = roundTrip($before);

        return $after->id()->equals($before->id())
            && $after->vehicleId()->equals($before->vehicleId())
            && $after->accountHolderId()->equals($before->accountHolderId())
            && $after->currency() === $before->currency()
            && $after->status() === $before->status();
    }
);

check(
    'ROUND TRIP: every LineId survives, in order',
    static function (): bool {
        $before = aJobCardWithLines();
        $after  = roundTrip($before);

        $idsBefore = array_map(static fn(Line $l): string => $l->id()->value(), $before->lines());
        $idsAfter  = array_map(static fn(Line $l): string => $l->id()->value(), $after->lines());

        return $idsBefore === $idsAfter && $idsBefore !== [];
    }
);

check(
    'ROUND TRIP: the total is identical — 2250 + 8200 = 10450',
    static function (): bool {
        $before = aJobCardWithLines();
        $after  = roundTrip($before);

        return $before->total()->amountMinor() === 10450
            && $after->total()->equals($before->total());
    }
);

check(
    'ROUND TRIP: the lines come back as the right CLASSES, not a common shape',
    static function (): bool {
        $lines = roundTrip(aJobCardWithLines())->lines();

        return $lines[0] instanceof LabourLine && $lines[1] instanceof PartLine;
    }
);

check(
    'ROUND TRIP: a labour line keeps its units and rate',
    static function (): bool {
        /** @var LabourLine $line */
        $line = roundTrip(aJobCardWithLines())->lines()[0];

        return $line->units()->tenths() === 15
            && $line->ratePerTenth()->amountMinor() === 150
            && $line->description() === 'Rear brake discs';
    }
);

check(
    'ROUND TRIP: a part line keeps its part number, quantity and unit price',
    static function (): bool {
        /** @var PartLine $line */
        $line = roundTrip(aJobCardWithLines())->lines()[1];

        return $line->partNumber()->value() === 'K9K-1234'
            && $line->quantity() === 2
            && $line->unitPrice()->amountMinor() === 4100;
    }
);

check(
    'ROUND TRIP: it records no events',
    static fn(): bool => roundTrip(aJobCardWithLines())->releaseEvents() === []
);

check(
    'ROUND TRIP: pending authorisations survive',
    static function (): bool {
        $before = aJobCardWithLines();
        $before->discoverAdditionalWork('Rear discs below minimum thickness');

        $after = roundTrip($before);

        return $after->pendingAuthorisations() === ['Rear discs below minimum thickness']
            && $after->status() === JobCardStatus::AwaitingAuthorisation;
    }
);

check(
    'ROUND TRIP: a job card with no lines survives, total zero in its currency',
    static function (): bool {
        $empty = JobCard::open(
            JobCardId::fromString('JC-2002'),
            VehicleId::fromString('VEH-1'),
            AccountHolderId::fromString('AH-1'),
            Currency::JPY
        );
        $empty->releaseEvents();

        $after = roundTrip($empty);

        return $after->lineCount() === 0
            && $after->total()->isZero()
            && $after->currency() === Currency::JPY;
    }
);

check(
    'ROUND TRIP: an invoiced job card comes back terminal, and stays terminal',
    static function (): bool {
        $jobCard = aJobCardWithLines();
        $jobCard->complete();
        $jobCard->invoice();
        $jobCard->releaseEvents();

        $after = roundTrip($jobCard);

        return $after->status() === JobCardStatus::Invoiced
            && throws(DomainException::class, static fn() => $after->addLine(aPartLine()));
    }
);

check(
    'ROUND TRIP: a reloaded job card can still be worked on',
    static function (): bool {
        $after = roundTrip(aJobCardWithLines());

        // In Progress, so this is legal — and it must behave exactly as if the
        // job card had never left memory.
        $after->complete();
        $after->invoice();

        return $after->status() === JobCardStatus::Invoiced
            && count($after->releaseEvents()) === 2;
    }
);

check(
    'A row with an unknown line kind is refused rather than silently skipped',
    static fn(): bool => throws(
        RuntimeException::class,
        static function (): void {
            $mapper  = new JobCardMapper();
            $jobCard = aJobCardWithLines();

            $row      = $mapper->toRow($jobCard);
            $lineRows = $mapper->toLineRows($jobCard);

            $lineRows[0]['kind'] = 'something_new';

            $mapper->fromRows($row, $lineRows);
        }
    )
);


acceptance_report();
