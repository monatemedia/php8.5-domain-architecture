<?php
declare(strict_types=1);

namespace Workshop;

/**
 * Additional work was recorded with no description of what it is.
 *
 * REFERENCE IMPLEMENTATION. The only class on the `InvalidWorkshopInput` branch,
 * and it is here to prove the branch is real rather than theoretical.
 *
 * WHY IT IS INPUT AND NOT A BUSINESS RULE
 * ----------------------------------------
 * Apply the test from `InvalidWorkshopInput`:
 *
 *     Could the caller have known this was invalid without asking the domain?
 *
 * Yes. An empty string is empty in the controller, in the form, and in the
 * caller's own variable. Nothing about the job card's state was consulted.
 *
 * Compare `NothingAwaitingAuthorisation`, which sits on the other branch: the
 * caller could NOT have known that, because it depends on what the job card
 * contains, and only the job card knows.
 *
 * That is the whole distinction, and Lesson 4.4 turns it into 422 versus 409.
 *
 * WHY THIS IS NOT ALSO A VALUE OBJECT
 * ------------------------------------
 * The honest answer is that it should probably be one. A `WorkDescription`
 * value object would make the empty case unrepresentable rather than merely
 * refused — Lesson 1.2's argument, applied here — and this exception would
 * mostly disappear into its constructor.
 *
 * It is left as a string because `discoverAdditionalWork()` was written in
 * Lesson 2.3 and changing its signature now would break Lesson 2.3's checks,
 * and the course has held to additive change throughout Modules 2, 3 and 4.
 * That is a real cost of that discipline, and it is worth seeing one.
 */
final class MissingWorkDescription extends InvalidWorkshopInput
{
    public static function forJobCard(JobCardId $id): self
    {
        return new self(
            'Additional work needs a description — it is what the account holder is '
            . 'being asked to approve.',
            ['jobCardId' => $id->value()]
        );
    }

    public function failureCode(): string
    {
        return 'missing-work-description';
    }
}
