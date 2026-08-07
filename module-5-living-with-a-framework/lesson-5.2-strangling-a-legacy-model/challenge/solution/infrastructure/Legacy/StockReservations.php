<?php
declare(strict_types=1);

namespace Infrastructure\Legacy;

use Workshop\PartNumber;
use Workshop\PartStock;

/**
 * The strangler: the same two methods, routed through the aggregate.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * IT HAS THE SAME SHAPE AS THE THING IT REPLACES, ON PURPOSE
 * -----------------------------------------------------------
 *     ReservationService::reserve(PartStockRow $row, int $quantity): void
 *     StockReservations::reserve(PartStockRow $row, int $quantity): void
 *
 * Identical signatures, so a call site changes by one word — the type it asks
 * the container for — and nothing else. No new arguments, no return value to
 * handle, no `Money` where an `int` used to be.
 *
 * That is the single most important property of a strangler and the one most
 * often given up first. The moment the new method needs different arguments,
 * every call site has to change in the same pull request, and a two-day
 * extraction becomes a six-week branch that gets abandoned.
 *
 * Improve the signature LATER, when the call sites are yours. `InvalidQuantity`
 * says the same thing about not introducing a `Quantity` value object yet.
 *
 * IT IS AN ANTI-CORRUPTION LAYER, WHICH IS A GRAND NAME FOR TWO METHODS
 * ---------------------------------------------------------------------
 * `toAggregate()` reads a row into a domain object. `apply()` writes the result
 * back. Between them the domain object is the only thing that decides anything,
 * and neither the row nor the ORM appears in `Workshop\`.
 *
 * It is Lesson 3.2's mapper, smaller, and pointed at a legacy table instead of
 * at a schema you designed. The costs are the same ones 3.2 listed and so is
 * the benefit.
 *
 * IT IS IN infrastructure/, AND SO IS EVERYTHING IT TOUCHES EXCEPT THE DOMAIN
 * ----------------------------------------------------------------------------
 * The row, the ORM base, the two services it does not replace. The only things
 * from `Workshop\` are `PartStock` and `PartNumber`, both imported, and the
 * exceptions they raise travel out through here untouched — Lesson 4.1's rule
 * about a domain failure passing through a boundary as the same object.
 */
final class StockReservations
{
    /**
     * @throws \Workshop\InvalidQuantity
     * @throws \Workshop\InsufficientStock
     */
    public function reserve(PartStockRow $row, int $quantity): void
    {
        $stock = $this->toAggregate($row);

        $stock->reserve($quantity);

        $this->apply($stock, $row);
    }

    /**
     * @throws \Workshop\InvalidQuantity
     * @throws \Workshop\InsufficientStock
     */
    public function release(PartStockRow $row, int $quantity): void
    {
        $stock = $this->toAggregate($row);

        $stock->release($quantity);

        $this->apply($stock, $row);
    }

    /**
     * The data survey, and the first thing to run.
     *
     * Before changing a single call site, walk the table with this and count.
     * Eleven bad rows is an afternoon; forty thousand means the invariant you
     * were about to enforce is not the one the business has been operating
     * under, and you need a different conversation before writing more code.
     *
     * Most failed extractions fail exactly here, and they fail LATE — after the
     * new path is written, when it starts throwing on data that has been fine
     * for six years.
     */
    #[\NoDiscard('The point of asking is the answer')]
    public function isConsistent(PartStockRow $row): bool
    {
        return $this->toAggregate($row)->isConsistent();
    }

    /**
     * Row in, aggregate out.
     *
     * NOTE WHAT IT DOES NOT DO: it does not validate the invariant. `PartStock::
     * of()` deliberately loads an inconsistent row, because legacy data is not
     * clean and an extraction that cannot load the data is not an extraction.
     *
     * The rules apply to what happens NEXT.
     */
    private function toAggregate(PartStockRow $row): PartStock
    {
        return PartStock::of(
            PartNumber::fromString((string) $row->part_number),
            (int) $row->on_hand,
            (int) $row->reserved
        );
    }

    /**
     * Aggregate back onto the row.
     *
     * Two columns, written together, and the row is not saved here — the caller
     * owns the transaction exactly as it did before this class existed. A
     * strangler that starts committing on its own is a strangler that has
     * changed the caller's contract without saying so.
     */
    private function apply(PartStock $stock, PartStockRow $row): void
    {
        $row->on_hand  = $stock->onHand();
        $row->reserved = $stock->reserved();
    }
}
