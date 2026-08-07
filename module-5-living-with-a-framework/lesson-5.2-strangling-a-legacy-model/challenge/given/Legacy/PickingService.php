<?php
declare(strict_types=1);

namespace Infrastructure\Legacy;

/**
 * Fitting parts to a job card. SUPPLIED BY THE COURSE.
 *
 * YOU ARE NOT STRANGLING THIS ONE, and leaving it alone is an acceptance
 * criterion rather than an oversight.
 *
 * It enforces nothing at all. `fit()` will drive `on_hand` negative without
 * comment, which means the shelf now holds minus two alternators and every
 * availability calculation downstream is wrong.
 *
 * That is worse than anything in ReservationService, and it is still not what
 * you are fixing today.
 *
 * WHY NOT
 * --------
 * Because a strangler that grows to three services in its first week is a
 * rewrite that has not admitted it yet. You extract ONE aggregate, prove the
 * new path matches the old one where the old one was right, ship it, and stop.
 *
 * The reward for stopping is that this file becomes trivially easy to convert
 * later — `PartStock::consume()` already exists and already refuses. Somebody
 * changes four lines in an afternoon, with the characterisation tests you wrote
 * this week to lean on.
 *
 * The punishment for not stopping is a six-week branch.
 */
final class PickingService
{
    public function fit(PartStockRow $row, int $quantity): void
    {
        $row->on_hand  = (int) $row->on_hand - $quantity;
        $row->reserved = (int) $row->reserved - $quantity;
    }
}
