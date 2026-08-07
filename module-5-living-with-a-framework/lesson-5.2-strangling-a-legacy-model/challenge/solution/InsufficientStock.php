<?php
declare(strict_types=1);

namespace Workshop;

/**
 * There is not enough of a part to do what was asked.
 *
 * REFERENCE IMPLEMENTATION. The tenth leaf of Module 4's tree, added a module
 * later by somebody working on something else entirely — which is exactly the
 * case Lesson 4.4 predicted when it wrote an exhaustiveness check.
 *
 * WHERE IT HANGS, AND WHAT IS NOT BEING ADDED
 * --------------------------------------------
 * `WorkshopException` directly. Not under `JobCardException`, because this is
 * not about a job card — a stock shortage is a fact about a shelf, and it is
 * raised while nobody is holding a job card at all.
 *
 * And NO `StockException` node between them. Lesson 4.2's rule: name the catch
 * clause that would use the node and the field it holds. With one class below
 * it there is nothing to hoist and nobody writes `catch (StockException)` when
 * catching this is more precise.
 *
 * If `PickingService` is strangled later and brings a second stock failure with
 * it, the node earns its place then. That is a two-minute change at that point.
 *
 * WHY IT IS A WorkshopException AND NOT AN InvalidWorkshopInput
 * --------------------------------------------------------------
 * The test from 4.2: could the caller have known this was invalid without
 * asking the domain? No. Whether six alternators are enough depends on how many
 * are on the shelf and how many are already promised, and only the stock knows
 * that.
 *
 * Compare `InvalidQuantity`, which sits on the other branch: asking to reserve
 * minus five is wrong in the controller, in the form, and in the caller's own
 * variable.
 *
 * The consequence at the boundary is 409 versus 422 — "fix the world" versus
 * "fix your request" — and a client that retries automatically needs the
 * difference.
 */
final class InsufficientStock extends WorkshopException
{
    public private(set) PartNumber $partNumber;

    public static function forPart(
        PartNumber $partNumber,
        int $requested,
        int $available,
    ): self {
        $exception = new self(
            sprintf(
                'Only %d of part %s available; %d were asked for.',
                $available,
                $partNumber,
                $requested
            ),
            [
                'partNumber' => $partNumber->value(),
                'requested'  => $requested,
                'available'  => $available,
                'short'      => $requested - $available,
            ]
        );

        $exception->partNumber = $partNumber;

        return $exception;
    }

    public function failureCode(): string
    {
        return 'insufficient-stock';
    }
}
