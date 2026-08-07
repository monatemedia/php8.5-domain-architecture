<?php
declare(strict_types=1);

namespace Workshop;

/**
 * A vehicle the workshop works on.
 *
 * REFERENCE IMPLEMENTATION. The first ENTITY in the course.
 *
 * WHY THIS IS NOT `readonly`
 * ---------------------------
 * An entity has a lifetime. It is re-registered, it accumulates mileage, and it
 * is still the same vehicle throughout. `readonly` forbids writes even from
 * inside the class after construction, so a readonly entity cannot express its
 * own lifecycle — it can only be replaced, and a replaced entity is a different
 * object claiming to be the same thing.
 *
 * The state is `private` with named operations instead. On PHP 8.4+ you could
 * write `public private(set)` and drop the accessors; that is equally correct
 * and the checks pass either way.
 *
 * WHY equals() COMPARES ONLY THE IDENTITY
 * ----------------------------------------
 * Both directions of the identity rule depend on it:
 *
 *   - Two vehicles with the same registration, colour and mileage are two
 *     vehicles. Identity says so; attributes cannot.
 *   - One vehicle whose every attribute has changed is still that vehicle, and
 *     every reference to it elsewhere is still correct.
 *
 * An equals() that also compared attributes would report that a vehicle stopped
 * being itself the moment somebody corrected a typo. Downstream that looks like
 * duplicate rows, a cache that never hits, and a repository saving two vehicles
 * where there was one.
 *
 * WHAT IS NOT MODELLED HERE, ON PURPOSE
 * --------------------------------------
 *   - No account holder. A vehicle is worked on by the workshop and BILLED to
 *     an account holder, and those are different relationships with different
 *     lifetimes — fleet vehicles change hands. Modelling it as a field on
 *     Vehicle would bake in "one vehicle, one payer forever", which is false.
 *
 *   - No job cards. A vehicle does not own its job cards; a job card refers to
 *     a vehicle. Getting that arrow the wrong way round is how aggregates end
 *     up enormous, and Lesson 2.1 is about it at length.
 *
 *   - No `isDue()`, `serviceInterval()` or similar. Those need a policy and a
 *     clock, neither of which belongs inside an entity. See COURSE_PHILOSOPHY,
 *     Rule 1.
 */
final class Vehicle
{
    /**
     * @var list<OdometerReading> oldest first
     *
     * Kept because a workshop genuinely needs it: a sequence of readings is how
     * you spot a replaced instrument cluster, and it is evidence in a warranty
     * dispute.
     *
     * It is also a defensible thing to leave out at this stage — the challenge
     * does not require it, and "the current reading" would be enough for
     * everything the checks ask for. It is here as something to disagree with:
     * an entity that quietly accumulates an unbounded array is a real cost in
     * Module 3, when this has to be persisted.
     */
    private array $readingHistory;

    public function __construct(
        private readonly VehicleId $id,
        private RegistrationNumber $registration,
        private OdometerReading    $odometer,
    ) {
        $this->readingHistory = [$odometer];
    }

    public function id(): VehicleId
    {
        return $this->id;
    }

    /**
     * Returns the value object, not a string.
     *
     * Handing back a string here would undo Lesson 1.2 one caller at a time:
     * the first consumer would compare it with `===` against something
     * un-normalised, and the four-spellings bug would be back.
     */
    public function registration(): RegistrationNumber
    {
        return $this->registration;
    }

    public function odometer(): OdometerReading
    {
        return $this->odometer;
    }

    /**
     * INVARIANT I4: an odometer reading may never decrease for a given vehicle.
     *
     * Note how little work this method does, and why: `$reading` is already
     * known to be non-negative and plausible, because it is an OdometerReading
     * and there is no other way to obtain one. The entity only has to check the
     * thing the value cannot know about — the previous reading.
     *
     * Note also `isLessThan`, not "is not greater than". "May never decrease"
     * is not "must always increase": a vehicle in for two jobs on the same day
     * has not moved, and refusing that would send somebody to the service desk
     * to invent a number. The difference between < and <= has gone to
     * production more than once.
     *
     * @throws \DomainException the reading is lower than the current one
     */
    public function recordOdometer(OdometerReading $reading): void
    {
        // Validate first, mutate last. With one assignment this looks like
        // pedantry; in Module 2 it is the difference between a refusal and a
        // half-applied change.
        if ($reading->isLessThan($this->odometer)) {
            throw new \DomainException(sprintf(
                'Vehicle %s is on %s, so %s would be a decrease. An odometer that goes '
                . 'backwards means a replaced instrument cluster or a mis-keyed reading, '
                . 'and both need a person to decide which.',
                $this->id,
                $this->odometer,
                $reading
            ));
        }

        $this->odometer         = $reading;
        $this->readingHistory[] = $reading;
    }

    /**
     * A vehicle can be re-registered. It is the same vehicle afterwards, which
     * is the entire argument against using the registration as its identity.
     */
    public function reRegister(RegistrationNumber $registration): void
    {
        $this->registration = $registration;
    }

    /**
     * Identity, and nothing else. See the class note.
     */
    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }

    /**
     * @return list<OdometerReading> oldest first
     *
     * PHP copies arrays on assignment, so the caller gets its own array and
     * cannot append to ours. The OdometerReading objects inside are shared, and
     * that is safe precisely because they are immutable — which is a concrete
     * payoff for Lesson 1.2 that is easy to miss.
     *
     * If they were mutable, this method would be a hole in every guard above.
     * Module 2 returns to this at length, where the contents are entities.
     */
    public function readingHistory(): array
    {
        return $this->readingHistory;
    }
}
