<?php
declare(strict_types=1);

/**
 * checks.php — Lesson 4.3 acceptance checks
 * ==========================================
 *
 * THE SPECIFICATION for:
 *
 *     Workshop\Refusal    one reason, as a value
 *     Workshop\Decision   yes, or every reason why not
 *     Workshop\JobCard    checkCanComplete() and checkCanInvoice()
 *
 * DO NOT EDIT.
 *
 * Section 5 is the lesson. It runs both paths over every reachable state and
 * asserts they agree — the drift that every result-type retrofit eventually
 * produces, tested for rather than hoped about.
 */

use Workshop\AccountHolderId;
use Workshop\CarriesContext;
use Workshop\Currency;
use Workshop\Decision;
use Workshop\IllegalStatusTransition;
use Workshop\JobCard;
use Workshop\JobCardId;
use Workshop\JobCardStatus;
use Workshop\LabourLine;
use Workshop\LabourUnits;
use Workshop\Money;
use Workshop\OutstandingAuthorisations;
use Workshop\Refusal;
use Workshop\VehicleId;
use Workshop\WorkshopFailure;

acceptance('Lesson 4.3 — Exceptions vs result types');


// ═══════════════════════════════════════════════════════════════════════════
// Helpers — a factory per reachable state, so every assertion gets a fresh one
// ═══════════════════════════════════════════════════════════════════════════

function aJobCard(string $id = 'JC-1001'): JobCard
{
    return JobCard::open(
        JobCardId::fromString($id),
        VehicleId::fromString('VEH-4471'),
        AccountHolderId::fromString('AH-0007'),
        Currency::EUR
    );
}

function aLabourLine(): LabourLine
{
    return LabourLine::create('Diagnostics', LabourUnits::fromMinutes(30), Money::of(900, Currency::EUR));
}

/**
 * Every state a job card can reach, as a factory.
 *
 * @return array<string, callable(): JobCard>
 */
function everyState(): array
{
    return [
        'Booked' => static fn(): JobCard => aJobCard(),

        'InProgress' => static function (): JobCard {
            $jobCard = aJobCard();
            $jobCard->startWork();

            return $jobCard;
        },

        'AwaitingAuthorisation' => static function (): JobCard {
            $jobCard = aJobCard();
            $jobCard->startWork();
            $jobCard->discoverAdditionalWork('Rear discs below minimum thickness');

            return $jobCard;
        },

        'AwaitingParts' => static function (): JobCard {
            $jobCard = aJobCard();
            $jobCard->startWork();
            $jobCard->awaitParts();

            return $jobCard;
        },

        'Completed' => static function (): JobCard {
            $jobCard = aJobCard();
            $jobCard->startWork();
            $jobCard->complete();

            return $jobCard;
        },

        'Invoiced' => static function (): JobCard {
            $jobCard = aJobCard();
            $jobCard->startWork();
            $jobCard->complete();
            $jobCard->invoice();

            return $jobCard;
        },

        'Cancelled' => static function (): JobCard {
            $jobCard = aJobCard();
            $jobCard->cancel();

            return $jobCard;
        },
    ];
}

function captured(callable $fn): ?\Throwable
{
    try {
        $fn();
    } catch (\Throwable $e) {
        return $e;
    }

    return null;
}

/** @return list<string> attribute names on a method */
function attributesOn(string $class, string $method): array
{
    return array_map(
        static fn(ReflectionAttribute $a): string => $a->getName(),
        (new ReflectionMethod($class, $method))->getAttributes()
    );
}


// ═══════════════════════════════════════════════════════════════════════════
// 1 — Refusal is a value
// ═══════════════════════════════════════════════════════════════════════════

check('Workshop\Refusal exists, is final and readonly',
    fn() => class_exists(Refusal::class)
        && (new ReflectionClass(Refusal::class))->isFinal()
        && (new ReflectionClass(Refusal::class))->isReadOnly());

check('Refusal is declared in domain/workshop/',
    fn() => declared_in(Refusal::class, 'domain/workshop'));

check('Refusal::from() takes a CarriesContext, not a bare \Throwable',
    function (): bool {
        $parameter = (new ReflectionMethod(Refusal::class, 'from'))->getParameters()[0] ?? null;

        return (string) $parameter?->getType() === CarriesContext::class;
    });

check('Refusal::from() copies the code, message and context off the failure',
    function (): bool {
        $failure = OutstandingAuthorisations::on(
            JobCardId::fromString('JC-1042'),
            ['Rear discs below minimum thickness']
        );

        $refusal = Refusal::from($failure);

        return $refusal->code === $failure->failureCode()
            && $refusal->message === $failure->getMessage()
            && $refusal->context === $failure->context();
    });

check('Refusal::raise() is declared `never`',
    fn() => (string) (new ReflectionMethod(Refusal::class, 'raise'))->getReturnType() === 'never');

check('Refusal::raise() throws the SAME object it was built from',
    function (): bool {
        $failure = OutstandingAuthorisations::on(JobCardId::fromString('JC-1042'), ['x']);
        $thrown  = captured(static fn() => Refusal::from($failure)->raise());

        return $thrown === $failure;
    });

check('Refusal is NOT itself throwable',
    fn() => !is_a(Refusal::class, Throwable::class, true));

check('Refusal cannot be constructed directly — only through from()',
    fn() => !(new ReflectionMethod(Refusal::class, '__construct'))->isPublic());


// ═══════════════════════════════════════════════════════════════════════════
// 2 — Decision
// ═══════════════════════════════════════════════════════════════════════════

check('Workshop\Decision exists, is final and readonly',
    fn() => class_exists(Decision::class)
        && (new ReflectionClass(Decision::class))->isFinal()
        && (new ReflectionClass(Decision::class))->isReadOnly());

check('Decision::allowed() is allowed and holds no refusals',
    fn() => Decision::allowed()->isAllowed()
        && !Decision::allowed()->isRefused()
        && Decision::allowed()->refusals === []);

check('Decision::refusedBecause() is refused',
    function (): bool {
        $decision = Decision::refusedBecause(
            Refusal::from(OutstandingAuthorisations::on(JobCardId::fromString('JC-1'), ['x']))
        );

        return $decision->isRefused() && !$decision->isAllowed();
    });

check('Decision::refusedBecause() requires at least one Refusal',
    fn() => (new ReflectionMethod(Decision::class, 'refusedBecause'))->getNumberOfRequiredParameters() >= 1);

check('codes() returns the failure codes in order',
    function (): bool {
        $decision = Decision::refusedBecause(
            Refusal::from(OutstandingAuthorisations::on(JobCardId::fromString('JC-1'), ['x'])),
            Refusal::from(IllegalStatusTransition::from(
                JobCardId::fromString('JC-1'),
                JobCardStatus::AwaitingAuthorisation,
                JobCardStatus::Completed
            ))
        );

        return $decision->codes() === ['outstanding-authorisations', 'illegal-status-transition'];
    });

check('orThrow() on an allowed Decision does nothing',
    fn() => captured(static fn() => Decision::allowed()->orThrow()) === null);

check('orThrow() throws the FIRST refusal and discards the rest',
    function (): bool {
        $first  = OutstandingAuthorisations::on(JobCardId::fromString('JC-1'), ['x']);
        $second = IllegalStatusTransition::from(
            JobCardId::fromString('JC-1'),
            JobCardStatus::AwaitingAuthorisation,
            JobCardStatus::Completed
        );

        $thrown = captured(static fn() => Decision::refusedBecause(
            Refusal::from($first),
            Refusal::from($second)
        )->orThrow());

        return $thrown === $first;
    });

check('Both Decision factories carry #[\NoDiscard]',
    fn() => in_array('NoDiscard', attributesOn(Decision::class, 'allowed'), true)
        && in_array('NoDiscard', attributesOn(Decision::class, 'refusedBecause'), true));


// ═══════════════════════════════════════════════════════════════════════════
// 3 — JobCard can be asked as well as told
// ═══════════════════════════════════════════════════════════════════════════

check('JobCard::checkCanComplete() returns a Decision',
    fn() => (string) (new ReflectionMethod(JobCard::class, 'checkCanComplete'))->getReturnType()
        === Decision::class);

check('JobCard::checkCanInvoice() returns a Decision',
    fn() => (string) (new ReflectionMethod(JobCard::class, 'checkCanInvoice'))->getReturnType()
        === Decision::class);

check('Both check methods carry #[\NoDiscard]',
    fn() => in_array('NoDiscard', attributesOn(JobCard::class, 'checkCanComplete'), true)
        && in_array('NoDiscard', attributesOn(JobCard::class, 'checkCanInvoice'), true));

check('A job card In Progress may be completed',
    fn() => everyState()['InProgress']()->checkCanComplete()->isAllowed());

check('A job card Booked may not',
    fn() => everyState()['Booked']()->checkCanComplete()->isRefused());

check('Asking does not change anything',
    function (): bool {
        $jobCard = everyState()['Booked']();

        // Assigned rather than discarded, deliberately: #[\NoDiscard] would
        // emit a warning on a bare `$jobCard->checkCanComplete();` — which is
        // the attribute doing its job, and not something to print in the
        // middle of an acceptance run. Try it in your own copy.
        $completing = $jobCard->checkCanComplete();
        $invoicing  = $jobCard->checkCanInvoice();

        return $completing->isRefused()
            && $invoicing->isRefused()
            && $jobCard->status() === JobCardStatus::Booked
            && $jobCard->hasRecordedEvents();
    });


// ═══════════════════════════════════════════════════════════════════════════
// 4 — THE THING AN EXCEPTION CANNOT DO: two reasons at once
// ═══════════════════════════════════════════════════════════════════════════

check('Awaiting Authorisation with an outstanding item is refused for TWO reasons',
    fn() => count(everyState()['AwaitingAuthorisation']()->checkCanComplete()->refusals) === 2);

check('...the outstanding work, and the illegal transition, in that order',
    fn() => everyState()['AwaitingAuthorisation']()->checkCanComplete()->codes()
        === ['outstanding-authorisations', 'illegal-status-transition']);

check('...and complete() reports only the first, which is the cost being measured',
    function (): bool {
        $thrown = captured(static fn() => everyState()['AwaitingAuthorisation']()->complete());

        return $thrown instanceof OutstandingAuthorisations;
    });

check('Every refusal carries the same context the exception would have',
    function (): bool {
        $decision = everyState()['AwaitingAuthorisation']()->checkCanComplete();

        return $decision->refusals[0]->context['descriptions']
                === ['Rear discs below minimum thickness']
            && $decision->refusals[1]->context['from'] === 'awaiting_authorisation';
    });

check('Every refusal context is JSON-safe',
    fn() => count(array_filter(
        everyState()['AwaitingAuthorisation']()->checkCanComplete()->refusals,
        static fn(Refusal $r): bool => json_encode($r->context) !== false
    )) === 2);


// ═══════════════════════════════════════════════════════════════════════════
// 5 — THE DRIFT TEST
// ═══════════════════════════════════════════════════════════════════════════
//
// The ask-path and the act-path must agree, in EVERY reachable state. A check
// that says yes while the operation refuses is the failure this pattern is
// famous for, and it is the one thing worth asserting mechanically rather than
// reasoning about.
//
// Note what makes it pass in the reference implementation: complete() is
// written as `$this->checkCanComplete()->orThrow()`, so there is one list of
// guards and the two cannot disagree. The test is still worth writing — that
// implementation is a decision somebody can undo next week by adding an `if`
// above the orThrow(), and this is what would notice.

/**
 * @return array{agree: int, total: int, mismatches: list<string>}
 */
function drift(string $ask, string $act): array
{
    $agree      = 0;
    $total      = 0;
    $mismatches = [];

    foreach (everyState() as $state => $make) {
        $total++;

        $allowed = $make()->{$ask}()->isAllowed();
        $thrown  = captured(static fn() => $make()->{$act}());

        if ($allowed === ($thrown === null)) {
            $agree++;
        } else {
            $mismatches[] = $state . ': ' . $ask . ' said '
                . ($allowed ? 'yes' : 'no') . ' but ' . $act . ' '
                . ($thrown === null ? 'succeeded' : 'threw ' . $thrown::class);
        }
    }

    return ['agree' => $agree, 'total' => $total, 'mismatches' => $mismatches];
}

$completeDrift = drift('checkCanComplete', 'complete');
$invoiceDrift  = drift('checkCanInvoice', 'invoice');

check('checkCanComplete() and complete() agree in all seven states',
    fn() => $completeDrift['agree'] === $completeDrift['total']
        && $completeDrift['mismatches'] === []);

check('checkCanInvoice() and invoice() agree in all seven states',
    fn() => $invoiceDrift['agree'] === $invoiceDrift['total']
        && $invoiceDrift['mismatches'] === []);

check('Where both refuse, the thrown failure is the FIRST refusal\'s code',
    function (): bool {
        foreach (everyState() as $make) {
            $decision = $make()->checkCanComplete();

            if ($decision->isAllowed()) {
                continue;
            }

            $thrown = captured(static fn() => $make()->complete());

            if (!$thrown instanceof CarriesContext
                || $thrown->failureCode() !== $decision->codes()[0]) {
                return false;
            }
        }

        return true;
    });

check('Same for invoicing',
    function (): bool {
        foreach (everyState() as $make) {
            $decision = $make()->checkCanInvoice();

            if ($decision->isAllowed()) {
                continue;
            }

            $thrown = captured(static fn() => $make()->invoice());

            if (!$thrown instanceof CarriesContext
                || $thrown->failureCode() !== $decision->codes()[0]) {
                return false;
            }
        }

        return true;
    });

check('Exactly one state permits completing, and one permits invoicing',
    function (): bool {
        $canComplete = array_filter(everyState(), static fn(callable $m): bool => $m()->checkCanComplete()->isAllowed());
        $canInvoice  = array_filter(everyState(), static fn(callable $m): bool => $m()->checkCanInvoice()->isAllowed());

        return array_keys($canComplete) === ['InProgress']
            && array_keys($canInvoice) === ['Completed'];
    });


// ═══════════════════════════════════════════════════════════════════════════
// 6 — Everything a Decision throws is still in the tree
// ═══════════════════════════════════════════════════════════════════════════

check('Every failure raised through orThrow() is a WorkshopFailure',
    function (): bool {
        foreach (everyState() as $make) {
            $decision = $make()->checkCanComplete();

            if ($decision->isAllowed()) {
                continue;
            }

            $thrown = captured(static fn() => $decision->orThrow());

            if (!$thrown instanceof WorkshopFailure) {
                return false;
            }
        }

        return true;
    });

check('Refusals do not leak internal class names into their codes',
    function (): bool {
        foreach (everyState() as $make) {
            foreach ($make()->checkCanComplete()->refusals as $refusal) {
                if (str_contains($refusal->code, '\\')
                    || preg_match('/^[a-z]+(-[a-z]+)*$/', $refusal->code) !== 1) {
                    return false;
                }
            }
        }

        return true;
    });


// ═══════════════════════════════════════════════════════════════════════════
// 7 — Behaviour is unchanged. Again.
// ═══════════════════════════════════════════════════════════════════════════

check('A legal lifecycle still runs end to end',
    function (): bool {
        $jobCard = aJobCard('JC-7001');
        $jobCard->startWork();
        $jobCard->addLine(aLabourLine());
        $jobCard->complete();
        $jobCard->invoice();

        return $jobCard->status() === JobCardStatus::Invoiced;
    });

check('complete() still records JobCardCompleted, and only on success',
    function (): bool {
        $refused = everyState()['Booked']();
        captured(static fn() => $refused->complete());

        $succeeded = everyState()['InProgress']();
        $succeeded->complete();

        return count($refused->releaseEvents()) === 1        // opened only
            && count($succeeded->releaseEvents()) === 2;     // opened + completed
    });

check('A refused complete() still changes nothing',
    function (): bool {
        $jobCard = everyState()['AwaitingAuthorisation']();
        captured(static fn() => $jobCard->complete());

        return $jobCard->status() === JobCardStatus::AwaitingAuthorisation
            && $jobCard->pendingAuthorisations() !== [];
    });

check('Lesson 4.2\'s exception types are unchanged',
    fn() => throws(OutstandingAuthorisations::class,
        static fn() => everyState()['AwaitingAuthorisation']()->complete())
        && throws(IllegalStatusTransition::class,
        static fn() => everyState()['Booked']()->invoice()));


acceptance_report();
