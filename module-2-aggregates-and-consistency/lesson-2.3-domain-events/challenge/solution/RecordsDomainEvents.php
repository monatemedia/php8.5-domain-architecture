<?php
declare(strict_types=1);

namespace Workshop;

/**
 * Gives an aggregate root somewhere to put the facts it announces.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * WHY A TRAIT AND NOT A BASE CLASS
 * ---------------------------------
 * This is horizontal code sharing between classes with no "is-a" relationship
 * whatsoever. A JobCard is not a kind of CourtesyFleet, and neither is a kind
 * of AggregateRoot in any sense that carries meaning.
 *
 * That is the decision table from Lesson 1.2 of the previous course, and the
 * practical test from Rule 4: an abstract base class here would spend the one
 * inheritance slot every aggregate has, forever, on four lines of array
 * handling.
 *
 * WHY recordThat() IS PROTECTED AND releaseEvents() IS PUBLIC
 * -----------------------------------------------------------
 * Recording is something an aggregate does to itself, from inside a business
 * operation. A public recordThat() would let any caller announce that a job
 * card had been invoiced without invoicing it — a claim about the past that
 * never happened, dispatched to handlers that will act on it.
 *
 * Releasing is something the outside must do, once, after the commit.
 */
trait RecordsDomainEvents
{
    /** @var list<DomainEvent> in the order they happened */
    private array $recordedEvents = [];

    /**
     * Note where this is NOT called from: nothing records an event before its
     * operation has succeeded. Every call in JobCard comes after the guards and
     * after the transition, so a refused operation announces nothing.
     *
     * Getting that wrong is worse than it looks — handlers would act on
     * something that did not happen, and unlike a database write, an email
     * cannot be rolled back.
     */
    protected function recordThat(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * Hand over the events and give them up.
     *
     * THE CLEARING IS THE POINT. An aggregate saved twice in one request is
     * entirely ordinary; dispatching its events twice is a support ticket about
     * a customer receiving two invoices.
     *
     * The name says so. `getEvents()` implies a harmless read and invites a
     * second call; `releaseEvents()` says something is handed over and let go.
     *
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;

        $this->recordedEvents = [];

        return $events;
    }

    /**
     * Useful in a test, and honest about what it does not do: this does not
     * clear, so it can be called freely.
     */
    public function hasRecordedEvents(): bool
    {
        return $this->recordedEvents !== [];
    }
}
