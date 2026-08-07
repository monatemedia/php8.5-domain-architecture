<?php
declare(strict_types=1);

namespace Workshop;

/**
 * Bill it.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * Structurally identical to CompleteJobCard, including the `remove()`/`add()`
 * it is forced into, and that repetition is worth noticing rather than
 * refactoring away: two use cases, both doing load-change-store, both writing
 * the same three lines because the interface gives them nowhere else to put it.
 *
 * Duplication that appears in the second place is a coincidence. Duplication
 * that appears in the second AND third place is a missing abstraction — and
 * here the missing abstraction has a name, `save()`, and a lesson that argued
 * about it.
 */
final readonly class InvoiceJobCard
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

        $jobCard->invoice();

        $this->jobCards->remove($id);
        $this->jobCards->add($jobCard);

        $jobCard->releaseEvents();
    }
}
