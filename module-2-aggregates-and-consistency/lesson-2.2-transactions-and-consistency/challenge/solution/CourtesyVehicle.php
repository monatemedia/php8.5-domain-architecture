<?php
declare(strict_types=1);

namespace Workshop;

/**
 * A vehicle the workshop lends to an account holder while theirs is in.
 *
 * REFERENCE IMPLEMENTATION — Lesson 1.1's class, with ONE ADDITION.
 *
 * Added:     a CourtesyVehicleId, minted in the constructor, and id().
 * Unchanged: the constructor signature and every existing method.
 *
 * The same additive discipline as LabourLine in Lesson 2.1, for the same
 * reason: Lesson 1.1's checks must still pass. A course where finishing one
 * lesson un-finishes an earlier one is a course nobody trusts.
 *
 * IT IS NO LONGER AN AGGREGATE ROOT
 * ----------------------------------
 * In Lesson 1.1 this was the outermost thing — you constructed one and called
 * allocateTo() on it. It is now a MEMBER of the CourtesyFleet aggregate, and
 * outside code should reach it only through the fleet.
 *
 * Nothing in the language enforces that. `new CourtesyVehicle('CV-01')` still
 * works, and a caller who does that gets an object the fleet knows nothing
 * about, which can be allocated behind its back. That is a real hole and it is
 * worth being uncomfortable about: aggregate boundaries in PHP are a
 * convention, held by agreement and by code review, not by the compiler.
 *
 * The mitigation is that the fleet creates its own members and never hands one
 * out — so within a codebase that goes through the fleet, there is no way to
 * obtain a reference to one. See CourtesyFleet::addVehicle().
 *
 * WHAT DID NOT CHANGE, AND THE WART IT LEAVES
 * --------------------------------------------
 * allocateTo() still takes a `string $jobCardReference`, because it was written
 * before JobCardId existed and changing the type would break Lesson 1.1. So the
 * fleet converts on the way in and back on the way out. That round trip is
 * ugly, it is real, and it is exactly the tax you pay in a codebase you cannot
 * freely change. Module 5 is about doing this at scale to somebody else's code.
 *
 * The honest fix, when you can take it: add allocateToJobCard(JobCardId),
 * migrate callers, remove the old method. Three commits and a conversation.
 */
final class CourtesyVehicle
{
    private readonly CourtesyVehicleId $id;

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

        $this->id        = CourtesyVehicleId::generate();
        $this->fleetCode = $fleetCode;
    }

    /**
     * Added in Lesson 2.2. Everything below this line is unchanged from 1.1.
     */
    public function id(): CourtesyVehicleId
    {
        return $this->id;
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
     * @throws \InvalidArgumentException the reference is not a reference
     * @throws \DomainException          it is already out
     */
    public function allocateTo(string $jobCardReference): void
    {
        $jobCardReference = trim($jobCardReference);

        if ($jobCardReference === '') {
            throw new \InvalidArgumentException(
                'A job card reference is required to allocate a courtesy vehicle.'
            );
        }

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
