<?php
declare(strict_types=1);

namespace Workshop;

/**
 * Somebody asked for a job card that is not there.
 *
 * REFERENCE IMPLEMENTATION — modified in Lesson 4.1.
 *
 * WHAT CHANGED, AND HOW LITTLE
 * -----------------------------
 * One clause: `implements WorkshopFailure`. Nothing else in this file moved.
 *
 * That is the payoff for a decision made in Lesson 3.1, before there was any
 * tree to fit into. The doc block there said:
 *
 *     Note also what it does NOT extend: not PDOException, not
 *     RuntimeException. It is a DomainException because a request for a job
 *     card that does not exist is a business-level mistake, not a crash.
 *
 * Because that call was made on the merits at the time, re-parenting is a
 * one-word change now. Had it been thrown as a \RuntimeException "because the
 * repository is infrastructure", this lesson would be a refactor with a
 * migration note.
 *
 * IT IS STILL A \DomainException
 * -------------------------------
 * Deliberately. `Workshop\JobCards::get()` documents `@throws JobCardNotFound`,
 * and Lesson 3.1's checks assert the type — but Lesson 3.4's contract test also
 * has clauses written against `\DomainException`. Adding an interface changes
 * neither, which is why `php regression.php` stays green through this lesson.
 *
 * Additive change, again: nothing was removed and no signature moved. That is
 * the third time in this course and it is not luck. It is what happens when the
 * original type was chosen for a reason rather than for convenience.
 *
 * WHY AN EXCEPTION RATHER THAN null
 * ----------------------------------
 * `JobCards::get()` promises a JobCard. Returning null instead would mean every
 * caller in the system writes the same three lines, and the day one of them
 * forgets, the null travels.
 *
 * There is a real counter-argument: "not found" is not always exceptional.
 * Behind a search box, a miss is the ordinary case, and an exception for the
 * ordinary case is control flow with a stack trace attached.
 *
 * That is why the repository has BOTH shapes:
 *
 *     get($id)      "I know this exists" -> throws if it does not
 *     contains($id) "does this exist?"   -> answers
 *
 * Lesson 4.3 takes the whole argument apart properly as exceptions versus
 * result types.
 *
 * WHY IT IS IN THE DOMAIN
 * -----------------------
 * A missing job card is a fact about the workshop, not about a database. The
 * same exception is right whether the repository is SQLite, an array, or a
 * filing cabinet — which is exactly the test for `domain/workshop/`.
 */
final class JobCardNotFound extends \DomainException implements WorkshopFailure
{
    /**
     * A named constructor, so the message is written once and every call site
     * produces the same sentence.
     *
     * The identity is carried on the exception as well as inside the message.
     * Lesson 4.2 generalises that — structured context rather than a formatted
     * string — and Lesson 4.4 spends it: an RFC 9457 response needs the id as a
     * field, not a sentence with the id somewhere in it.
     */
    public static function withId(JobCardId $id): self
    {
        $exception = new self(sprintf('No job card with id %s.', $id));
        $exception->jobCardId = $id;

        return $exception;
    }

    public private(set) ?JobCardId $jobCardId = null;
}
