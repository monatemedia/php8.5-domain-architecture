<?php
declare(strict_types=1);

namespace Workshop;

/**
 * A completed job card was turned into a bill.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * WHY THE TOTAL IS IN THE EVENT
 * ------------------------------
 * The job card's total is DERIVED — recomputed from its lines on every call,
 * which Lesson 2.1 section 6 argues at length. That is safe today because Money
 * is immutable and every line froze its own rate.
 *
 * "Safe today" is not the same as "safe forever", and this event is where the
 * difference shows. The amount that was BILLED is a fact about a moment; the
 * amount the aggregate would compute is a fact about now. Carrying the total
 * here means an accounting handler records what was actually charged, even if
 * the calculation changes in five years.
 *
 * An event carrying only the id, whose handler then loads the aggregate and
 * asks for its total, is subtly different in a way that only bites once — and
 * it bites in the accounts.
 */
final readonly class JobCardInvoiced implements DomainEvent
{
    public function __construct(
        public JobCardId $jobCardId,
        public Money     $total,
    ) {}

    public function eventName(): string
    {
        return 'job_card.invoiced';
    }
}
