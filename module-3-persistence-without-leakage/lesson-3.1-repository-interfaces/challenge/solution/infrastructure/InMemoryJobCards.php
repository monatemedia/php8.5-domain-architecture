<?php
declare(strict_types=1);

namespace Infrastructure;

use Workshop\JobCard;
use Workshop\JobCardId;
use Workshop\JobCardNotFound;
use Workshop\JobCardStatus;
use Workshop\JobCards;
use Workshop\VehicleId;

/**
 * Job cards in an array.
 *
 * REFERENCE IMPLEMENTATION, and the first class in this course that lives
 * OUTSIDE `domain/workshop/`.
 *
 * NOTE THE `use` STATEMENTS AT THE TOP
 * -------------------------------------
 * Six of them, all pointing INTO the domain. That direction is the entire
 * lesson. `Workshop\JobCards` has no imports at all and knows nothing about
 * this file; every challenge in the course checks that, and the capstone proves
 * it for the whole directory.
 *
 * Infrastructure knows about the domain. The domain knows nothing.
 *
 * WHY THIS IS NOT "JUST A TEST DOUBLE"
 * -------------------------------------
 * It is a real implementation of a real interface, and Lesson 3.4 runs the same
 * contract test against this and against SQLite to prove they are
 * substitutable — Liskov, cashed in as something you can execute.
 *
 * Treating it as a throwaway is how in-memory fakes end up subtly different
 * from the real thing: a fake that returns results in insertion order while
 * SQL returns them arbitrarily gives you a green test suite and a broken
 * production system.
 *
 * THE PART THAT WILL NOT SURVIVE MODULE 3
 * ----------------------------------------
 * This class stores REFERENCES. `add()` puts the caller's object in an array,
 * so anything they do to it afterwards is visible here immediately, and there
 * is nothing to write.
 *
 * A SQLite implementation cannot do that. It has no idea you changed anything,
 * and the collection-oriented interface gives it nowhere to find out. That
 * mismatch is not a flaw in this class — it is the reason unit-of-work patterns
 * exist, and Lesson 3.2 meets it head on.
 *
 * It also means this class hands out references to aggregates it holds. For a
 * fake that is correct behaviour; for a SQLite implementation it will not even
 * be possible. Notice how much easier this is than what comes next, and be
 * suspicious of the ease.
 */
final class InMemoryJobCards implements JobCards
{
    /** @var array<string, JobCard> keyed by the string form of the identity */
    private array $jobCards = [];

    public function add(JobCard $jobCard): void
    {
        $key = $jobCard->id()->value();

        // A collection does not silently replace. If this were permitted,
        // add()-ing a stale copy would quietly discard whatever was here.
        if (isset($this->jobCards[$key])) {
            throw new \DomainException(sprintf(
                'Job card %s is already in this collection. To replace it, remove it first.',
                $jobCard->id()
            ));
        }

        $this->jobCards[$key] = $jobCard;
    }

    public function get(JobCardId $id): JobCard
    {
        return $this->jobCards[$id->value()] ?? throw JobCardNotFound::withId($id);
    }

    public function contains(JobCardId $id): bool
    {
        return isset($this->jobCards[$id->value()]);
    }

    public function remove(JobCardId $id): void
    {
        if (!isset($this->jobCards[$id->value()])) {
            throw JobCardNotFound::withId($id);
        }

        unset($this->jobCards[$id->value()]);
    }

    /**
     * @return list<JobCard>
     */
    public function forVehicle(VehicleId $vehicleId): array
    {
        return $this->filter(
            static fn(JobCard $jobCard): bool => $jobCard->vehicleId()->equals($vehicleId)
        );
    }

    /**
     * @return list<JobCard>
     */
    public function awaitingAuthorisation(): array
    {
        return $this->filter(
            static fn(JobCard $jobCard): bool => $jobCard->status() === JobCardStatus::AwaitingAuthorisation
        );
    }

    public function count(): int
    {
        return count($this->jobCards);
    }

    /**
     * `array_values()` matters: the interface promises a LIST, and filtering an
     * associative array leaves the original keys behind. A caller doing
     * `$results[0]` would then get an undefined-key error on a result set that
     * is not empty, which is a confusing way to find out.
     *
     * @param callable(JobCard): bool $matches
     *
     * @return list<JobCard>
     */
    private function filter(callable $matches): array
    {
        return array_values(array_filter($this->jobCards, $matches));
    }
}
