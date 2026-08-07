<?php
declare(strict_types=1);

namespace Workshop;

/**
 * The stock of one part.
 *
 * REFERENCE IMPLEMENTATION. The one aggregate extracted in Lesson 5.2, and the
 * whole exercise is that it is ONE.
 *
 * THE INVARIANT NOBODY HAD WRITTEN DOWN
 * --------------------------------------
 *     0 <= reserved <= onHand
 *
 * You cannot promise the same alternator to two job cards, and a shelf cannot
 * hold minus four of anything. Both halves were enforced in half a place: the
 * legacy `ReservationService` checked the upper bound on `reserve()` and
 * nothing else, `PickingService` checked neither, and `StockReportService`
 * merely subtracted.
 *
 * That is what an invariant looks like before it has a home, and it is why
 * this class is the right first extraction: the rule spans two fields, has to
 * be true at every instant, and there is exactly one place a change to either
 * field can come from once this exists.
 *
 * WHY STOCK AND NOT SOMETHING ELSE
 * ---------------------------------
 * Section 3 of the README gives the rule — start at the invariant that keeps
 * biting you. This one bites: the symptom is a job card promised parts that are
 * not there, discovered by a technician at the shelf, and it is a phone call to
 * a customer rather than a log line.
 *
 * Do not start at the most interesting aggregate. Start at the one generating
 * incidents, because that is the one somebody will let you spend a week on.
 *
 * IT KNOWS NOTHING ABOUT A ROW
 * -----------------------------
 * No `save()`, no columns, no ORM base class, no `id`. `Infrastructure\Legacy\
 * StockReservations` is the anti-corruption layer that reads a `PartStockRow`
 * into this and writes the result back, and this class has never heard of it.
 *
 * The arrow points inward, as it has since Lesson 3.1 — the difference being
 * that on the other side of it now sits an Active Record model somebody wrote
 * in 2019, rather than a repository you designed.
 */
final class PartStock
{
    private function __construct(
        private readonly PartNumber $partNumber,
        private int $onHand,
        private int $reserved,
    ) {}

    /**
     * Rebuild from whatever the legacy row says, WITHOUT enforcing the
     * invariant.
     *
     * This is Lesson 3.2's reconstitution argument arriving somewhere much less
     * comfortable. There, the rows had been written by code that already
     * enforced the rules. Here they have not, and some of them are wrong.
     *
     * A factory that refused an inconsistent row would be correct in principle
     * and undeployable in practice: the first `reserved > on_hand` row in
     * production takes down the screen that was going to show you how many
     * there are. Legacy data is not clean, and an extraction that cannot load
     * the data is not an extraction.
     *
     * So: loading is not a change, the rules govern changes, and
     * `isConsistent()` below is how you find out what you have.
     *
     * @throws InvalidQuantity either count is negative — that is not
     *         inconsistent data, it is impossible data, and a row saying minus
     *         four are on the shelf is a bug to be found rather than tolerated
     */
    public static function of(PartNumber $partNumber, int $onHand, int $reserved): self
    {
        if ($onHand < 0) {
            throw InvalidQuantity::mustBePositive($onHand);
        }

        if ($reserved < 0) {
            throw InvalidQuantity::mustBePositive($reserved);
        }

        return new self($partNumber, $onHand, $reserved);
    }

    /**
     * Does this stock satisfy its own invariant?
     *
     * NOT a guard — nothing calls it before doing anything. It is a REPORT, and
     * it exists because the first question anybody asks about a strangler is
     * "how much bad data is there?"
     *
     * Run it across the table before you change a single call site. If the
     * answer is eleven rows, fix them and carry on. If it is forty thousand,
     * the invariant you were about to enforce is not the invariant the business
     * has been operating under, and you need a different conversation before you
     * write any more code.
     *
     * That is worth more than it looks. Most failed extractions fail here, and
     * they fail late — after the new path is written, when it starts throwing on
     * data that has been fine for six years.
     */
    #[\NoDiscard('The point of asking is the answer')]
    public function isConsistent(): bool
    {
        return $this->reserved <= $this->onHand;
    }

    // ── Reading ─────────────────────────────────────────────────────────────

    public function partNumber(): PartNumber
    {
        return $this->partNumber;
    }

    public function onHand(): int
    {
        return $this->onHand;
    }

    public function reserved(): int
    {
        return $this->reserved;
    }

    /**
     * The third copy of this subtraction in the codebase, and that is fine.
     *
     * `StockReportService` has one, the legacy `ReservationService` has one in
     * a local variable, and here is a third. Duplicated arithmetic is not the
     * duplication worth chasing; the rule about not reserving beyond it is, and
     * after this class that rule exists exactly once.
     */
    public function available(): int
    {
        return $this->onHand - $this->reserved;
    }

    // ── Changing ────────────────────────────────────────────────────────────

    /**
     * Promise some of this part to a job card.
     *
     * @throws InvalidQuantity     the quantity is not positive
     * @throws InsufficientStock   there are not that many free
     */
    public function reserve(int $quantity): void
    {
        $this->assertPositive($quantity);

        if ($quantity > $this->available()) {
            throw InsufficientStock::forPart($this->partNumber, $quantity, $this->available());
        }

        $this->reserved += $quantity;
    }

    /**
     * Give some back, unpromised.
     *
     * THE FLOOR IS THE FIX. The legacy version was `$row->reserved -= $qty` with
     * nothing below it, so releasing more than was reserved drove the column
     * negative and every later calculation on that row was computed against a
     * lie.
     *
     * Note which exception this raises. Releasing eight when six are reserved
     * is not an input problem — eight is a perfectly good number — it is a
     * problem with the world. `InsufficientStock`, and a 409.
     *
     * @throws InvalidQuantity
     * @throws InsufficientStock more was released than was ever reserved
     */
    public function release(int $quantity): void
    {
        $this->assertPositive($quantity);

        if ($quantity > $this->reserved) {
            throw InsufficientStock::forPart($this->partNumber, $quantity, $this->reserved);
        }

        $this->reserved -= $quantity;
    }

    /**
     * Fit the parts to the vehicle. They leave the shelf and stop being
     * promised at the same instant.
     *
     * BOTH FIELDS MOVE TOGETHER, which is the argument for this being an
     * aggregate rather than two numbers. There is no moment at which the parts
     * have been fitted but are still reserved, and no way for a caller to do
     * one without the other.
     *
     * `PickingService::fit()` does exactly this arithmetic with no guard at all,
     * and it is still in the codebase after this lesson. That is deliberate:
     * see the README on why a strangler that grows to three services in its
     * first week is a rewrite that has not admitted it yet.
     *
     * When somebody does convert it, this method is already here and already
     * correct.
     *
     * @throws InvalidQuantity
     * @throws InsufficientStock more was fitted than was reserved
     */
    public function consume(int $quantity): void
    {
        $this->assertPositive($quantity);

        if ($quantity > $this->reserved) {
            throw InsufficientStock::forPart($this->partNumber, $quantity, $this->reserved);
        }

        $this->onHand   -= $quantity;
        $this->reserved -= $quantity;
    }

    /**
     * Take delivery.
     *
     * @throws InvalidQuantity
     */
    public function receive(int $quantity): void
    {
        $this->assertPositive($quantity);

        $this->onHand += $quantity;
    }

    /**
     * @throws InvalidQuantity
     */
    private function assertPositive(int $quantity): void
    {
        if ($quantity <= 0) {
            throw InvalidQuantity::mustBePositive($quantity);
        }
    }
}
