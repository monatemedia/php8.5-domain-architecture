<?php
declare(strict_types=1);

/**
 * checks.php — Lesson 5.1 acceptance checks
 * ==========================================
 *
 * THE SPECIFICATION for `Infrastructure\Legacy\JobCardRecord`.
 *
 * DO NOT EDIT.
 *
 * Section 5 is the lesson: Module 4's exception tree and Module 4's boundary
 * mapper work against an Active Record model with no changes at all.
 *
 * Section 6 is the other half of the lesson, and it asserts something the
 * course has never asserted before — that a thing was NOT built.
 */

use Infrastructure\Legacy\ActiveRecord;
use Infrastructure\Legacy\JobCardRecord;
use Infrastructure\ProblemDetailsMapper;
use Workshop\Currency;
use Workshop\IllegalStatusTransition;
use Workshop\JobCardStatus;
use Workshop\Money;

acceptance('Lesson 5.1 — When the framework owns your models');


// ═══════════════════════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════════════════════

/** A row as the legacy system would have written it, before any of this. */
function aLegacyRow(string $status = 'booked', string $id = 'JC-1042'): array
{
    return [
        'id'                => $id,
        'vehicle_id'        => 'VEH-4471',
        'account_holder_id' => 'AH-0007',
        'status'            => $status,
        'total_cents'       => 24_500,
        'currency'          => 'EUR',
        'customer_note'     => 'Ring before 4pm',
    ];
}

function aRecord(string $status = 'booked', string $id = 'JC-1042'): JobCardRecord
{
    return JobCardRecord::hydrate(aLegacyRow($status, $id));
}

function captured(callable $fn): ?\Throwable
{
    try {
        $fn();
    } catch (\Throwable $e) {
        return $e;
    }

    return null;
}

/** @return string forward-slashed path of the file a class is declared in */
function fileOf(string $class): string
{
    $file = (new ReflectionClass($class))->getFileName();

    return $file === false ? '' : str_replace('\\', '/', $file);
}


// ═══════════════════════════════════════════════════════════════════════════
// 1 — It is a framework object, and it lives where framework objects live
// ═══════════════════════════════════════════════════════════════════════════

check('Infrastructure\Legacy\JobCardRecord exists and extends ActiveRecord',
    fn() => class_exists(JobCardRecord::class)
        && is_subclass_of(JobCardRecord::class, ActiveRecord::class));

check('It is declared OUTSIDE domain/workshop/',
    fn() => !str_contains(fileOf(JobCardRecord::class), '/domain/workshop/'));

check('It is in the Infrastructure\Legacy namespace',
    fn() => (new ReflectionClass(JobCardRecord::class))->getNamespaceName() === 'Infrastructure\Legacy');

check('A row written by the old system still reads correctly',
    function (): bool {
        $record = aRecord('in_progress');

        return $record->status() === JobCardStatus::InProgress
            && $record->id()->value() === 'JC-1042'
            && $record->customer_note === 'Ring before 4pm';
    });


// ═══════════════════════════════════════════════════════════════════════════
// 2 — RUNG 1: the guarded columns cannot be written from outside
// ═══════════════════════════════════════════════════════════════════════════

check('Assigning status directly is refused',
    fn() => throws(LogicException::class, static fn() => aRecord()->status = 'invoiced'));

check('Assigning total_cents directly is refused',
    fn() => throws(LogicException::class, static fn() => aRecord()->total_cents = 999));

check('Assigning currency directly is refused',
    fn() => throws(LogicException::class, static fn() => aRecord()->currency = 'GBP'));

check('The refusal says what to do instead',
    function (): bool {
        $thrown = captured(static fn() => aRecord()->status = 'invoiced');

        return $thrown !== null && str_contains($thrown->getMessage(), 'status');
    });

check('An UNGUARDED column is still as convenient as it was',
    function (): bool {
        $record = aRecord();
        $record->customer_note = 'Ring after 5pm';

        return $record->customer_note === 'Ring after 5pm';
    });

check('...and so is a column nobody has thought about yet',
    function (): bool {
        $record = aRecord();
        $record->courtesy_vehicle_id = 'CV-0003';

        return $record->courtesy_vehicle_id === 'CV-0003';
    });


// ═══════════════════════════════════════════════════════════════════════════
// 3 — RUNG 2: the invariant is in the model
// ═══════════════════════════════════════════════════════════════════════════

check('A legal transition works through the named method',
    function (): bool {
        $record = aRecord('booked');
        $record->startWork();

        return $record->status() === JobCardStatus::InProgress;
    });

check('The full legal lifecycle runs',
    function (): bool {
        $record = aRecord('booked');
        $record->startWork();
        $record->complete();
        $record->invoice();

        return $record->status() === JobCardStatus::Invoiced;
    });

check('An illegal transition raises Workshop\IllegalStatusTransition',
    fn() => throws(IllegalStatusTransition::class, static fn() => aRecord('booked')->invoice()));

check('...from a terminal state too',
    fn() => throws(IllegalStatusTransition::class, static fn() => aRecord('invoiced')->cancel()));

check('A refused transition leaves the column untouched',
    function (): bool {
        $record = aRecord('booked');
        captured(static fn() => $record->invoice());

        return $record->status() === JobCardStatus::Booked;
    });

check('The rule comes from Workshop\JobCardStatus, not from a copy',
    function (): bool {
        // Every transition the enum permits from Booked must work, and every
        // one it forbids must not. A hand-written copy of the table inside the
        // record would drift; this asserts there is no copy.
        $record = aRecord('booked');
        $allowed = JobCardStatus::Booked->allowedNext();

        foreach (JobCardStatus::cases() as $target) {
            $method = match ($target) {
                JobCardStatus::InProgress => 'startWork',
                JobCardStatus::Completed  => 'complete',
                JobCardStatus::Invoiced   => 'invoice',
                JobCardStatus::Cancelled  => 'cancel',
                default                   => null,
            };

            if ($method === null) {
                continue;
            }

            $fresh  = aRecord('booked');
            $thrown = captured(static fn() => $fresh->{$method}());
            $legal  = in_array($target, $allowed, true);

            if ($legal !== ($thrown === null)) {
                return false;
            }
        }

        return true;
    });


// ═══════════════════════════════════════════════════════════════════════════
// 4 — RUNG 3: value objects over the existing columns
// ═══════════════════════════════════════════════════════════════════════════

check('total() returns a Workshop\Money built from two columns',
    function (): bool {
        $total = aRecord()->total();

        return $total instanceof Money
            && $total->amountMinor() === 24_500
            && $total->currency() === Currency::EUR;
    });

check('priceAt() takes a Money, not an int',
    function (): bool {
        $parameter = (new ReflectionMethod(JobCardRecord::class, 'priceAt'))->getParameters()[0] ?? null;

        return (string) $parameter?->getType() === Money::class;
    });

check('priceAt() writes both halves together',
    function (): bool {
        $record = aRecord();
        $record->priceAt(Money::of(31_000, Currency::GBP));

        $row = $record->toRow();

        return $row['total_cents'] === 31_000 && $row['currency'] === 'GBP';
    });

check('The schema did not move — the columns are still scalars',
    function (): bool {
        $record = aRecord();
        $record->priceAt(Money::of(31_000, Currency::GBP));

        $row = $record->toRow();

        return is_int($row['total_cents']) && is_string($row['currency']);
    });


// ═══════════════════════════════════════════════════════════════════════════
// 5 — MODULE 4 TRANSFERS WHOLESALE
// ═══════════════════════════════════════════════════════════════════════════
//
// The exception thrown by an Active Record model, mapped by the boundary
// written in Lesson 4.4, with no new catalogue entry and no change to either.

check('The exception carries Module 4\'s failure code',
    function (): bool {
        $thrown = captured(static fn() => aRecord('booked')->invoice());

        return $thrown instanceof IllegalStatusTransition
            && $thrown->failureCode() === 'illegal-status-transition';
    });

check('...and Module 4\'s structured context',
    function (): bool {
        $thrown = captured(static fn() => aRecord('booked')->invoice());

        return $thrown instanceof IllegalStatusTransition
            && $thrown->context()['from'] === 'booked'
            && $thrown->context()['to'] === 'invoiced'
            && $thrown->context()['jobCardId'] === 'JC-1042';
    });

check('ProblemDetailsMapper turns it into a 409, unchanged',
    function (): bool {
        $thrown  = captured(static fn() => aRecord('booked')->invoice());
        $problem = (new ProblemDetailsMapper())->map($thrown, '/job-cards/JC-1042/invoice');

        return $problem->status === 409
            && $problem->extensions['from'] === 'booked'
            && $problem->instance === '/job-cards/JC-1042/invoice';
    });

check('...and the mapper needed no entry for a legacy model, because it keys on the code',
    function (): bool {
        // Identical body from an Active Record record and from a domain
        // aggregate: the mapper cannot tell them apart, and should not.
        $fromRecord = (new ProblemDetailsMapper())
            ->map(captured(static fn() => aRecord('booked')->invoice()))
            ->jsonSerialize();

        $fromDomain = (new ProblemDetailsMapper())
            ->map(IllegalStatusTransition::from(
                \Workshop\JobCardId::fromString('JC-1042'),
                JobCardStatus::Booked,
                JobCardStatus::Invoiced
            ))
            ->jsonSerialize();

        return $fromRecord === $fromDomain;
    });


// ═══════════════════════════════════════════════════════════════════════════
// 6 — RUNG 5 WAS NOT PAID, AND THAT IS THE POINT
// ═══════════════════════════════════════════════════════════════════════════
//
// The first checks in this course that assert something was NOT built. A
// solution that quietly introduces a mapping layer has answered a different
// question than the one asked.

check('No Workshop\JobCard aggregate is involved',
    fn() => !str_contains(class_source_without_comments(JobCardRecord::class), 'Workshop\JobCard;')
        && !str_contains(class_source_without_comments(JobCardRecord::class), 'JobCard('));

check('No repository is involved',
    function (): bool {
        $source = class_source_without_comments(JobCardRecord::class);

        return !str_contains($source, 'JobCards')
            && !str_contains($source, 'Repository');
    });

check('No mapper is involved',
    fn() => !str_contains(class_source_without_comments(JobCardRecord::class), 'Mapper'));

check('The domain still imports nothing from Infrastructure',
    fn() => !str_contains(class_source_without_comments(JobCardStatus::class), 'Infrastructure')
        && !str_contains(class_source_without_comments(Money::class), 'Infrastructure')
        && !str_contains(class_source_without_comments(IllegalStatusTransition::class), 'Infrastructure'));

check('The arrow points inward: the record imports FROM Workshop',
    function (): bool {
        $source = class_source_without_comments(JobCardRecord::class);

        return str_contains($source, 'use Workshop\\');
    });


// ═══════════════════════════════════════════════════════════════════════════
// 7 — It is still an ORM object, and still works like one
// ═══════════════════════════════════════════════════════════════════════════
//
// A change that improves the model and breaks persistence has not helped
// anybody. This is the half of the lesson that stops rung 1 becoming rung 5 by
// accident.

check('save() then find() round-trips through the toy ORM',
    function (): bool {
        JobCardRecord::truncate();

        $record = aRecord('booked', 'JC-5001');
        $record->startWork();
        $record->save();

        $loaded = JobCardRecord::find('JC-5001');

        return $loaded instanceof JobCardRecord
            && $loaded->status() === JobCardStatus::InProgress;
    });

check('The status column holds the enum\'s backed value, readable by anything',
    function (): bool {
        JobCardRecord::truncate();

        $record = aRecord('booked', 'JC-5002');
        $record->startWork();
        $record->save();

        $row = JobCardRecord::find('JC-5002')?->toRow() ?? [];

        return $row['status'] === 'in_progress';
    });

check('hydrate() skips the constructor and the record still enforces its rules',
    function (): bool {
        // The ORM builds objects without asking. Lesson 3.2's reconstitution
        // problem, arriving as a property of the framework rather than as a
        // decision — and here it costs nothing, because the rules are on the
        // transitions rather than on construction.
        $record = JobCardRecord::hydrate(aLegacyRow('invoiced'));

        return $record->status() === JobCardStatus::Invoiced
            && throws(IllegalStatusTransition::class, static fn() => $record->startWork());
    });

check('A row in a state the rules forbid can still be LOADED',
    function (): bool {
        // Legacy data is not clean, and a model that refuses to load a bad row
        // is a model nobody can deploy. The rules govern CHANGES; loading is
        // not a change. Lesson 3.2 said the same thing about reconstitute().
        $record = JobCardRecord::hydrate(aLegacyRow('cancelled') + ['total_cents' => 24_500]);

        return $record->status() === JobCardStatus::Cancelled
            && $record->total()->amountMinor() === 24_500;
    });


acceptance_report();
