<?php
declare(strict_types=1);

namespace Workshop;

/**
 * A job card with this identity is already in the collection.
 *
 * REFERENCE IMPLEMENTATION — re-parented in Lesson 4.2.
 *
 * Created in 4.1 because `GuardedJobCards` translated the anonymous
 * `\DomainException` behind it into a storage failure. It slots into the tree
 * without argument: it is about one job card, so it belongs under
 * `JobCardException`, and the identity it was carrying by hand now comes from
 * the parent.
 *
 * A NOTE ON THE CODE STRING
 * --------------------------
 * `job-card-already-exists` rather than `duplicate-job-card`. Both are fine;
 * the rule that matters is that it never changes again once a client has read
 * it. Lesson 4.4 maps it to 409 Conflict, which is the status code that exists
 * for precisely this.
 */
final class JobCardAlreadyExists extends JobCardException
{
    public static function withId(JobCardId $id): self
    {
        return new self($id, sprintf(
            'Job card %s is already in this collection. To replace it, remove it first.',
            $id
        ));
    }

    public function failureCode(): string
    {
        return 'job-card-already-exists';
    }
}
