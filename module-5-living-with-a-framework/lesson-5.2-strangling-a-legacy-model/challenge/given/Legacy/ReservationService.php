<?php
declare(strict_types=1);

namespace Infrastructure\Legacy;

/**
 * Reserving and releasing stock. SUPPLIED BY THE COURSE — the "before".
 *
 * This is the service that half-enforces the invariant, and it is the one you
 * are going to strangle. Read what it does and, more importantly, what it does
 * not.
 *
 * WHAT IT GETS RIGHT
 * -------------------
 * `reserve()` refuses to promise more than is available. Somebody thought
 * about it, once, and it has been correct ever since.
 *
 * WHAT IT DOES NOT CHECK, AND YOU WILL FIND THIS BY RUNNING IT
 * -------------------------------------------------------------
 *   - The quantity is never checked for sign. `reserve($row, -5)` REDUCES the
 *     reservation and passes the guard, because -5 is comfortably less than
 *     what is available.
 *   - `release()` has no floor. Release more than is reserved and the column
 *     goes negative, silently, and the next `reserve()` on that row is
 *     computed against a lie.
 *
 * Neither is exotic. Both are what happens when a rule lives in a procedure
 * that was written to handle the case somebody was thinking about at the time.
 *
 * DO NOT ASSUME THEY ARE BUGS WORTH FIXING UNTIL YOU HAVE LOOKED. That is the
 * lesson in 5.2 section 5, and it is the part people skip.
 */
final class ReservationService
{
    public function reserve(PartStockRow $row, int $quantity): void
    {
        $available = (int) $row->on_hand - (int) $row->reserved;

        if ($quantity > $available) {
            throw new \DomainException(sprintf(
                'Only %d of %s available.',
                $available,
                (string) $row->part_number
            ));
        }

        $row->reserved = (int) $row->reserved + $quantity;
    }

    public function release(PartStockRow $row, int $quantity): void
    {
        $row->reserved = (int) $row->reserved - $quantity;
    }
}
