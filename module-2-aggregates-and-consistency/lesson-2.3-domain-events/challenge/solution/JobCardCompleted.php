<?php
declare(strict_types=1);

namespace Workshop;

/**
 * The agreed work is finished and the vehicle is ready.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * The smallest event in the course, and it carries exactly what its most
 * important handler needs: the courtesy-vehicle handler from Lesson 2.2 calls
 * CourtesyFleet::returnFromJobCard($jobCardId) and needs nothing else.
 *
 * The temptation is to add the vehicle id, the account holder, the total —
 * "somebody might want them". Resist it. An event is a published contract:
 * every field is something a handler may come to depend on, handlers are often
 * written by people you will never meet, and removing a field later is a
 * breaking change to code you cannot grep.
 *
 * Adding one is a five-minute change. That asymmetry is the whole argument.
 */
final readonly class JobCardCompleted implements DomainEvent
{
    public function __construct(public JobCardId $jobCardId) {}

    public function eventName(): string
    {
        return 'job_card.completed';
    }
}
