<?php
declare(strict_types=1);

namespace Workshop;

/**
 * A job card with this identity is already in the collection.
 *
 * REFERENCE IMPLEMENTATION. New in Lesson 4.1, and it exists because the
 * decorator found it.
 *
 * HOW THIS CLASS GOT WRITTEN
 * ---------------------------
 * `GuardedJobCards` enforces one rule: a WorkshopFailure passes through, and
 * anything else becomes a StorageFailure. Wrap `InMemoryJobCards` in it, add
 * the same job card twice, and Lesson 3.1's duplicate guard came out the far
 * side as a **storage failure**.
 *
 * The database was fine. The rule that fired is as domain-level as they come —
 * *"a collection does not silently replace"* — and it was thrown like this:
 *
 *     throw new \DomainException(sprintf(
 *         'Job card %s is already in this collection. ...',
 *         $jobCard->id()
 *     ));
 *
 * A bare `\DomainException`. It carries no marker, so it is indistinguishable
 * from any other \DomainException in any dependency, and the decorator was
 * right to be suspicious of it.
 *
 * THAT IS THE GENERAL LESSON, AND IT IS WORTH MORE THAN THIS CLASS
 * ----------------------------------------------------------------
 * An anonymous `throw new \DomainException('...')` is a failure with no type,
 * which means no catch clause can single it out and no boundary can map it.
 * The message is doing all the work, and messages are for humans.
 *
 * The decorator did not create that problem. It made it visible, by being the
 * first piece of code that had to make a decision based on the TYPE of what it
 * caught rather than on the message.
 *
 * Lesson 4.2 does the remaining throw sites — there are eleven more in
 * `JobCard` alone — and gives them a hierarchy rather than a flat list.
 *
 * IT CARRIES THE IDENTITY
 * ------------------------
 * Same reasoning as JobCardNotFound. The boundary needs the id as a field, and
 * a caller writing a "replace" flow wants to know which job card blocked it
 * without parsing an English sentence to find out.
 */
final class JobCardAlreadyExists extends \DomainException implements WorkshopFailure
{
    public static function withId(JobCardId $id): self
    {
        $exception = new self(sprintf(
            'Job card %s is already in this collection. To replace it, remove it first.',
            $id
        ));

        $exception->jobCardId = $id;

        return $exception;
    }

    public private(set) ?JobCardId $jobCardId = null;
}
