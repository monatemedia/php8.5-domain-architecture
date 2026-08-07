<?php
declare(strict_types=1);

/**
 * checks.php — Lesson 4.2 acceptance checks
 * ==========================================
 *
 * THE SPECIFICATION for the workshop's exception tree.
 *
 *     Workshop\CarriesContext                interface: failureCode() + context()
 *     Workshop\WorkshopException             abstract, under \DomainException
 *     Workshop\InvalidWorkshopInput          abstract, under \InvalidArgumentException
 *     Workshop\JobCardException              abstract, holds the identity
 *
 *     Workshop\JobCardNotFound               re-parented
 *     Workshop\JobCardAlreadyExists          re-parented
 *     Workshop\IllegalStatusTransition       new
 *     Workshop\JobCardIsTerminal             new
 *     Workshop\LineNotFound                  new
 *     Workshop\LineCurrencyMismatch          new
 *     Workshop\NothingAwaitingAuthorisation  new
 *     Workshop\OutstandingAuthorisations     new
 *     Workshop\MissingWorkDescription        new, on the other branch
 *
 *     Workshop\JobCard                       seven throw sites renamed
 *     Infrastructure\SqliteJobCards          the last anonymous throw
 *
 * DO NOT EDIT.
 *
 * The checks under "SHAPE OF THE TREE" and "NO ANONYMOUS THROWS" are the
 * lesson. The rest asserts that renaming eleven throws changed no behaviour.
 */

use Infrastructure\InMemoryJobCards;
use Workshop\AccountHolderId;
use Workshop\CarriesContext;
use Workshop\Currency;
use Workshop\IllegalStatusTransition;
use Workshop\InvalidWorkshopInput;
use Workshop\JobCard;
use Workshop\JobCardAlreadyExists;
use Workshop\JobCardException;
use Workshop\JobCardId;
use Workshop\JobCardIsTerminal;
use Workshop\JobCardNotFound;
use Workshop\JobCardStatus;
use Workshop\LabourLine;
use Workshop\LabourUnits;
use Workshop\LineCurrencyMismatch;
use Workshop\LineId;
use Workshop\LineNotFound;
use Workshop\MissingWorkDescription;
use Workshop\Money;
use Workshop\NothingAwaitingAuthorisation;
use Workshop\OutstandingAuthorisations;
use Workshop\VehicleId;
use Workshop\WorkshopException;
use Workshop\WorkshopFailure;

acceptance('Lesson 4.2 — The exception tree');


// ═══════════════════════════════════════════════════════════════════════════
// Helpers
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

function aLabourLine(string $description = 'Diagnostics', string $currency = 'EUR'): LabourLine
{
    return LabourLine::create(
        $description,
        LabourUnits::fromMinutes(30),
        Money::of(900, Currency::from($currency))
    );
}

/** Booked -> In Progress -> Awaiting Authorisation, one item outstanding. */
function aJobCardAwaitingAuthorisation(string $id = 'JC-1001'): JobCard
{
    $jobCard = aJobCard($id);
    $jobCard->startWork();
    $jobCard->discoverAdditionalWork('Rear discs below minimum thickness');

    return $jobCard;
}

/** Booked -> ... -> Invoiced. Terminal. */
function anInvoicedJobCard(string $id = 'JC-1001'): JobCard
{
    $jobCard = aJobCard($id);
    $jobCard->startWork();
    $jobCard->complete();
    $jobCard->invoice();

    return $jobCard;
}

/** Run $fn and hand back whatever it threw, or null. */
function captured(callable $fn): ?\Throwable
{
    try {
        $fn();
    } catch (\Throwable $e) {
        return $e;
    }

    return null;
}

/** Every concrete exception class the tree is supposed to contain. */
function everyLeaf(): array
{
    return [
        JobCardNotFound::class,
        JobCardAlreadyExists::class,
        IllegalStatusTransition::class,
        JobCardIsTerminal::class,
        LineNotFound::class,
        LineCurrencyMismatch::class,
        NothingAwaitingAuthorisation::class,
        OutstandingAuthorisations::class,
        MissingWorkDescription::class,
    ];
}

/** One instance of each leaf, built through its own named constructor. */
function oneOfEach(): array
{
    $id     = JobCardId::fromString('JC-1042');
    $lineId = LineId::fromString('LN-0001');

    return [
        JobCardNotFound::withId($id),
        JobCardAlreadyExists::withId($id),
        IllegalStatusTransition::from($id, JobCardStatus::InProgress, JobCardStatus::Invoiced),
        JobCardIsTerminal::cannot($id, 'add a line to', JobCardStatus::Invoiced),
        LineNotFound::on($id, $lineId),
        LineCurrencyMismatch::between($id, Currency::EUR, 'Front pads', Currency::GBP),
        NothingAwaitingAuthorisation::on($id),
        OutstandingAuthorisations::on($id, ['Rear discs below minimum thickness']),
        MissingWorkDescription::forJobCard($id),
    ];
}

/** True when every value in $context is JSON-safe: scalar, null, or a list of those. */
function jsonSafe(array $context): bool
{
    foreach ($context as $key => $value) {
        if (!is_string($key)) {
            return false;
        }

        if (is_array($value)) {
            if (!array_is_list($value)) {
                return false;
            }

            foreach ($value as $item) {
                if ($item !== null && !is_scalar($item)) {
                    return false;
                }
            }

            continue;
        }

        if ($value !== null && !is_scalar($value)) {
            return false;
        }
    }

    return true;
}


// ═══════════════════════════════════════════════════════════════════════════
// 1 — SHAPE OF THE TREE
// ═══════════════════════════════════════════════════════════════════════════

check('Workshop\CarriesContext is an interface extending WorkshopFailure',
    fn() => interface_exists(CarriesContext::class)
        && is_a(CarriesContext::class, WorkshopFailure::class, true));

check('CarriesContext declares failureCode() and context(), and nothing else',
    function (): bool {
        $own = array_map(
            static fn(ReflectionMethod $m): string => $m->getName(),
            (new ReflectionClass(CarriesContext::class))->getMethods()
        );

        $inherited = array_map(
            static fn(ReflectionMethod $m): string => $m->getName(),
            (new ReflectionClass(Throwable::class))->getMethods()
        );

        $added = array_values(array_diff($own, $inherited));
        sort($added);

        return $added === ['context', 'failureCode'];
    });

check('WorkshopFailure is STILL empty — Lesson 4.1 froze it',
    function (): bool {
        $own = (new ReflectionClass(WorkshopFailure::class))->getMethods();

        return count($own) === count((new ReflectionClass(Throwable::class))->getMethods());
    });

check('Workshop\WorkshopException is abstract',
    fn() => class_exists(WorkshopException::class)
        && (new ReflectionClass(WorkshopException::class))->isAbstract());

check('WorkshopException extends \DomainException and carries context',
    fn() => is_a(WorkshopException::class, DomainException::class, true)
        && is_a(WorkshopException::class, CarriesContext::class, true));

check('Workshop\InvalidWorkshopInput is abstract, under \InvalidArgumentException',
    fn() => class_exists(InvalidWorkshopInput::class)
        && (new ReflectionClass(InvalidWorkshopInput::class))->isAbstract()
        && is_a(InvalidWorkshopInput::class, InvalidArgumentException::class, true)
        && is_a(InvalidWorkshopInput::class, CarriesContext::class, true));

check('The two roots share NO common ancestor class below \LogicException',
    fn() => !is_a(InvalidWorkshopInput::class, WorkshopException::class, true)
        && !is_a(WorkshopException::class, InvalidWorkshopInput::class, true));

check('...but both are WorkshopFailures, which is why the marker exists',
    fn() => is_a(WorkshopException::class, WorkshopFailure::class, true)
        && is_a(InvalidWorkshopInput::class, WorkshopFailure::class, true));

check('Workshop\JobCardException is abstract, under WorkshopException',
    fn() => class_exists(JobCardException::class)
        && (new ReflectionClass(JobCardException::class))->isAbstract()
        && is_a(JobCardException::class, WorkshopException::class, true));

check('Every internal node of the tree is abstract — none can be thrown',
    fn() => count(array_filter(
        [WorkshopException::class, InvalidWorkshopInput::class, JobCardException::class],
        static fn(string $c): bool => (new ReflectionClass($c))->isAbstract()
    )) === 3);

check('Every leaf is final — the tree is closed',
    fn() => count(array_filter(
        everyLeaf(),
        static fn(string $c): bool => class_exists($c) && (new ReflectionClass($c))->isFinal()
    )) === count(everyLeaf()));

check('Eight leaves sit under JobCardException',
    fn() => count(array_filter(
        everyLeaf(),
        static fn(string $c): bool => is_a($c, JobCardException::class, true)
    )) === 8);

check('MissingWorkDescription is the one on the other branch',
    fn() => is_a(MissingWorkDescription::class, InvalidWorkshopInput::class, true)
        && !is_a(MissingWorkDescription::class, WorkshopException::class, true));

check('Every leaf is declared in domain/workshop/',
    fn() => count(array_filter(
        everyLeaf(),
        static fn(string $c): bool => declared_in($c, 'domain/workshop')
    )) === count(everyLeaf()));


// ═══════════════════════════════════════════════════════════════════════════
// 2 — Nothing earlier was broken by re-parenting
// ═══════════════════════════════════════════════════════════════════════════

check('Every leaf is still catchable as a WorkshopFailure',
    fn() => count(array_filter(
        oneOfEach(),
        static fn(Throwable $e): bool => $e instanceof WorkshopFailure
    )) === 9);

check('The eight JobCardExceptions are still \DomainExceptions',
    fn() => count(array_filter(
        oneOfEach(),
        static fn(Throwable $e): bool => $e instanceof DomainException
    )) === 8);

check('MissingWorkDescription is still an \InvalidArgumentException',
    fn() => MissingWorkDescription::forJobCard(JobCardId::fromString('JC-1'))
        instanceof InvalidArgumentException);

check('JobCardNotFound->jobCardId still reads the same from outside',
    function (): bool {
        $id = JobCardId::fromString('JC-9999');

        return JobCardNotFound::withId($id)->jobCardId->equals($id);
    });

check('...and it is no longer nullable',
    function (): bool {
        $property = new ReflectionProperty(JobCardException::class, 'jobCardId');

        return $property->getType()?->allowsNull() === false;
    });


// ═══════════════════════════════════════════════════════════════════════════
// 3 — failureCode(): stable, kebab-case, unique, and not a class name
// ═══════════════════════════════════════════════════════════════════════════

check('Every leaf returns a non-empty failureCode()',
    fn() => count(array_filter(
        oneOfEach(),
        static fn(CarriesContext $e): bool => $e->failureCode() !== ''
    )) === 9);

check('Every failureCode() is kebab-case: lowercase letters and hyphens only',
    fn() => count(array_filter(
        oneOfEach(),
        static fn(CarriesContext $e): bool => preg_match('/^[a-z]+(-[a-z]+)*$/', $e->failureCode()) === 1
    )) === 9);

check('Failure codes are unique across the tree',
    function (): bool {
        $codes = array_map(static fn(CarriesContext $e): string => $e->failureCode(), oneOfEach());

        return count(array_unique($codes)) === count($codes);
    });

check('No failureCode() contains a namespace separator or the word Exception',
    fn() => count(array_filter(
        oneOfEach(),
        static fn(CarriesContext $e): bool => !str_contains($e->failureCode(), '\\')
            && !str_contains(strtolower($e->failureCode()), 'exception')
    )) === 9);

check('failureCode() is not implemented on WorkshopException — every leaf declares one',
    fn() => !(new ReflectionClass(WorkshopException::class))->hasMethod('failureCode')
        || (new ReflectionMethod(WorkshopException::class, 'failureCode'))->isAbstract());


// ═══════════════════════════════════════════════════════════════════════════
// 4 — context(): fields, not sentences
// ═══════════════════════════════════════════════════════════════════════════

check('Every leaf returns a JSON-safe context()',
    fn() => count(array_filter(
        oneOfEach(),
        static fn(CarriesContext $e): bool => jsonSafe($e->context())
    )) === 9);

check('Every context() survives json_encode without loss',
    fn() => count(array_filter(
        oneOfEach(),
        static fn(CarriesContext $e): bool => json_encode($e->context()) !== false
    )) === 9);

check('Every leaf puts jobCardId in its context, on both branches',
    fn() => count(array_filter(
        oneOfEach(),
        static fn(CarriesContext $e): bool => ($e->context()['jobCardId'] ?? null) === 'JC-1042'
    )) === 9);

check('IllegalStatusTransition carries from, to and the allowed list',
    function (): bool {
        $context = IllegalStatusTransition::from(
            JobCardId::fromString('JC-1042'),
            JobCardStatus::InProgress,
            JobCardStatus::Invoiced
        )->context();

        return ($context['from'] ?? null) === 'in_progress'
            && ($context['to'] ?? null) === 'invoiced'
            && is_array($context['allowed'] ?? null)
            && in_array('completed', $context['allowed'], true)
            && !in_array('invoiced', $context['allowed'], true);
    });

check('...and the allowed list matches the enum, not a hand-written copy',
    function (): bool {
        $expected = array_map(
            static fn(JobCardStatus $s): string => $s->value,
            JobCardStatus::InProgress->allowedNext()
        );

        $actual = IllegalStatusTransition::from(
            JobCardId::fromString('JC-1042'),
            JobCardStatus::InProgress,
            JobCardStatus::Invoiced
        )->context()['allowed'];

        return $actual === array_values($expected);
    });

check('The context uses the enum VALUE and the message uses the LABEL',
    function (): bool {
        $exception = IllegalStatusTransition::from(
            JobCardId::fromString('JC-1042'),
            JobCardStatus::InProgress,
            JobCardStatus::Invoiced
        );

        return $exception->context()['from'] === 'in_progress'
            && str_contains($exception->getMessage(), JobCardStatus::InProgress->label());
    });

check('OutstandingAuthorisations carries the descriptions as a LIST, not a sentence',
    function (): bool {
        $context = OutstandingAuthorisations::on(
            JobCardId::fromString('JC-1042'),
            ['Rear discs below minimum thickness', 'Wiper blades perished']
        )->context();

        return ($context['count'] ?? null) === 2
            && is_array($context['descriptions'] ?? null)
            && array_is_list($context['descriptions'])
            && $context['descriptions'][1] === 'Wiper blades perished';
    });

check('LineNotFound carries the line id as a typed property AND in context',
    function (): bool {
        $lineId    = LineId::fromString('LN-0001');
        $exception = LineNotFound::on(JobCardId::fromString('JC-1042'), $lineId);

        return $exception->lineId->equals($lineId)
            && ($exception->context()['lineId'] ?? null) === 'LN-0001';
    });

check('LineCurrencyMismatch carries both currencies',
    function (): bool {
        $context = LineCurrencyMismatch::between(
            JobCardId::fromString('JC-1042'),
            Currency::EUR,
            'Front pads',
            Currency::GBP
        )->context();

        return ($context['jobCardCurrency'] ?? null) === 'EUR'
            && ($context['lineCurrency'] ?? null) === 'GBP';
    });

check('JobCardIsTerminal carries the operation in the workshop\'s words',
    function (): bool {
        $context = JobCardIsTerminal::cannot(
            JobCardId::fromString('JC-1042'),
            'add a line to',
            JobCardStatus::Invoiced
        )->context();

        return ($context['operation'] ?? null) === 'add a line to'
            && ($context['status'] ?? null) === 'invoiced';
    });

check('Both branches spell the identity key the same way',
    function (): bool {
        // MissingWorkDescription sets `jobCardId` itself, because its branch
        // has no node holding an identity. Every JobCardException gets it from
        // the parent. A client must not have to know which is which — if one
        // said `job_card_id`, this would be a bug nobody notices until a
        // consumer does.
        $id = JobCardId::fromString('JC-1042');

        return MissingWorkDescription::forJobCard($id)->context()['jobCardId'] === 'JC-1042'
            && JobCardNotFound::withId($id)->context()['jobCardId'] === 'JC-1042';
    });


// ═══════════════════════════════════════════════════════════════════════════
// 5 — NO ANONYMOUS THROWS: JobCard raises named failures
// ═══════════════════════════════════════════════════════════════════════════

check('An illegal transition raises IllegalStatusTransition',
    fn() => throws(IllegalStatusTransition::class,
        static fn() => aJobCard()->invoice()));

check('...carrying the status it was actually in',
    function (): bool {
        $thrown = captured(static fn() => aJobCard()->invoice());

        return $thrown instanceof IllegalStatusTransition
            && $thrown->context()['from'] === 'booked'
            && $thrown->context()['to'] === 'invoiced';
    });

check('Adding a line to an invoiced job card raises JobCardIsTerminal',
    fn() => throws(JobCardIsTerminal::class,
        static fn() => anInvoicedJobCard()->addLine(aLabourLine())));

check('...and NOT IllegalStatusTransition — two rules, two types',
    function (): bool {
        $thrown = captured(static fn() => anInvoicedJobCard()->addLine(aLabourLine()));

        return $thrown instanceof JobCardIsTerminal
            && !($thrown instanceof IllegalStatusTransition);
    });

check('Removing a line that is not there raises LineNotFound',
    fn() => throws(LineNotFound::class,
        static fn() => aJobCard()->removeLine(LineId::fromString('LN-9999'))));

check('A line in the wrong currency raises LineCurrencyMismatch',
    fn() => throws(LineCurrencyMismatch::class,
        static fn() => aJobCard()->addLine(aLabourLine('Front pads', 'GBP'))));

check('An empty work description raises MissingWorkDescription',
    fn() => throws(MissingWorkDescription::class,
        static fn() => aJobCard()->discoverAdditionalWork('   ')));

check('...which is InvalidWorkshopInput, not WorkshopException',
    function (): bool {
        $thrown = captured(static fn() => aJobCard()->discoverAdditionalWork(''));

        return $thrown instanceof InvalidWorkshopInput
            && !($thrown instanceof WorkshopException);
    });

check('Authorising nothing raises NothingAwaitingAuthorisation',
    function (): bool {
        $jobCard = aJobCard();
        $jobCard->startWork();

        return throws(NothingAwaitingAuthorisation::class,
            static fn() => $jobCard->authoriseAdditionalWork());
    });

check('Completing with work outstanding raises OutstandingAuthorisations',
    fn() => throws(OutstandingAuthorisations::class,
        static fn() => aJobCardAwaitingAuthorisation()->complete()));

check('...carrying the descriptions, so a client can list them',
    function (): bool {
        $thrown = captured(static fn() => aJobCardAwaitingAuthorisation()->complete());

        return $thrown instanceof OutstandingAuthorisations
            && $thrown->context()['descriptions'] === ['Rear discs below minimum thickness'];
    });

check('A duplicate add raises JobCardAlreadyExists',
    function (): bool {
        $repository = new InMemoryJobCards();
        $repository->add(aJobCard('JC-5001'));

        return throws(JobCardAlreadyExists::class,
            static fn() => $repository->add(aJobCard('JC-5001')));
    });

check('A missing job card raises JobCardNotFound',
    fn() => throws(JobCardNotFound::class,
        static fn() => (new InMemoryJobCards())->get(JobCardId::fromString('JC-0000'))));


// ═══════════════════════════════════════════════════════════════════════════
// 6 — No anonymous throws left in JobCard
// ═══════════════════════════════════════════════════════════════════════════
//
// Source-level, and comments are stripped first — the doc blocks in JobCard
// discuss \DomainException at length, which is describing the rule and not
// breaking it. Lesson 3.1 learned this the expensive way.

check('JobCard contains no `new \DomainException` in code',
    fn() => !str_contains(
        preg_replace('/\s+/', ' ', class_source_without_comments(JobCard::class)) ?? '',
        'new \DomainException'
    ));

check('JobCard contains no `new \InvalidArgumentException` in code',
    fn() => !str_contains(
        preg_replace('/\s+/', ' ', class_source_without_comments(JobCard::class)) ?? '',
        'new \InvalidArgumentException'
    ));

check('JobCard builds no exception messages — every sprintf left with the throws',
    function (): bool {
        $source = class_source_without_comments(JobCard::class);

        // The aggregate is allowed to format nothing at all now. If this ever
        // needs relaxing, the question to ask is what the string is FOR.
        return !str_contains($source, 'sprintf');
    });

check('Infrastructure\SqliteJobCards contains no anonymous \DomainException',
    fn() => !str_contains(
        class_source_without_comments('Infrastructure\SqliteJobCards'),
        'new \DomainException'
    ));


// ═══════════════════════════════════════════════════════════════════════════
// 7 — Catching broadly still works, at three widths
// ═══════════════════════════════════════════════════════════════════════════

check('catch (JobCardException) catches every job-card failure',
    fn() => count(array_filter(
        oneOfEach(),
        static fn(Throwable $e): bool => $e instanceof JobCardException
    )) === 8);

check('catch (WorkshopException) catches those and no input failures',
    fn() => count(array_filter(
        oneOfEach(),
        static fn(Throwable $e): bool => $e instanceof WorkshopException
    )) === 8);

check('catch (WorkshopFailure) catches all nine — both branches',
    fn() => count(array_filter(
        oneOfEach(),
        static fn(Throwable $e): bool => $e instanceof WorkshopFailure
    )) === 9);

check('catch (WorkshopFailure) still catches nothing from vendor or the engine',
    fn() => !(new BadFunctionCallException('x') instanceof WorkshopFailure)
        && !(new PDOException('x') instanceof WorkshopFailure)
        && !(new TypeError('x') instanceof WorkshopFailure));

check('A narrow clause still wins over a broad one — ordering is preserved',
    function (): bool {
        try {
            aJobCard()->invoice();
        } catch (IllegalStatusTransition) {
            return true;
        } catch (JobCardException) {
            return false;
        }

        return false;
    });


// ═══════════════════════════════════════════════════════════════════════════
// 8 — Behaviour is unchanged. Renaming a throw is not a refactor of a rule.
// ═══════════════════════════════════════════════════════════════════════════

check('A legal lifecycle still runs end to end',
    function (): bool {
        $jobCard = aJobCard('JC-7001');
        $jobCard->startWork();
        $jobCard->addLine(aLabourLine());
        $jobCard->complete();
        $jobCard->invoice();

        return $jobCard->status() === JobCardStatus::Invoiced
            && $jobCard->lineCount() === 1;
    });

check('Authorisation still clears the pending list',
    function (): bool {
        $jobCard = aJobCardAwaitingAuthorisation();
        $jobCard->authoriseAdditionalWork();

        return $jobCard->pendingAuthorisations() === []
            && $jobCard->status() === JobCardStatus::InProgress;
    });

check('A refused operation still changes nothing',
    function (): bool {
        $jobCard = aJobCard();
        $before  = $jobCard->status();

        captured(static fn() => $jobCard->invoice());

        return $jobCard->status() === $before;
    });

check('A refused discoverAdditionalWork still appends nothing',
    function (): bool {
        $jobCard = aJobCard();
        $jobCard->startWork();

        captured(static fn() => $jobCard->discoverAdditionalWork(''));

        return $jobCard->pendingAuthorisations() === []
            && $jobCard->status() === JobCardStatus::InProgress;
    });

check('Events are still recorded, and only on success',
    function (): bool {
        $jobCard = aJobCard('JC-7002');
        captured(static fn() => $jobCard->invoice());   // refused

        return count($jobCard->releaseEvents()) === 1;  // JobCardOpened only
    });


acceptance_report();
