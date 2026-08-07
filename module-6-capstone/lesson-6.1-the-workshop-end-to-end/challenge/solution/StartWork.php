<?php
declare(strict_types=1);

namespace Workshop;

/**
 * The technician has started.
 *
 * REFERENCE IMPLEMENTATION. The smallest use case in the capstone, and it is
 * here for a reason that is not about starting work.
 *
 * Without it the API cannot reach any state except Booked, so `complete()` and
 * `invoice()` could only ever be tested refusing. An API that can only be
 * driven into its failure paths is not assembled; it is demonstrated.
 *
 * It is also the third file in a row containing the same three lines —
 * `get()`, change, `remove()`+`add()`. See `CompleteJobCard` on why that
 * duplication is being left visible rather than hidden behind a base class:
 * it is the shape of a missing `save()`, and hiding it would hide the argument.
 */
final readonly class StartWork
{
    public function __construct(private JobCards $jobCards) {}

    /**
     * @throws JobCardNotFound
     * @throws IllegalStatusTransition
     */
    public function __invoke(string $jobCardId): void
    {
        $id      = JobCardId::fromString($jobCardId);
        $jobCard = $this->jobCards->get($id);

        $jobCard->startWork();

        $this->jobCards->remove($id);
        $this->jobCards->add($jobCard);

        $jobCard->releaseEvents();
    }
}
