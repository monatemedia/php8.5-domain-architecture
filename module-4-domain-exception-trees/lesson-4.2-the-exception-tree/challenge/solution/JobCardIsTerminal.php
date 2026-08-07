<?php
declare(strict_types=1);

namespace Workshop;

/**
 * INVARIANT I6: no line may be added to or removed from a job card in a
 * terminal state.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * WHY THIS IS NOT IllegalStatusTransition
 * ----------------------------------------
 * Both mean "the status forbids this", and merging them is a real option worth
 * rejecting explicitly rather than by omission.
 *
 * They are different failures because they are about different things. An
 * illegal transition is about the LIFECYCLE: you asked for a state and the
 * table says no. This is about the CONTENTS: the lifecycle is not moving at
 * all, and the lines are frozen because the job card is finished.
 *
 * The practical test is the one from Lesson 4.1: read them aloud.
 *
 *     "You cannot move that job card from Invoiced to In Progress."
 *     "You cannot add parts to that job card — it was invoiced last Tuesday."
 *
 * Those are two sentences a service manager would say on two different
 * occasions. Two failures.
 *
 * The context carries the operation, so a boundary can render the second
 * sentence without a lookup table of its own.
 */
final class JobCardIsTerminal extends JobCardException
{
    /**
     * @param string $operation what was being attempted, in the workshop's
     *                          words — 'add a line to', 'remove a line from'.
     *                          Not the method name: `addLine` is this
     *                          codebase's vocabulary, not the business's.
     */
    public static function cannot(JobCardId $id, string $operation, JobCardStatus $status): self
    {
        return new self(
            $id,
            sprintf(
                'Cannot %s job card %s: it is %s, and its lines are fixed.',
                $operation,
                $id,
                $status->label()
            ),
            [
                'operation' => $operation,
                'status'    => $status->value,
            ]
        );
    }

    public function failureCode(): string
    {
        return 'job-card-is-terminal';
    }
}
