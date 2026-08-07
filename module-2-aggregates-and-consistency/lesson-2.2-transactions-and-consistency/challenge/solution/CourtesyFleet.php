<?php
declare(strict_types=1);

namespace Workshop;

/**
 * The workshop's courtesy vehicles, and who has them.
 *
 * REFERENCE IMPLEMENTATION. The second AGGREGATE ROOT in the course, and the
 * answer to invariant I10.
 *
 * WHY THIS EXISTS
 * ---------------
 * I10: a courtesy vehicle is on at most one open job card AT ANY TIME.
 *
 * Lesson 1.1's CourtesyVehicle already refuses a second allocation, which looks
 * like I10 and is not. Load the same vehicle twice — two requests, two
 * repository calls, entirely ordinary — allocate each copy to a different job
 * card, and every object obeys its own rule while the workshop ends up with one
 * car promised to two customers. Example 02 runs exactly that.
 *
 * The fleet closes it by OWNING every vehicle, so there is exactly one instance
 * of each. Note the fix is not a cleverer check. The check was always fine; it
 * had nothing to run against.
 *
 * WHAT THIS COSTS, CHOSEN RATHER THAN STUMBLED INTO
 * --------------------------------------------------
 * Every allocation now locks the whole fleet. Two allocations of two DIFFERENT
 * cars contend with each other for no business reason, and loading the fleet
 * loads every vehicle in it. That is the large-aggregate failure from Lesson
 * 2.1 §2, deliberately.
 *
 * It is affordable at twelve vehicles and eight allocations a day. It is not
 * affordable at ten thousand and seven thousand — and that number is a fact
 * about the business, not about this file. If the workshop grows into a
 * national rental company, this class becomes wrong, and it becomes wrong
 * without anybody having made a mistake.
 *
 * The alternative is example 03: allow the collision, reconcile, compensate.
 * Cheaper to run, and it costs a window in which I10 is false plus a phone call
 * to a customer who was told they had a car. Which is right is not an
 * engineering decision — README §6.
 *
 * WHY NOTHING GETS IN OR OUT
 * ---------------------------
 * CourtesyVehicle is a MUTABLE entity. Handing one out would let a caller
 * allocate it directly, behind the fleet's back, and I10 would be defeated
 * exactly as before — Lesson 2.1's leaky collection, with the invariant as the
 * casualty.
 *
 * So every accessor returns an IDENTITY, and addVehicle() CREATES the vehicle
 * rather than accepting one, because a caller who passed an object in would
 * keep a handle to it. The rule is symmetrical and worth stating as one:
 *
 *     nothing inside the boundary should have a reference to it from outside.
 *
 * WHAT IS NOT MODELLED, ON PURPOSE
 * ---------------------------------
 *   - Whether a job card is still open. The fleet holds allocations, not job
 *     card lifecycles, and asking a JobCard would mean holding one — a second
 *     aggregate inside this boundary. In practice the application layer returns
 *     the vehicle when the job card completes; Lesson 2.3's domain events are
 *     how that happens without either aggregate knowing about the other.
 *
 *   - Who wins a collision, because in this design there are no collisions.
 *     That question only arises in example 03's version, and its answer is a
 *     commercial decision.
 */
final class CourtesyFleet
{
    /**
     * Keyed by the string form of the vehicle's identity.
     *
     * @var array<string, CourtesyVehicle>
     */
    private array $vehicles = [];

    private function __construct() {}

    public static function create(): self
    {
        return new self();
    }

    // ── Membership ──────────────────────────────────────────────────────────

    /**
     * Put a new vehicle into the fleet.
     *
     * Takes a fleet code and returns an IDENTITY. It does not take a
     * CourtesyVehicle, because the caller would keep a reference to it; and it
     * does not return one, for the same reason. See the class note.
     *
     * @throws \InvalidArgumentException the fleet code is empty
     * @throws \DomainException          that fleet code is already in this fleet
     */
    public function addVehicle(string $fleetCode): CourtesyVehicleId
    {
        // Delegated to the constructor, which has enforced this since 1.1.
        $vehicle = new CourtesyVehicle($fleetCode);

        // An aggregate-scope invariant: fleet codes identify a car to the
        // people who work here, so two cars answering to CV-01 is not a data
        // detail, it is two sets of keys on the same hook.
        //
        // Note this is only enforceable HERE. A CourtesyVehicle cannot know
        // what other fleet codes exist, which is the same argument as I10 at a
        // smaller scale.
        foreach ($this->vehicles as $existing) {
            if ($existing->fleetCode() === $vehicle->fleetCode()) {
                throw new \DomainException(sprintf(
                    'Fleet code %s is already in this fleet.',
                    $vehicle->fleetCode()
                ));
            }
        }

        $this->vehicles[$vehicle->id()->value()] = $vehicle;

        return $vehicle->id();
    }

    public function size(): int
    {
        return count($this->vehicles);
    }

    public function has(CourtesyVehicleId $vehicleId): bool
    {
        return isset($this->vehicles[$vehicleId->value()]);
    }

    /**
     * @throws \DomainException the vehicle is not in this fleet
     */
    public function fleetCodeOf(CourtesyVehicleId $vehicleId): string
    {
        return $this->vehicleOrFail($vehicleId)->fleetCode();
    }

    // ── Allocation ──────────────────────────────────────────────────────────

    /**
     * Lend a vehicle out against a job card.
     *
     * Two rules fire here and they are enforced in different places, which is
     * worth noticing:
     *
     *   "a job card has at most one courtesy vehicle"  -> HERE. It needs to see
     *      every vehicle to answer, so nothing smaller could enforce it.
     *
     *   "a vehicle is out on at most one job card"     -> the VEHICLE, exactly
     *      as in Lesson 1.1. The fleet adds nothing to that check; it only
     *      guarantees there is one instance for the check to run against.
     *
     * Order: the job-card rule first, because it is about the thing the caller
     * is acting on. Told "CV-01 is already out" when the real problem is that
     * this job card already has CV-02, they would go and find another car and
     * hit the same wall.
     *
     * @throws \DomainException the vehicle is unknown, the job card already has
     *                          one, or the vehicle is already out
     */
    public function allocate(CourtesyVehicleId $vehicleId, JobCardId $jobCardId): void
    {
        $vehicle = $this->vehicleOrFail($vehicleId);

        $existing = $this->allocationFor($jobCardId);
        if ($existing !== null) {
            throw new \DomainException(sprintf(
                'Job card %s already has courtesy vehicle %s out.',
                $jobCardId,
                $this->fleetCodeOf($existing)
            ));
        }

        // The string conversion is the tax on not breaking Lesson 1.1's API.
        // See the note in CourtesyVehicle.
        $vehicle->allocateTo($jobCardId->value());
    }

    /**
     * Take back whatever this job card has out.
     *
     * @throws \DomainException the job card has no courtesy vehicle
     */
    public function returnFromJobCard(JobCardId $jobCardId): void
    {
        $vehicleId = $this->allocationFor($jobCardId);

        if ($vehicleId === null) {
            throw new \DomainException(sprintf(
                'Job card %s has no courtesy vehicle out.',
                $jobCardId
            ));
        }

        $this->vehicleOrFail($vehicleId)->returnToFleet();
    }

    // ── Queries ─────────────────────────────────────────────────────────────

    /**
     * @throws \DomainException the vehicle is not in this fleet
     */
    public function isAvailable(CourtesyVehicleId $vehicleId): bool
    {
        return $this->vehicleOrFail($vehicleId)->isAvailable();
    }

    /**
     * @return list<CourtesyVehicleId>
     *
     * Identities, not vehicles. A caller wanting the fleet code asks
     * fleetCodeOf() — which is two calls where one would do, and is the price
     * of not letting a mutable member escape.
     */
    public function availableVehicles(): array
    {
        $available = [];

        foreach ($this->vehicles as $vehicle) {
            if ($vehicle->isAvailable()) {
                $available[] = $vehicle->id();
            }
        }

        return $available;
    }

    public function allocationFor(JobCardId $jobCardId): ?CourtesyVehicleId
    {
        foreach ($this->vehicles as $vehicle) {
            if ($vehicle->allocatedToJobCard() === $jobCardId->value()) {
                return $vehicle->id();
            }
        }

        return null;
    }

    /**
     * @throws \DomainException the vehicle is not in this fleet
     */
    public function jobCardFor(CourtesyVehicleId $vehicleId): ?JobCardId
    {
        $reference = $this->vehicleOrFail($vehicleId)->allocatedToJobCard();

        return $reference === null ? null : JobCardId::fromString($reference);
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /**
     * @throws \DomainException
     */
    private function vehicleOrFail(CourtesyVehicleId $vehicleId): CourtesyVehicle
    {
        if (!isset($this->vehicles[$vehicleId->value()])) {
            throw new \DomainException(sprintf(
                'Courtesy vehicle %s is not in this fleet.',
                $vehicleId
            ));
        }

        return $this->vehicles[$vehicleId->value()];
    }
}
