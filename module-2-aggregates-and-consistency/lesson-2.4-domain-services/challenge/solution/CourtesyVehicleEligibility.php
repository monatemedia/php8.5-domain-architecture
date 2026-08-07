<?php
declare(strict_types=1);

namespace Workshop;

/**
 * Whether a job card qualifies for a courtesy vehicle.
 *
 * REFERENCE IMPLEMENTATION. The only DOMAIN SERVICE in this course, and it is
 * worth being suspicious of even so.
 *
 * WHY THIS IS NOT ON JobCard
 * ---------------------------
 * The obvious home is `$jobCard->isEligibleForCourtesyVehicle()`. It would read
 * beautifully and it would be wrong, because the answer depends on a MINIMUM
 * SPEND that the workshop negotiates:
 *
 *   - it was 15000 minor units last year;
 *   - it is 0 for fleet accounts, who get one regardless;
 *   - Marcus expects to move it again.
 *
 * That is a POLICY, not an invariant — DOMAIN.md, "What is not an invariant" —
 * and a policy baked into an entity is a bug with a commencement date. Nobody
 * finds it until the day it changes, and then the change is a deployment rather
 * than a setting.
 *
 * WHY IT IS NOT ON Money EITHER
 * ------------------------------
 * `$total->meetsMinimum($threshold)` is just `isGreaterThan`, and it would have
 * nothing to say about the job card's status. The rule needs BOTH facts.
 *
 * SO WHAT MAKES IT A GENUINE DOMAIN SERVICE
 * ------------------------------------------
 * Four things, and all four must hold or it is something else:
 *
 *   1. It is a real operation the business has a NAME for. The service manager
 *      says "does this one qualify for a courtesy car?" — it is not a technical
 *      step somebody invented.
 *
 *   2. It belongs to NO single object. It needs a job card and a negotiated
 *      threshold; the job card must not hold the threshold, and the threshold
 *      cannot see the job card.
 *
 *   3. It is STATELESS. Constructed with its policy, then asked. It remembers
 *      nothing between calls, so two threads asking about two job cards cannot
 *      interfere. Rule 5 of the previous course.
 *
 *   4. It works ONLY on domain objects. No repository, no clock, no HTTP, no
 *      database. Hand it a JobCard and it answers. If it needed to LOAD the job
 *      card, it would be an application service and would belong outside
 *      domain/workshop/.
 *
 * THE HONEST CAVEAT
 * -----------------
 * Point 2 is the weakest. You could argue this is a policy object with a
 * question on it rather than a service, and you would not be wrong — the two
 * are frequently the same thing wearing different names. What matters is that
 * the threshold is passed in rather than welded into JobCard. If the vocabulary
 * argument interests you more than the outcome, you have found the part of DDD
 * that generates the most words and the least value.
 *
 * WHAT WOULD MAKE IT NOT A DOMAIN SERVICE
 * ----------------------------------------
 * If it took a JobCardId and loaded the job card, it would be an APPLICATION
 * service: orchestration, not domain logic, and it would need infrastructure.
 * That is the line, and it is the one most codebases smear.
 */
final readonly class CourtesyVehicleEligibility
{
    private function __construct(private Money $minimumSpend) {}

    /**
     * The policy, passed in. This is the whole point of the class.
     */
    public static function requiringSpendOf(Money $minimumSpend): self
    {
        return new self($minimumSpend);
    }

    /**
     * A fleet contract: they get one regardless of what the job comes to.
     */
    public static function unconditional(Currency $currency): self
    {
        return new self(Money::zero($currency));
    }

    public function minimumSpend(): Money
    {
        return $this->minimumSpend;
    }

    /**
     * @throws \DomainException the job card is in a different currency
     */
    public function isEligible(JobCard $jobCard): bool
    {
        return $this->reasonsAgainst($jobCard) === [];
    }

    /**
     * Every reason the job card does not qualify, in business language.
     *
     * Returning REASONS rather than a bare false is the single most useful
     * decision in this class. "Not eligible" sends a service adviser to guess;
     * "the job comes to 45.00 and the minimum is 150.00" lets them tell the
     * account holder something true.
     *
     * It is also what makes the rule readable back to the workshop, which is
     * Rule 4 — they can correct a sentence, and they cannot correct a boolean.
     *
     * @return list<string> empty when the job card qualifies
     *
     * @throws \DomainException the job card is in a different currency
     */
    public function reasonsAgainst(JobCard $jobCard): array
    {
        // Not a business rule — a guard against a question that has no answer.
        // Money::isGreaterThan() would refuse this anyway; catching it here
        // names the job card instead of complaining about arithmetic.
        if ($jobCard->currency() !== $this->minimumSpend->currency()) {
            throw new \DomainException(sprintf(
                'Job card %s is in %s and the minimum spend is in %s, so eligibility cannot '
                . 'be decided. Convert explicitly first.',
                $jobCard->id(),
                $jobCard->currency()->value,
                $this->minimumSpend->currency()->value
            ));
        }

        $reasons = [];

        // A terminal job card is finished with. Lending a car against an
        // invoiced or cancelled job is not a thing the workshop does.
        if ($jobCard->status()->isTerminal()) {
            $reasons[] = sprintf(
                'Job card %s is %s, so no courtesy vehicle can be allocated against it.',
                $jobCard->id(),
                $jobCard->status()->label()
            );
        }

        // Note this reads the job card's PUBLIC API only. The service does not
        // reach inside the aggregate, and it could not: total() is derived and
        // the lines are not exposed for modification.
        if ($this->minimumSpend->isGreaterThan($jobCard->total())) {
            $reasons[] = sprintf(
                'Job card %s comes to %s, below the %s minimum for a courtesy vehicle.',
                $jobCard->id(),
                $jobCard->total(),
                $this->minimumSpend
            );
        }

        return $reasons;
    }
}
