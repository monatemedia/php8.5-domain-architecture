<?php
declare(strict_types=1);

namespace Workshop;

/**
 * Somebody approved additional work on a job card that has none outstanding.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * A GUARD ABOUT CONTENTS, NOT STATUS
 * -----------------------------------
 * Lesson 2.3's doc block explains why this check lives on `JobCard` rather than
 * on the enum: it is about what the job card CONTAINS, not about which state it
 * is in. Awaiting Parts can legally transition to In Progress, so without this
 * guard `authoriseAdditionalWork()` would succeed there and report that work
 * had been approved when none was ever proposed.
 *
 * The exception carries no context beyond the identity, and that is fine. Not
 * every failure has fields — which is exactly why `context()` lives on
 * `CarriesContext` rather than on `WorkshopFailure`, and why the empty array
 * here is a default rather than something this class has to write out.
 */
final class NothingAwaitingAuthorisation extends JobCardException
{
    public static function on(JobCardId $id): self
    {
        return new self($id, sprintf(
            'Job card %s has no additional work awaiting authorisation.',
            $id
        ));
    }

    public function failureCode(): string
    {
        return 'nothing-awaiting-authorisation';
    }
}
