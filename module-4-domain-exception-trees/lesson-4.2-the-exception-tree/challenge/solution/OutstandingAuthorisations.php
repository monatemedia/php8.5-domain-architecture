<?php
declare(strict_types=1);

namespace Workshop;

/**
 * INVARIANT I8: a job card cannot be completed while additional work awaits
 * authorisation.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * THIS GUARD IS STILL REDUNDANT
 * ------------------------------
 * Lesson 2.3 was explicit about it: `discoverAdditionalWork()` moves the status
 * to Awaiting Authorisation, and the transition table has no path from there to
 * Completed. Delete the guard and nothing new becomes possible; the state
 * machine already refuses it.
 *
 * It is kept for the message, and the tree makes that argument concrete in a
 * way prose could not. Without the guard, a caller gets:
 *
 *     IllegalStatusTransition
 *     from: awaiting_authorisation, to: completed
 *
 * ...which describes the model. With it:
 *
 *     OutstandingAuthorisations
 *     descriptions: ['Rear discs below minimum thickness']
 *
 * ...which describes the workshop, and tells the caller what to go and get
 * approved. Two different failure codes, so a client can handle them
 * differently, which is the thing a message alone could never support.
 *
 * The counter-argument from 2.3 stands and is now sharper: a reader cannot tell
 * which of the two is the real rule, and if the transition table ever changed
 * to allow the path, this guard would silently become the only thing enforcing
 * I8 — a duplicate that had quietly become the original.
 *
 * THE LIST IS A LIST
 * -------------------
 * `descriptions` is `list<string>`, not a joined sentence. Joining it here
 * would put a presentation decision — semicolons? commas? "and"? — inside the
 * domain, and take from the client the ability to render it as bullet points.
 * The message joins them, because a message is prose; the context does not,
 * because context is data.
 */
final class OutstandingAuthorisations extends JobCardException
{
    /**
     * @param list<string> $descriptions
     */
    public static function on(JobCardId $id, array $descriptions): self
    {
        return new self(
            $id,
            sprintf(
                'Job card %s cannot be completed: %d item(s) of additional work are still '
                . 'waiting for the account holder to approve them (%s).',
                $id,
                count($descriptions),
                implode('; ', $descriptions)
            ),
            [
                'count'        => count($descriptions),
                'descriptions' => array_values($descriptions),
            ]
        );
    }

    public function failureCode(): string
    {
        return 'outstanding-authorisations';
    }
}
