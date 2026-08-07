<?php
declare(strict_types=1);

namespace Workshop;

/**
 * Somebody asked for a job card that is not there.
 *
 * REFERENCE IMPLEMENTATION — re-parented in Lesson 4.2.
 *
 * THE THIRD TIME THIS CLASS HAS CHANGED, AND STILL NOTHING BROKE
 * ---------------------------------------------------------------
 *   3.1  created, extending \DomainException, carrying the identity
 *   4.1  gained `implements WorkshopFailure`
 *   4.2  gained a parent: JobCardException, which now holds the identity
 *
 * It is still a `\DomainException` — through two intermediate classes rather
 * than directly — so Lesson 3.1's `is_subclass_of(..., DomainException::class)`
 * still passes, and so does every `throws(\DomainException::class, ...)` in
 * Modules 2 and 3.
 *
 * `$e->jobCardId` still reads the same from outside. It moved to the parent,
 * which callers cannot see and do not care about.
 *
 * WHAT WAS DELETED
 * -----------------
 * The `?JobCardId $jobCardId = null` property. It was nullable only because
 * there was nowhere better to put it and a named constructor had to assign it
 * after construction. With the identity as a constructor parameter one level
 * up, it is non-nullable — an exception about a job card cannot exist without
 * one, and now the type says so.
 *
 * That is the quiet win in this lesson. The tree did not just organise the
 * exceptions; it removed a null that had no business existing.
 */
final class JobCardNotFound extends JobCardException
{
    public static function withId(JobCardId $id): self
    {
        return new self($id, sprintf('No job card with id %s.', $id));
    }

    public function failureCode(): string
    {
        return 'job-card-not-found';
    }
}
