<?php
declare(strict_types=1);

/**
 * checks.php — Lesson 5.2 acceptance checks
 * ==========================================
 *
 * THE SPECIFICATION for:
 *
 *     Workshop\PartStock                        the one aggregate extracted
 *     Workshop\InsufficientStock                a new leaf, state branch
 *     Workshop\InvalidQuantity                  a new leaf, input branch
 *     Infrastructure\Legacy\StockReservations   the strangler
 *     Infrastructure\ProblemDetailsMapper       two new catalogue entries
 *
 * DO NOT EDIT.
 *
 * Section 4 is the lesson: a CHARACTERISATION TEST that runs the old path and
 * the new path over the same inputs and asserts they agree — except in three
 * named cases where the old one was wrong, which the test also pins down.
 *
 * Section 6 asserts you extracted exactly ONE aggregate and left the rest
 * alone.
 */

use Infrastructure\Legacy\PartStockRow;
use Infrastructure\Legacy\PickingService;
use Infrastructure\Legacy\ReservationService;
use Infrastructure\Legacy\StockReportService;
use Infrastructure\Legacy\StockReservations;
use Infrastructure\ProblemDetailsMapper;
use Workshop\InsufficientStock;
use Workshop\InvalidQuantity;
use Workshop\InvalidWorkshopInput;
use Workshop\PartNumber;
use Workshop\PartStock;
use Workshop\WorkshopException;
use Workshop\WorkshopFailure;

acceptance('Lesson 5.2 — Strangling a legacy model');


// ═══════════════════════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════════════════════

const PART = 'K9K-1234';

function aRow(int $onHand = 10, int $reserved = 4): PartStockRow
{
    return PartStockRow::make(PART, $onHand, $reserved);
}

function aStock(int $onHand = 10, int $reserved = 4): PartStock
{
    return PartStock::of(PartNumber::fromString(PART), $onHand, $reserved);
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

/**
 * The outcome of an operation, as a comparable value: either the resulting
 * columns, or the word "refused".
 *
 * @return array{on_hand:int, reserved:int}|string
 */
function outcome(callable $operation, int $onHand, int $reserved): array|string
{
    $row = aRow($onHand, $reserved);

    if (captured(static fn() => $operation($row)) !== null) {
        return 'refused';
    }

    return ['on_hand' => (int) $row->on_hand, 'reserved' => (int) $row->reserved];
}


// ═══════════════════════════════════════════════════════════════════════════
// 1 — The aggregate is in the domain, and knows nothing about a row
// ═══════════════════════════════════════════════════════════════════════════

check('Workshop\PartStock exists, in domain/workshop/',
    fn() => class_exists(PartStock::class) && declared_in(PartStock::class, 'domain/workshop'));

check('It imports nothing from Infrastructure',
    fn() => !str_contains(class_source_without_comments(PartStock::class), 'Infrastructure'));

check('It has no save(), no id, no ORM base class',
    fn() => get_parent_class(PartStock::class) === false
        && !method_exists(PartStock::class, 'save')
        && !method_exists(PartStock::class, 'find'));

check('It cannot be constructed publicly — only through of()',
    fn() => !(new ReflectionMethod(PartStock::class, '__construct'))->isPublic());

check('available() is onHand minus reserved',
    fn() => aStock(10, 4)->available() === 6);


// ═══════════════════════════════════════════════════════════════════════════
// 2 — The invariant now has a home
// ═══════════════════════════════════════════════════════════════════════════

check('Reserving more than is available is refused',
    fn() => throws(InsufficientStock::class, static fn() => aStock(10, 4)->reserve(7)));

check('Reserving exactly what is available is allowed',
    function (): bool {
        $stock = aStock(10, 4);
        $stock->reserve(6);

        return $stock->reserved() === 10 && $stock->available() === 0;
    });

check('Releasing more than is reserved is refused',
    fn() => throws(InsufficientStock::class, static fn() => aStock(10, 4)->release(5)));

check('Consuming more than is reserved is refused',
    fn() => throws(InsufficientStock::class, static fn() => aStock(10, 4)->consume(5)));

check('Consuming moves BOTH counts, together',
    function (): bool {
        $stock = aStock(10, 4);
        $stock->consume(3);

        return $stock->onHand() === 7 && $stock->reserved() === 1;
    });

check('A non-positive quantity is refused, on every operation',
    fn() => throws(InvalidQuantity::class, static fn() => aStock()->reserve(0))
        && throws(InvalidQuantity::class, static fn() => aStock()->reserve(-5))
        && throws(InvalidQuantity::class, static fn() => aStock()->release(-1))
        && throws(InvalidQuantity::class, static fn() => aStock()->consume(0))
        && throws(InvalidQuantity::class, static fn() => aStock()->receive(-2)));

check('No sequence of legal operations can break the invariant',
    function (): bool {
        $stock = aStock(10, 0);

        foreach (['reserve' => 4, 'consume' => 2, 'release' => 2, 'receive' => 5] as $method => $qty) {
            $stock->{$method}($qty);

            if (!$stock->isConsistent() || $stock->onHand() < 0 || $stock->reserved() < 0) {
                return false;
            }
        }

        return $stock->onHand() === 13 && $stock->reserved() === 0;
    });


// ═══════════════════════════════════════════════════════════════════════════
// 3 — It loads dirty data, because legacy data is dirty
// ═══════════════════════════════════════════════════════════════════════════

check('An inconsistent row still LOADS — reserved above on hand',
    function (): bool {
        $stock = PartStock::of(PartNumber::fromString(PART), 3, 9);

        return $stock->onHand() === 3 && $stock->reserved() === 9;
    });

check('...and isConsistent() reports it rather than throwing',
    fn() => PartStock::of(PartNumber::fromString(PART), 3, 9)->isConsistent() === false
        && aStock(10, 4)->isConsistent() === true);

check('isConsistent() carries #[\NoDiscard] — the point of asking is the answer',
    fn() => in_array(
        'NoDiscard',
        array_map(
            static fn(ReflectionAttribute $a): string => $a->getName(),
            (new ReflectionMethod(PartStock::class, 'isConsistent'))->getAttributes()
        ),
        true
    ));

check('An IMPOSSIBLE row is still refused — a negative count is a bug, not dirt',
    fn() => throws(InvalidQuantity::class,
        static fn() => PartStock::of(PartNumber::fromString(PART), -4, 0)));

check('The rules still apply to what happens next, on a dirty row',
    function (): bool {
        $stock = PartStock::of(PartNumber::fromString(PART), 3, 9);

        // available() is negative here. Reserving anything at all must refuse.
        return throws(InsufficientStock::class, static fn() => $stock->reserve(1));
    });


// ═══════════════════════════════════════════════════════════════════════════
// 4 — THE CHARACTERISATION TEST
// ═══════════════════════════════════════════════════════════════════════════
//
// The old path and the new path, over the same inputs. Where the legacy
// service was right, they must agree exactly. Where it was wrong, they must
// differ — and the three cases are named, because "we found some bugs" is not
// a decision and "we changed behaviour here, here and here" is.

function legacyReserve(int $qty): callable
{
    return static fn(PartStockRow $row) => (new ReservationService())->reserve($row, $qty);
}

function newReserve(int $qty): callable
{
    return static fn(PartStockRow $row) => (new StockReservations())->reserve($row, $qty);
}

function legacyRelease(int $qty): callable
{
    return static fn(PartStockRow $row) => (new ReservationService())->release($row, $qty);
}

function newRelease(int $qty): callable
{
    return static fn(PartStockRow $row) => (new StockReservations())->release($row, $qty);
}

/** Inputs where the legacy service was correct and the two must agree. */
function agreeingCases(): array
{
    return [
        'reserve 1 of 6 free'    => [legacyReserve(1), newReserve(1), 10, 4],
        'reserve 6 of 6 free'    => [legacyReserve(6), newReserve(6), 10, 4],
        'reserve 7 of 6 free'    => [legacyReserve(7), newReserve(7), 10, 4],
        'reserve 1 of 0 free'    => [legacyReserve(1), newReserve(1), 4, 4],
        'release 1 of 4 held'    => [legacyRelease(1), newRelease(1), 10, 4],
        'release 4 of 4 held'    => [legacyRelease(4), newRelease(4), 10, 4],
        'reserve 3 on empty row' => [legacyReserve(3), newReserve(3), 0, 0],
    ];
}

/** Inputs where the legacy service was WRONG. Named, deliberately. */
function divergingCases(): array
{
    return [
        'reserve -5'          => [legacyReserve(-5), newReserve(-5), 10, 4],
        'reserve 0'           => [legacyReserve(0), newReserve(0), 10, 4],
        'release 9 of 4 held' => [legacyRelease(9), newRelease(9), 10, 4],
    ];
}

check('Old and new agree on every input where the old one was right',
    function (): bool {
        foreach (agreeingCases() as $label => [$old, $new, $onHand, $reserved]) {
            if (outcome($old, $onHand, $reserved) !== outcome($new, $onHand, $reserved)) {
                throw new RuntimeException(sprintf(
                    '%s: legacy %s, new %s',
                    $label,
                    json_encode(outcome($old, $onHand, $reserved)),
                    json_encode(outcome($new, $onHand, $reserved))
                ));
            }
        }

        return true;
    });

check('...including the refusals, not just the successes',
    fn() => outcome(legacyReserve(7), 10, 4) === 'refused'
        && outcome(newReserve(7), 10, 4) === 'refused');

check('They DIVERGE on all three cases where the old one was wrong',
    function (): bool {
        foreach (divergingCases() as $label => [$old, $new, $onHand, $reserved]) {
            if (outcome($old, $onHand, $reserved) === outcome($new, $onHand, $reserved)) {
                throw new RuntimeException($label . ': expected a difference, found none');
            }
        }

        return true;
    });

check('Legacy reserve(-5) silently REDUCED the reservation',
    fn() => outcome(legacyReserve(-5), 10, 4) === ['on_hand' => 10, 'reserved' => -1]);

check('...and the new path refuses it',
    fn() => outcome(newReserve(-5), 10, 4) === 'refused');

check('Legacy release(9) drove the column NEGATIVE',
    fn() => outcome(legacyRelease(9), 10, 4) === ['on_hand' => 10, 'reserved' => -5]);

check('...and the new path refuses it',
    fn() => outcome(newRelease(9), 10, 4) === 'refused');

check('The new path is a drop-in: identical signatures',
    function (): bool {
        $signature = static function (string $class, string $method): string {
            return implode(',', array_map(
                static fn(ReflectionParameter $p): string => (string) $p->getType() . ' $' . $p->getName(),
                (new ReflectionMethod($class, $method))->getParameters()
            ));
        };

        foreach (['reserve', 'release'] as $method) {
            if ($signature(ReservationService::class, $method) !== $signature(StockReservations::class, $method)) {
                return false;
            }
        }

        return true;
    });


// ═══════════════════════════════════════════════════════════════════════════
// 5 — The new leaves fit Module 4's tree
// ═══════════════════════════════════════════════════════════════════════════

check('InsufficientStock is a WorkshopException — a fact about the world',
    fn() => is_a(InsufficientStock::class, WorkshopException::class, true)
        && (new ReflectionClass(InsufficientStock::class))->isFinal());

check('InvalidQuantity is an InvalidWorkshopInput — a fact about the request',
    fn() => is_a(InvalidQuantity::class, InvalidWorkshopInput::class, true)
        && (new ReflectionClass(InvalidQuantity::class))->isFinal());

check('Both are WorkshopFailures, so the boundary catches them',
    fn() => aStockFailure() instanceof WorkshopFailure
        && InvalidQuantity::mustBePositive(-1) instanceof WorkshopFailure);

function aStockFailure(): InsufficientStock
{
    return InsufficientStock::forPart(PartNumber::fromString(PART), 7, 6);
}

check('No StockException node was added for a single class',
    fn() => get_parent_class(InsufficientStock::class) === WorkshopException::class);

check('Their failure codes are kebab-case and carry no class name',
    fn() => aStockFailure()->failureCode() === 'insufficient-stock'
        && InvalidQuantity::mustBePositive(-1)->failureCode() === 'invalid-quantity');

check('InsufficientStock carries what a caller needs to act',
    function (): bool {
        $context = aStockFailure()->context();

        return $context['requested'] === 7
            && $context['available'] === 6
            && $context['short'] === 1
            && $context['partNumber'] === PART;
    });

check('Both contexts are JSON-safe',
    fn() => json_encode(aStockFailure()->context()) !== false
        && json_encode(InvalidQuantity::mustBePositive(-1)->context()) !== false);

check('The mapper gained an entry: insufficient-stock is a 409',
    fn() => (new ProblemDetailsMapper())->map(aStockFailure())->status === 409);

check('...and invalid-quantity is a 422 — the branch decides, as it has since 4.2',
    fn() => (new ProblemDetailsMapper())->map(InvalidQuantity::mustBePositive(-1))->status === 422);

check('Neither becomes an opaque 500 — Lesson 4.4\'s exhaustiveness check earning its keep',
    fn() => (new ProblemDetailsMapper())->map(aStockFailure())->status !== 500
        && (new ProblemDetailsMapper())->map(InvalidQuantity::mustBePositive(-1))->status !== 500);

check('The failure survives the adapter as the same object',
    function (): bool {
        $row     = aRow(10, 4);
        $thrown  = captured(static fn() => (new StockReservations())->reserve($row, 7));

        return $thrown instanceof InsufficientStock
            && $thrown->partNumber->value() === PART;
    });


// ═══════════════════════════════════════════════════════════════════════════
// 6 — EXACTLY ONE AGGREGATE. The other two services are untouched.
// ═══════════════════════════════════════════════════════════════════════════
//
// A strangler that grows to three services in its first week is a rewrite that
// has not admitted it yet.

check('PickingService still exists and still works, unchanged',
    function (): bool {
        $row = aRow(10, 4);
        (new PickingService())->fit($row, 3);

        return (int) $row->on_hand === 7 && (int) $row->reserved === 1;
    });

check('...and can STILL break the invariant, which is honest rather than fixed',
    function (): bool {
        $row = aRow(2, 1);
        (new PickingService())->fit($row, 5);

        // The old path is still the old path. Documenting that is the point:
        // the extraction is correct, and the codebase is not finished.
        return (int) $row->on_hand === -3;
    });

check('StockReportService still exists and still works, unchanged',
    function (): bool {
        $row = aRow(10, 4);

        return (new StockReportService())->available($row) === 6
            && (new StockReportService())->isLow($row) === false;
    });

check('The two untouched services still use the ROW directly, not the aggregate',
    fn() => !mentionsAggregate(PickingService::class)
        && !mentionsAggregate(StockReportService::class));

/**
 * Does this class name the AGGREGATE — as opposed to the row?
 *
 * The word boundary is load-bearing, and the first version of this check did
 * not have one. `PickingService::fit(PartStockRow $row)` contains the substring
 * `PartStock`, so a plain `str_contains()` reported that an untouched legacy
 * service had been converted to use the aggregate. It failed its own reference
 * solution, which is how it was found.
 *
 * `\b` after `PartStock` requires a non-word character next. `PartStockRow` has
 * `R` there, so it does not match; `use Workshop\PartStock;` has `;`, so it
 * does.
 *
 * THIS IS THE THIRD TIME THIS COURSE HAS BEEN BITTEN BY A SOURCE GREP that
 * cannot tell two things apart — Lesson 3.1's could not distinguish a
 * dependency from a sentence about one, and `no_float_in()` had already solved
 * the same problem a module earlier without anyone reusing it.
 *
 * The general rule, and it is worth more than this function: a check that reads
 * source is a parser with the parsing left out. If it is going to make a
 * decision, give it enough context to make it correctly — comments stripped,
 * identifiers bounded — or it will one day fail something that is right.
 */
function mentionsAggregate(string $class): bool
{
    return preg_match('/\bPartStock\b/', class_source_without_comments($class)) === 1;
}

check('Only ONE new aggregate was extracted',
    function (): bool {
        // A solution that also extracted picking or reporting has answered a
        // different question. Neither service names the domain, and no second
        // aggregate class appears in the domain directory for this lesson.
        return !class_exists('Workshop\PartPicking')
            && !class_exists('Workshop\StockReport')
            && !class_exists('Workshop\Stock');
    });

check('The strangler is in infrastructure/, not the domain',
    fn() => class_exists(StockReservations::class)
        && !str_contains(fileOf(StockReservations::class), '/domain/workshop/'));

check('The adapter writes both columns back, and saves nothing',
    function (): bool {
        $row = aRow(10, 4);
        (new StockReservations())->reserve($row, 2);

        return (int) $row->reserved === 6
            && (int) $row->on_hand === 10
            && !str_contains(class_source_without_comments(StockReservations::class), '->save(');
    });

check('The columns stay scalar — the legacy schema did not move',
    function (): bool {
        $row = aRow(10, 4);
        (new StockReservations())->reserve($row, 2);

        $stored = $row->toRow();

        return is_int($stored['on_hand']) && is_int($stored['reserved']);
    });


acceptance_report();
