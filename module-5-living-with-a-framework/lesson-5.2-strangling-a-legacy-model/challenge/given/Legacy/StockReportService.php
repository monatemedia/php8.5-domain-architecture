<?php
declare(strict_types=1);

namespace Infrastructure\Legacy;

/**
 * Reading stock levels for a screen. SUPPLIED BY THE COURSE.
 *
 * ALSO NOT BEING STRANGLED, and for a different reason than PickingService.
 *
 * This one is a READ MODEL wearing a service's name, and Lesson 3.3 already
 * settled what to do with those: nothing. It decides no rules, protects no
 * invariants, and every method is a subtraction. Routing it through an
 * aggregate would load an object in order to do arithmetic that is already
 * correct.
 *
 * Worth noticing how it duplicates `available()`. So does ReservationService,
 * in a local variable. So will your aggregate. Three copies of one subtraction
 * is not the duplication worth chasing — the rule that has to be UNAVOIDABLE
 * is the one about reserving beyond it, and that lives in exactly one place
 * when you are done.
 */
final class StockReportService
{
    public function available(PartStockRow $row): int
    {
        return (int) $row->on_hand - (int) $row->reserved;
    }

    public function isLow(PartStockRow $row, int $threshold = 2): bool
    {
        return $this->available($row) <= $threshold;
    }
}
