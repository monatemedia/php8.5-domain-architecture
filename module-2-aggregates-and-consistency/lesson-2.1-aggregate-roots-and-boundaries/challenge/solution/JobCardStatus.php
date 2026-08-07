<?php
declare(strict_types=1);

namespace Workshop;

/**
 * Where a job card is in its life.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * THE WHOLE POINT OF THIS CLASS IS allowedNext()
 * -----------------------------------------------
 * The transition table from DOMAIN.md, transcribed once, in one place, in a
 * form a workshop manager can be shown and can correct.
 *
 * The alternative — a status condition inside each of the aggregate's lifecycle
 * methods — is what everybody writes first, and example 03 part 1 runs the bug
 * it produces: a cancel() whose author thought about the two states you
 * obviously cannot cancel from and missed Completed, because a completed job
 * card FEELS cancellable. Finding that by reading requires holding the whole
 * table in your head while reading four unrelated method bodies, and the table
 * is written down nowhere.
 *
 * TWO THINGS THAT COME FREE
 * -------------------------
 *   Terminal states.  Invoiced and Cancelled simply have no successors. No
 *                     isTerminal flag to keep in step with anything.
 *
 *   No self-loops.    No state lists itself, so completing an
 *                     already-completed job card is refused rather than
 *                     silently succeeding. That is a decision, not an
 *                     oversight: a repeated call is a duplicate submission,
 *                     and duplicate submissions are how one job gets invoiced
 *                     twice.
 *
 * WHAT THIS ENUM IS NOT ALLOWED TO KNOW
 * --------------------------------------
 * It knows the current state and nothing else. Invariant I8 — no completing
 * while additional work awaits authorisation — is a fact about the job card's
 * CONTENTS, so it cannot live here. Lesson 1.4's test: can you decide the rule
 * holding only this object? Not this one. It lives on the aggregate.
 */
enum JobCardStatus: string
{
    case Booked                = 'booked';
    case InProgress            = 'in_progress';
    case AwaitingAuthorisation = 'awaiting_authorisation';
    case AwaitingParts         = 'awaiting_parts';
    case Completed             = 'completed';
    case Invoiced              = 'invoiced';
    case Cancelled             = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Booked     => [self::InProgress, self::Cancelled],
            self::InProgress => [
                self::AwaitingAuthorisation,
                self::AwaitingParts,
                self::Completed,
                self::Cancelled,
            ],
            self::AwaitingAuthorisation => [self::InProgress],
            self::AwaitingParts         => [self::InProgress],

            // Completed -> Cancelled is ABSENT on purpose, and it is the one
            // people get wrong. Work has been done and parts have been fitted;
            // you undo that with a credit note, which is a different operation
            // with different accounting consequences and a different audit
            // trail. Allowing a cancel here makes the work disappear.
            self::Completed => [self::Invoiced],

            self::Invoiced  => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedNext() === [];
    }

    /** 'awaiting_authorisation' -> 'Awaiting Authorisation' */
    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
