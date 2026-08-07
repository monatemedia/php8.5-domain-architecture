<?php
declare(strict_types=1);

namespace Workshop;

/**
 * A vehicle the workshop lends to an account holder while theirs is in.
 *
 * REFERENCE IMPLEMENTATION — one reasonable answer, not the only one.
 *
 * Two design decisions worth arguing with:
 *
 *   1. ONE FIELD, NOT TWO. Availability is derived from the job card reference
 *      rather than stored alongside it. Example 02 produced two states that
 *      cannot exist — "out with no job card" and "available but still pointing
 *      at one" — and both were possible only because two fields could disagree.
 *      Storing one fact removes the disagreement structurally, which is
 *      stronger than keeping them in step by hand.
 *
 *      Keeping both fields and maintaining them together is also correct. It is
 *      slightly faster to read and slightly easier to get wrong.
 *
 *   2. VALIDATE FIRST, MUTATE LAST. Every guard runs before any assignment, so
 *      a refused operation leaves the object exactly as it was. With one
 *      assignment this looks like pedantry. In Module 2, where an aggregate
 *      method changes five things, it is the difference between a refusal and a
 *      half-applied change.
 *
 * NOT YET MODELLED, on purpose:
 *
 *   - The job card reference is a `string`. It should be a value object, and it
 *     becomes one later. Feeling the itch is the point of Lesson 1.2.
 *   - Invariant I10 — "at most one open job card at any time" — is NOT enforced
 *     here and cannot be. This object can only speak for itself; it has no way
 *     to know what other job cards exist. That gap is Module 2's subject.
 */
final class CourtesyVehicle
{
    /**
     * The single source of truth. Null means available.
     *
     * PHP 8.4 asymmetric visibility would let this be `public private(set)` and
     * save the accessor below. It is kept private here so that the shape of the
     * class does not depend on a version feature — see COURSE_PHILOSOPHY.md,
     * "A Note on PHP Versions", carried over from the previous course.
     */
    private ?string $allocatedToJobCard = null;

    private readonly string $fleetCode;

    public function __construct(string $fleetCode)
    {
        $fleetCode = trim($fleetCode);

        if ($fleetCode === '') {
            throw new \InvalidArgumentException(
                'A courtesy vehicle needs a fleet code. An empty one is not a code in any state.'
            );
        }

        $this->fleetCode = $fleetCode;
    }

    public function fleetCode(): string
    {
        return $this->fleetCode;
    }

    public function isAvailable(): bool
    {
        return $this->allocatedToJobCard === null;
    }

    public function allocatedToJobCard(): ?string
    {
        return $this->allocatedToJobCard;
    }

    /**
     * Lend this vehicle out against a job card.
     *
     * @throws \InvalidArgumentException the reference is not a reference
     * @throws \DomainException          it is already out
     */
    public function allocateTo(string $jobCardReference): void
    {
        $jobCardReference = trim($jobCardReference);

        // Guard 1: the caller passed nonsense. No state of this vehicle would
        // make an empty reference acceptable, so it is an argument problem.
        if ($jobCardReference === '') {
            throw new \InvalidArgumentException(
                'A job card reference is required to allocate a courtesy vehicle.'
            );
        }

        // Guard 2: the argument was fine, the business rule said no. The
        // identical call would succeed once this vehicle comes back, which is
        // exactly what makes it a DomainException rather than the above.
        if (!$this->isAvailable()) {
            throw new \DomainException(sprintf(
                'Courtesy vehicle %s is already out on %s.',
                $this->fleetCode,
                $this->allocatedToJobCard
            ));
        }

        $this->allocatedToJobCard = $jobCardReference;
    }

    /**
     * Take this vehicle back into the fleet.
     *
     * @throws \DomainException it is not out
     */
    public function returnToFleet(): void
    {
        if ($this->isAvailable()) {
            throw new \DomainException(sprintf(
                'Courtesy vehicle %s is not out, so it cannot be returned.',
                $this->fleetCode
            ));
        }

        $this->allocatedToJobCard = null;
    }
}
