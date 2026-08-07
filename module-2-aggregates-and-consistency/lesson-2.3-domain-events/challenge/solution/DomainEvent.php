<?php
declare(strict_types=1);

namespace Workshop;

/**
 * Something that happened in the domain, stated as a fact.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * ONE METHOD, AND WHAT IS DELIBERATELY ABSENT
 * --------------------------------------------
 * eventName() gives a stable string a dispatcher can subscribe against, log,
 * or put on a queue, without every consumer needing the class.
 *
 * Absent, on purpose:
 *
 *   occurredAt()  'now' is the outside world. An event that fetches the system
 *                 clock has a dependency nothing declares and nobody can
 *                 substitute. If the time is EVIDENCE, infrastructure stamps it
 *                 on release; if it is INPUT TO A DECISION, it is a parameter.
 *                 README section 5 argues all three options.
 *
 *   payload()     an array-shaped accessor is a serialisation concern, and
 *                 serialisation is infrastructure. Add it in the layer that
 *                 needs it, where the format can change without touching the
 *                 domain.
 *
 *   aggregateId() tempting, and it forces every event to have exactly one
 *                 subject in a single type. JobCardOpened legitimately names
 *                 three things. Let each event declare what it carries.
 *
 * Every one of those is a method somebody will eventually want. Adding one is
 * easy; removing it from an interface other people implement is not — which is
 * the same argument as "do not put speculative fields in an event", applied one
 * level up.
 */
interface DomainEvent
{
    /**
     * A stable name, in the past tense, for what happened.
     *
     * Dotted and snake_cased — 'job_card.invoiced' — because it is going into
     * logs, queue routing keys and configuration, none of which want a PHP
     * class name in them.
     */
    public function eventName(): string;
}
