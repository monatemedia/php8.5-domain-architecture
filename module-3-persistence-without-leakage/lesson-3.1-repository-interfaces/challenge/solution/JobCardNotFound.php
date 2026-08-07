<?php
declare(strict_types=1);

namespace Workshop;

/**
 * Somebody asked for a job card that is not there.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * WHY AN EXCEPTION RATHER THAN null
 * ----------------------------------
 * `JobCards::get()` promises a JobCard. Returning null instead would mean every
 * caller in the system writes the same three lines:
 *
 *     $jobCard = $jobCards->get($id);
 *     if ($jobCard === null) { throw ... }   // or worse: if ($jobCard) { ... }
 *
 * ...and the day one of them forgets, the null travels. Lesson 1.1's second
 * caller, in a new costume.
 *
 * There is a real counter-argument and it is worth knowing: "not found" is not
 * always exceptional. Looking up a job card by a reference somebody typed into
 * a search box, a miss is the ordinary case, and an exception for the ordinary
 * case is control flow with a stack trace attached.
 *
 * That is why this repository has BOTH shapes, and they mean different things:
 *
 *     get($id)      "I know this exists" -> throws if it does not
 *     contains($id) "does this exist?"   -> answers
 *
 * Lesson 4.3 revisits the whole argument as exceptions versus result types.
 *
 * WHY IT IS IN THE DOMAIN
 * -----------------------
 * A missing job card is a fact about the workshop, not about a database. The
 * same exception is right whether the repository is SQLite, an array, or a
 * filing cabinet — which is exactly the test for whether something belongs in
 * `domain/workshop/`.
 *
 * Note also what it does NOT extend: not PDOException, not RuntimeException.
 * It is a DomainException because a request for a job card that does not exist
 * is a business-level mistake, not a crash. Module 4 builds the whole tree.
 */
final class JobCardNotFound extends \DomainException
{
    /**
     * A named constructor, so the message is written once and every call site
     * produces the same sentence.
     *
     * The identity is carried on the exception as well as in the message —
     * Lesson 4.2 is about carrying structured context rather than only a
     * formatted string, and a boundary that wants to render this as JSON will
     * need the id, not a sentence with the id inside it.
     */
    public static function withId(JobCardId $id): self
    {
        $exception = new self(sprintf('No job card with id %s.', $id));
        $exception->jobCardId = $id;

        return $exception;
    }

    public private(set) ?JobCardId $jobCardId = null;
}
