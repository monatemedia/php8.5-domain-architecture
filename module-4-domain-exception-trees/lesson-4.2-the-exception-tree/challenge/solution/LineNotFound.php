<?php
declare(strict_types=1);

namespace Workshop;

/**
 * Somebody tried to remove a line that is not on this job card.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * TWO IDENTITIES, AND THE SECOND ONE IS NOT ON THE PARENT
 * --------------------------------------------------------
 * `jobCardId` comes from `JobCardException`. `lineId` is declared here, because
 * this is the only failure in the tree that has one.
 *
 * That is the rule for where a field goes, and it is the same rule as for
 * whether a node exists: hoist it when more than one class needs it, and not
 * before. A `LineException` node holding `lineId` would be a node with one
 * child, which the `JobCardException` doc block argues against.
 *
 * WHY IT IS NOT AN \OutOfBoundsException
 * ---------------------------------------
 * SPL has one, and it reads as though it were meant for exactly this. It sits
 * under `\RuntimeException`, which would put a business rule in the branch
 * Lesson 4.1 reserved for the machinery falling over — and, more to the point,
 * outside `WorkshopFailure` unless you add the marker to it by hand.
 *
 * A line that is not there is a fact about the job card, and it belongs in the
 * tree that models the job card.
 */
final class LineNotFound extends JobCardException
{
    public private(set) LineId $lineId;

    public static function on(JobCardId $jobCardId, LineId $lineId): self
    {
        $exception = new self(
            $jobCardId,
            sprintf('Job card %s has no line %s.', $jobCardId, $lineId),
            ['lineId' => $lineId->value()]
        );

        $exception->lineId = $lineId;

        return $exception;
    }

    public function failureCode(): string
    {
        return 'line-not-found';
    }
}
