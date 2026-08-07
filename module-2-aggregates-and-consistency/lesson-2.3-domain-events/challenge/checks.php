<?php
declare(strict_types=1);

/**
 * checks.php — Lesson 2.3 acceptance checks
 * ==========================================
 *
 * THE SPECIFICATION for DomainEvent, RecordsDomainEvents, the three job card
 * events, and the addition to JobCard.
 *
 * DO NOT EDIT.
 */

use Workshop\AccountHolderId;
use Workshop\Currency;
use Workshop\DomainEvent;
use Workshop\JobCard;
use Workshop\JobCardCompleted;
use Workshop\JobCardId;
use Workshop\JobCardInvoiced;
use Workshop\JobCardOpened;
use Workshop\JobCardStatus;
use Workshop\LabourLine;
use Workshop\LabourUnits;
use Workshop\Money;
use Workshop\RecordsDomainEvents;
use Workshop\VehicleId;

acceptance('Lesson 2.3 — Domain events');


// ═══════════════════════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════════════════════

function anOpenJobCard(): JobCard
{
    return JobCard::open(
        JobCardId::fromString('JC-1001'),
        VehicleId::fromString('VEH-4471'),
        AccountHolderId::fromString('AH-0007'),
        Currency::EUR
    );
}

/** 15 tenths x 150 = 2250. */
function aLabourLine(): LabourLine
{
    return LabourLine::create('Rear brake discs', LabourUnits::fromMinutes(90), Money::of(150, Currency::EUR));
}

/** Booked -> InProgress -> Completed, one line, events already drained. */
function aCompletedJobCard(): JobCard
{
    $jobCard = anOpenJobCard();
    $jobCard->startWork();
    $jobCard->addLine(aLabourLine());
    $jobCard->complete();
    $jobCard->releaseEvents();

    return $jobCard;
}


// ═══════════════════════════════════════════════════════════════════════════
// DomainEvent
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\DomainEvent is an interface',
    static fn(): bool => interface_exists(DomainEvent::class)
);

check(
    'DomainEvent declares exactly eventName()',
    static function (): bool {
        $methods = array_map(
            static fn(ReflectionMethod $m): string => $m->getName(),
            (new ReflectionClass(DomainEvent::class))->getMethods()
        );
        return $methods === ['eventName'];
    }
);

check(
    'DomainEvent does NOT declare occurredAt() — the domain has no clock',
    static fn(): bool => !method_exists(DomainEvent::class, 'occurredAt')
);


// ═══════════════════════════════════════════════════════════════════════════
// RecordsDomainEvents
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Workshop\RecordsDomainEvents is a trait',
    static fn(): bool => trait_exists(RecordsDomainEvents::class)
);

check(
    'It is declared in domain/workshop/RecordsDomainEvents.php',
    static function (): bool {
        if (!trait_exists(RecordsDomainEvents::class)) {
            return false;
        }
        $file = (new ReflectionClass(RecordsDomainEvents::class))->getFileName();
        if ($file === false) {
            return false;
        }
        $normalised = str_replace('\\', '/', $file);
        return str_contains($normalised, '/challenge/solution/')
            || str_contains($normalised, 'domain/workshop/RecordsDomainEvents.php');
    }
);

check(
    'recordThat() is protected — nothing outside may announce a fact',
    static function (): bool {
        $method = new ReflectionMethod(RecordsDomainEvents::class, 'recordThat');
        return $method->isProtected();
    }
);

check(
    'releaseEvents() is public',
    static fn(): bool => (new ReflectionMethod(RecordsDomainEvents::class, 'releaseEvents'))->isPublic()
);


// ═══════════════════════════════════════════════════════════════════════════
// The three events
// ═══════════════════════════════════════════════════════════════════════════

check(
    'All three event classes exist and implement DomainEvent',
    static function (): bool {
        foreach ([JobCardOpened::class, JobCardCompleted::class, JobCardInvoiced::class] as $class) {
            if (!class_exists($class) || !is_subclass_of($class, DomainEvent::class)) {
                return false;
            }
        }
        return true;
    }
);

check(
    'All three are final readonly — a fact about the past does not change',
    static function (): bool {
        foreach ([JobCardOpened::class, JobCardCompleted::class, JobCardInvoiced::class] as $class) {
            $r = new ReflectionClass($class);
            if (!$r->isFinal() || !$r->isReadOnly()) {
                return false;
            }
        }
        return true;
    }
);

check(
    'eventName() is past tense, dotted and stable',
    static function (): bool {
        $opened    = new JobCardOpened(
            JobCardId::fromString('JC-1'),
            VehicleId::fromString('VEH-1'),
            AccountHolderId::fromString('AH-1')
        );
        $completed = new JobCardCompleted(JobCardId::fromString('JC-1'));
        $invoiced  = new JobCardInvoiced(JobCardId::fromString('JC-1'), Money::zero(Currency::EUR));

        return $opened->eventName() === 'job_card.opened'
            && $completed->eventName() === 'job_card.completed'
            && $invoiced->eventName() === 'job_card.invoiced';
    }
);

check(
    'JobCardOpened carries three identities',
    static function (): bool {
        $event = new JobCardOpened(
            JobCardId::fromString('JC-1'),
            VehicleId::fromString('VEH-1'),
            AccountHolderId::fromString('AH-1')
        );
        return $event->jobCardId->value() === 'JC-1'
            && $event->vehicleId->value() === 'VEH-1'
            && $event->accountHolderId->value() === 'AH-1';
    }
);

check(
    'JobCardCompleted carries the job card id and nothing else',
    static function (): bool {
        $params = (new ReflectionMethod(JobCardCompleted::class, '__construct'))->getParameters();
        return count($params) === 1
            && (string) $params[0]->getType() === 'Workshop\JobCardId';
    }
);

check(
    'JobCardInvoiced carries the job card id and the total',
    static function (): bool {
        $event = new JobCardInvoiced(JobCardId::fromString('JC-1'), Money::of(10450, Currency::EUR));
        return $event->jobCardId->value() === 'JC-1'
            && $event->total->amountMinor() === 10450;
    }
);

check(
    'NO event carries the JobCard aggregate itself',
    static function (): bool {
        foreach ([JobCardOpened::class, JobCardCompleted::class, JobCardInvoiced::class] as $class) {
            foreach ((new ReflectionMethod($class, '__construct'))->getParameters() as $param) {
                if ((string) $param->getType() === 'Workshop\JobCard') {
                    return false;
                }
            }
        }
        return true;
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// JobCard — the addition
// ═══════════════════════════════════════════════════════════════════════════

check(
    'JobCard uses the RecordsDomainEvents trait',
    static fn(): bool => in_array(
        RecordsDomainEvents::class,
        (new ReflectionClass(JobCard::class))->getTraitNames(),
        true
    )
);

check(
    'JobCard STILL has no dispatcher: open() takes the same four arguments',
    static function (): bool {
        $params = (new ReflectionMethod(JobCard::class, 'open'))->getParameters();
        $types  = array_map(static fn(ReflectionParameter $p): string => (string) $p->getType(), $params);

        return $types === [
            'Workshop\JobCardId',
            'Workshop\VehicleId',
            'Workshop\AccountHolderId',
            'Workshop\Currency',
        ];
    }
);

check(
    'open() records JobCardOpened, carrying what it was given',
    static function (): bool {
        $events = anOpenJobCard()->releaseEvents();

        if (count($events) !== 1 || !$events[0] instanceof JobCardOpened) {
            return false;
        }

        return $events[0]->jobCardId->value() === 'JC-1001'
            && $events[0]->vehicleId->value() === 'VEH-4471'
            && $events[0]->accountHolderId->value() === 'AH-0007';
    }
);

check(
    'complete() records JobCardCompleted',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $jobCard->startWork();
        $jobCard->releaseEvents();

        $jobCard->complete();
        $events = $jobCard->releaseEvents();

        return count($events) === 1
            && $events[0] instanceof JobCardCompleted
            && $events[0]->jobCardId->value() === 'JC-1001';
    }
);

check(
    'invoice() records JobCardInvoiced, with the total AT THAT MOMENT',
    static function (): bool {
        $jobCard = aCompletedJobCard();
        $jobCard->invoice();

        $events = $jobCard->releaseEvents();

        return count($events) === 1
            && $events[0] instanceof JobCardInvoiced
            && $events[0]->total->amountMinor() === 2250;
    }
);

check(
    'startWork(), awaitParts() and cancel() record nothing — not every transition is news',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $jobCard->releaseEvents();

        $jobCard->startWork();
        $jobCard->awaitParts();
        $jobCard->partsArrived();
        $jobCard->cancel();

        return $jobCard->releaseEvents() === [];
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// Recording happens only when the operation succeeded
// ═══════════════════════════════════════════════════════════════════════════

check(
    'A REFUSED invoice() records nothing',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $jobCard->startWork();
        $jobCard->releaseEvents();

        try {
            $jobCard->invoice();   // In Progress cannot become Invoiced
        } catch (DomainException) {
            // expected
        }

        return $jobCard->releaseEvents() === [];
    }
);

check(
    'A REFUSED complete() records nothing',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $jobCard->startWork();
        $jobCard->discoverAdditionalWork('Rear discs');
        $jobCard->releaseEvents();

        try {
            $jobCard->complete();   // I8: work awaits authorisation
        } catch (DomainException) {
            // expected
        }

        return $jobCard->releaseEvents() === [];
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// releaseEvents()
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Events come back in the order they happened',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $jobCard->startWork();
        $jobCard->addLine(aLabourLine());
        $jobCard->complete();
        $jobCard->invoice();

        $events = $jobCard->releaseEvents();

        return count($events) === 3
            && $events[0] instanceof JobCardOpened
            && $events[1] instanceof JobCardCompleted
            && $events[2] instanceof JobCardInvoiced;
    }
);

check(
    'releaseEvents() CLEARS — a second call returns nothing',
    static function (): bool {
        $jobCard = anOpenJobCard();

        return count($jobCard->releaseEvents()) === 1
            && $jobCard->releaseEvents() === [];
    }
);

check(
    'releaseEvents() returns a list',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $jobCard->startWork();
        $jobCard->addLine(aLabourLine());
        $jobCard->complete();

        return array_keys($jobCard->releaseEvents()) === [0, 1];
    }
);

check(
    'hasRecordedEvents() reports without clearing',
    static function (): bool {
        $jobCard = anOpenJobCard();

        return $jobCard->hasRecordedEvents() === true
            && $jobCard->hasRecordedEvents() === true
            && count($jobCard->releaseEvents()) === 1
            && $jobCard->hasRecordedEvents() === false;
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// Lesson 2.1 is undisturbed
// ═══════════════════════════════════════════════════════════════════════════

check(
    'The 2.1 lifecycle still works end to end',
    static function (): bool {
        $jobCard = anOpenJobCard();
        $jobCard->startWork();
        $jobCard->addLine(aLabourLine());
        $jobCard->discoverAdditionalWork('Rear discs below minimum thickness');
        $jobCard->authoriseAdditionalWork();
        $jobCard->complete();
        $jobCard->invoice();

        return $jobCard->status() === JobCardStatus::Invoiced
            && $jobCard->total()->amountMinor() === 2250;
    }
);

check(
    'JobCard is still final and NOT readonly, with no public "set" methods',
    static function (): bool {
        $r = new ReflectionClass(JobCard::class);

        if (!$r->isFinal() || $r->isReadOnly()) {
            return false;
        }

        foreach ($r->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with(strtolower($method->getName()), 'set')) {
                return false;
            }
        }

        return true;
    }
);

check(
    'RULE 1: nothing in this lesson imports outside the Workshop namespace',
    static function (): bool {
        $subjects = [
            DomainEvent::class, RecordsDomainEvents::class, JobCardOpened::class,
            JobCardCompleted::class, JobCardInvoiced::class, JobCard::class,
        ];

        foreach ($subjects as $subject) {
            $file = (new ReflectionClass($subject))->getFileName();
            if ($file === false) {
                return false;
            }

            $source = (string) file_get_contents($file);
            preg_match_all(
                '/^use\s+(?:function\s+|const\s+)?([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)/m',
                $source,
                $matches
            );

            foreach ($matches[1] as $imported) {
                $imported = ltrim($imported, '\\');
                if (!str_contains($imported, '\\')) {
                    continue;
                }
                if (str_starts_with($imported, 'Workshop\\')) {
                    continue;
                }
                return false;
            }
        }

        return true;
    }
);


acceptance_report();
