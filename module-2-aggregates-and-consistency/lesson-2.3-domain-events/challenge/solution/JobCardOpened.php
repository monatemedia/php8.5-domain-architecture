<?php
declare(strict_types=1);

namespace Workshop;

/**
 * A vehicle was booked in and a job card was opened for it.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * `final readonly` — the least controversial readonly in the course. A fact
 * about the past does not change.
 *
 * It carries three identities and no objects. A handler indexing job cards, or
 * notifying the account holder, has everything it needs without loading
 * anything; and because these are values rather than references, the event
 * still describes the world as it was if it is handled from a queue next
 * Tuesday.
 */
final readonly class JobCardOpened implements DomainEvent
{
    public function __construct(
        public JobCardId       $jobCardId,
        public VehicleId       $vehicleId,
        public AccountHolderId $accountHolderId,
    ) {}

    public function eventName(): string
    {
        return 'job_card.opened';
    }
}
