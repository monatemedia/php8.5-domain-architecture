<?php
declare(strict_types=1);

namespace Workshop;

/**
 * One visit: what was asked for, what was done, what it costs.
 *
 * REFERENCE IMPLEMENTATION. The first AGGREGATE ROOT in the course.
 *
 * WHAT IS INSIDE THE BOUNDARY
 * ----------------------------
 * The lines, the status, and the pending authorisations. Those three must be
 * correct together, at the same instant: for as long as the total disagrees
 * with the lines, somebody can invoice the wrong amount.
 *
 * WHAT IS OUTSIDE, AND HELD BY IDENTITY
 * --------------------------------------
 * The vehicle and the account holder. The test from the README:
 *
 *     If these two changed a second apart, would the business be harmed?
 *
 * A mileage correction is not a billing event, and a billing address can change
 * mid-repair. Both harmless, so both are separate aggregates, referenced by id.
 *
 * The cost is visible and it is meant to be: `$jobCard->vehicle->registration()`
 * does not exist. Printing the registration on an invoice is now somebody's
 * explicit decision to load a second aggregate — which is the boundary being
 * legible in the code rather than implied.
 *
 * The benefit is not visible until later, which is why big aggregates fail
 * slowly. Holding the AccountHolder object would mean loading one job card
 * loads that account holder's entire history, and two clerks editing two
 * unrelated job cards for the same fleet customer would collide.
 *
 * WHAT IS NOT MODELLED HERE, ON PURPOSE
 * --------------------------------------
 *   - The tax rate. A policy — set by a government, with a commencement date.
 *     It is passed in wherever an invoice is produced, never held here.
 *     DOMAIN.md, "What is not an invariant".
 *
 *   - The authorisation threshold. Also a policy: it was 30 last year, it is
 *     200 for fleet accounts, and Marcus expects to move it again.
 *
 *   - Invariant I10 — a courtesy vehicle is on at most one open job card at any
 *     time. This aggregate CANNOT enforce it and should not try. It would need
 *     to see every other open job card, and giving it that makes the boundary
 *     the whole system. Lesson 2.2 is about what you do instead.
 *
 * CHANGED IN LESSON 4.3 — TWO OPERATIONS CAN NOW BE ASKED AS WELL AS DONE
 * ------------------------------------------------------------------------
 * `checkCanComplete()` and `checkCanInvoice()` return a `Decision`, and
 * `complete()` and `invoice()` are implemented as `->orThrow()` on it.
 *
 * The point is not that there are now two ways to call the same rule. It is
 * that there is still only ONE list of guards, so the ask-path and the act-path
 * cannot drift. The check saying yes while the operation refuses is the failure
 * every result-type retrofit eventually produces, and here it is not a
 * discipline anybody has to maintain — it is unreachable.
 *
 * NOT EVERY OPERATION GOT ONE, and that is deliberate rather than unfinished.
 * `addLine()`, `removeLine()`, `startWork()`, `cancel()` and the rest still
 * only throw. Section 5 of the README gives the test; the short version is that
 * a Decision earns its place when somebody asks before acting, and nobody asks
 * whether they may add a line — they add it.
 *
 * CHANGED IN LESSON 4.2 — SEVEN THROW SITES, NO NEW BEHAVIOUR
 * ------------------------------------------------------------
 * Every `throw new \DomainException(...)` and the one
 * `throw new \InvalidArgumentException(...)` are gone, replaced by named
 * classes from `Workshop\`. Nothing this aggregate REFUSES has changed, and
 * nothing it accepts has changed either — `php regression.php` proves it,
 * because every earlier assertion is written against `\DomainException` or
 * `\InvalidArgumentException` and every new class is still one of those.
 *
 * Two things are different, and neither is visible in the diff of this file:
 *
 *   - The messages left. Each exception now builds its own, which is why
 *     `transitionTo()` went from fifteen lines to four. An aggregate root
 *     holding eleven `sprintf` calls was doing presentation.
 *
 *   - A caller can now tell the failures apart. `catch (JobCardIsTerminal $e)`
 *     is a clause that could not be written yesterday, and `$e->context()`
 *     gives a boundary the fields it needs without parsing English.
 *
 * The one thing to be suspicious of: this file now names seven exception
 * classes it did not name before. There are no new `use` statements, because
 * all of them are in `Workshop\` alongside this class — which is itself the
 * argument that they belong there. They describe this aggregate's own rules and
 * not one of them is infrastructure.
 *
 * If that list ever reaches thirty, the aggregate is too big. Lesson 2.1's
 * warning about boundaries, arriving from a direction it did not anticipate:
 * the size of an aggregate's exception vocabulary is a measure of the size of
 * the aggregate.
 *
 * ADDED IN LESSON 2.3 — DOMAIN EVENTS
 * ------------------------------------
 * Three operations now record a fact about what happened: open(), complete()
 * and invoice(). Nothing else changed, and nothing was removed — Lesson 2.1's
 * checks still pass, which `php regression.php` proves.
 *
 * Note what did NOT arrive with them: a dispatcher. This class still
 * constructs from nothing but its own data. The events sit in a list until
 * somebody calls releaseEvents(), which should be after the transaction has
 * committed — because a handler that emails an invoice cannot be rolled back.
 *
 * Note also WHERE each recordThat() sits: after the guards and after the
 * transition, never before. A refused operation announces nothing.
 */
final class JobCard
{
    /**
     * Four lines of array handling, shared with every other aggregate root.
     * A trait rather than a base class: a JobCard is not a kind of anything,
     * and an abstract parent would spend the one inheritance slot this class
     * has, forever, on a list.
     */
    use RecordsDomainEvents;

    /**
     * Keyed by line id so that removeLine() is a lookup rather than a scan.
     *
     * @var array<string, Line>
     */
    private array $lines = [];

    /** @var list<string> descriptions of work discovered but not yet approved */
    private array $pendingAuthorisations = [];

    private JobCardStatus $status = JobCardStatus::Booked;

    private function __construct(
        private readonly JobCardId       $id,
        private readonly VehicleId       $vehicleId,
        private readonly AccountHolderId $accountHolderId,
        private readonly Currency        $currency,
    ) {}

    /**
     * Book a vehicle in.
     *
     * Named `open` rather than `create` because that is what the workshop says:
     * a job card is opened when the vehicle is booked in, and closed when the
     * work is done. Rule 4 — use their words, so that they can read the code
     * back to you and tell you where it is wrong.
     *
     * The currency is fixed for the life of the job card. Every line must match
     * it, which is checked in addLine(); allowing it to change afterwards would
     * mean a job card whose existing lines are suddenly in the wrong currency.
     */
    public static function open(
        JobCardId       $id,
        VehicleId       $vehicleId,
        AccountHolderId $accountHolderId,
        Currency        $currency,
    ): self {
        $jobCard = new self($id, $vehicleId, $accountHolderId, $currency);

        // Recorded from a static factory rather than the constructor, on
        // purpose. A mapper reconstituting this job card from storage in
        // Module 3 will NOT call open() — it will rebuild the object directly,
        // and it must not announce that a vehicle was booked in for the second
        // time. Opening is a business event; loading is not.
        $jobCard->recordThat(new JobCardOpened($id, $vehicleId, $accountHolderId));

        return $jobCard;
    }

    /**
     * Rebuild a job card that already exists, from storage.
     *
     * ADDED IN LESSON 3.2. The hardest four lines in the course, and every one
     * of them is doing something the public API deliberately forbids.
     *
     * IT RECORDS NOTHING — AND THAT COMES FREE
     * -----------------------------------------
     * `open()` records JobCardOpened. If loading went through `open()`, every
     * read of a job card would announce that a vehicle had been booked in, and
     * handlers subscribed to that event would fire for something that happened
     * months ago.
     *
     * Note that this method does not have to do anything to avoid that. The
     * CONSTRUCTOR does not record; only `open()` does. That was a decision made
     * in Lesson 2.3 for a reason stated at the time — "opening is a business
     * event; loading is not" — and this is where it pays off. Had the event
     * been recorded in the constructor, there would be no way out that did not
     * involve a flag.
     *
     * IT SETS THE STATUS DIRECTLY, BYPASSING THE TRANSITION TABLE
     * -----------------------------------------------------------
     * A job card loaded as Invoiced did not transition to Invoiced now. It was
     * invoiced last Tuesday, and I9 governed that transition when it happened.
     * Replaying the lifecycle to arrive at the stored status would be absurd,
     * and it would be impossible for Cancelled.
     *
     * This is the sharpest form of the reconstitution argument: the invariant
     * is about CHANGES, and loading is not a change. It is the same object
     * arriving from a different place.
     *
     * IT TAKES THE LINES ALREADY BUILT
     * ---------------------------------
     * Not rows. The mapper builds each Line through its own reconstitute(), so
     * the identity round-trips and the value objects inside are built through
     * their normal factories. Reconstitution relaxes this class's rules, not
     * the rules of everything it holds.
     *
     * THE COST, STATED PLAINLY
     * ------------------------
     * This is a public method that assembles a JobCard in any state, with any
     * lines, with no checks at all. `JobCard::reconstitute($id, ..., Invoiced,
     * [], [])` from application code would produce an invoiced job card with no
     * lines and a zero total, and nothing would object.
     *
     * PHP has no friend classes and no package-private, so nothing can prevent
     * that. What is available: a name that says what it is for, a doc block
     * that says who may call it, and a review that asks why anything other than
     * a mapper is calling it. Example 03 shows the reflection alternative,
     * which trades this hole for a different one.
     *
     * @param list<Line>   $lines
     * @param list<string> $pendingAuthorisations
     */
    public static function reconstitute(
        JobCardId       $id,
        VehicleId       $vehicleId,
        AccountHolderId $accountHolderId,
        Currency        $currency,
        JobCardStatus   $status,
        array           $lines,
        array           $pendingAuthorisations,
    ): self {
        $jobCard = new self($id, $vehicleId, $accountHolderId, $currency);

        $jobCard->status = $status;

        foreach ($lines as $line) {
            $jobCard->lines[$line->id()->value()] = $line;
        }

        $jobCard->pendingAuthorisations = array_values($pendingAuthorisations);

        return $jobCard;
    }

    // ── Identity and references ─────────────────────────────────────────────

    public function id(): JobCardId
    {
        return $this->id;
    }

    public function vehicleId(): VehicleId
    {
        return $this->vehicleId;
    }

    public function accountHolderId(): AccountHolderId
    {
        return $this->accountHolderId;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function status(): JobCardStatus
    {
        return $this->status;
    }

    // ── Lines ───────────────────────────────────────────────────────────────

    /**
     * INVARIANT I6: no line may be added to a job card in a terminal state.
     *
     * Note the order of the two guards. The terminal check comes first because
     * it is the more fundamental refusal: telling somebody their currency is
     * wrong, when the real problem is that the job card was invoiced last
     * Tuesday, sends them to fix the wrong thing.
     *
     * The order now also decides which of two failure CODES a client sees,
     * which raises the stakes slightly — `job-card-is-terminal` and
     * `line-currency-mismatch` may be handled differently, and only the first
     * one that fires is reported. Lesson 4.3 is about returning both.
     *
     * @throws JobCardIsTerminal
     * @throws LineCurrencyMismatch
     */
    public function addLine(Line $line): void
    {
        $this->assertNotTerminal('add a line to');

        if ($line->total()->currency() !== $this->currency) {
            throw LineCurrencyMismatch::between(
                $this->id,
                $this->currency,
                $line->description(),
                $line->total()->currency()
            );
        }

        $this->lines[$line->id()->value()] = $line;
    }

    /**
     * INVARIANT I6, the other half.
     *
     * @throws JobCardIsTerminal
     * @throws LineNotFound
     */
    public function removeLine(LineId $lineId): void
    {
        $this->assertNotTerminal('remove a line from');

        if (!isset($this->lines[$lineId->value()])) {
            throw LineNotFound::on($this->id, $lineId);
        }

        unset($this->lines[$lineId->value()]);
    }

    /**
     * @return list<Line> in insertion order
     *
     * Safe to hand out, for a reason worth stating rather than assuming: PHP
     * copies the array, so the caller cannot add or remove; and every Line
     * implementation is `readonly`, so the caller cannot change one either.
     *
     * The array copy alone would not be enough. Example 02 runs an aggregate
     * whose every guard is correct, whose lines are mutable, and which enforces
     * nothing — a copied array of objects copies the handles, not the objects.
     */
    public function lines(): array
    {
        return array_values($this->lines);
    }

    public function lineCount(): int
    {
        return count($this->lines);
    }

    /**
     * INVARIANT I5: the total always equals the sum of the lines.
     *
     * Derived, so there is no moment at which it does not — the invariant is
     * not enforced, it is unrepresentable.
     *
     * This is the OPPOSITE conclusion to the one a billing system usually
     * reaches, and section 6 of the README argues it properly. The short
     * version: deriving is safe here because Money is immutable and each line
     * holds its own copy of the rate it was created with, so recomputing in
     * five years gives the same answer. The day the CALCULATION becomes
     * historical — a new tax treatment, a different rounding rule — this has to
     * become a stored value, with all the drift risk that brings.
     */
    public function total(): Money
    {
        $total = Money::zero($this->currency);

        foreach ($this->lines as $line) {
            $total = $total->plus($line->total());
        }

        return $total;
    }

    // ── Lifecycle ───────────────────────────────────────────────────────────

    public function startWork(): void
    {
        $this->transitionTo(JobCardStatus::InProgress);
    }

    /**
     * Record work discovered after the vehicle was booked in.
     *
     * ORDER MATTERS, and here it genuinely is observable. The transition
     * happens BEFORE the description is appended, so that a refused call
     * appends nothing. Get it the other way round and a job card in Booked
     * collects a pending item it has no legal path to clear.
     *
     * A DELIBERATE SIMPLIFICATION: because Awaiting Authorisation cannot
     * transition to itself, only one item can be pending at a time — a second
     * discovery while waiting is refused. Real workshops find two things at
     * once, so a real system would let the list grow while in that state. It is
     * left strict here because the alternative is a status condition inside a
     * lifecycle method, which is exactly what this lesson is trying to get out
     * of your fingers. Worth arguing with; note it in PROGRESS.md if you would
     * do it differently.
     *
     * THE ONE METHOD THAT THROWS FROM BOTH BRANCHES OF THE TREE.
     *
     * An empty description is INPUT the caller could have rejected itself, so
     * it is a `MissingWorkDescription` — an `InvalidWorkshopInput`, under
     * `\InvalidArgumentException`. An illegal transition depends on state only
     * this object knows, so it is an `IllegalStatusTransition` — a
     * `WorkshopException`, under `\DomainException`.
     *
     * Both are `WorkshopFailure`s, and a boundary catching that one interface
     * gets both. Lesson 4.4 gives them different status codes — 422 and 409 —
     * which is the distinction being worth something rather than being tidy.
     *
     * @throws MissingWorkDescription  the description is empty
     * @throws IllegalStatusTransition the transition is illegal
     */
    public function discoverAdditionalWork(string $description): void
    {
        $description = trim($description);

        if ($description === '') {
            throw MissingWorkDescription::forJobCard($this->id);
        }

        $this->transitionTo(JobCardStatus::AwaitingAuthorisation);

        $this->pendingAuthorisations[] = $description;
    }

    /**
     * The account holder has approved the outstanding work.
     *
     * The first guard is about the job card's CONTENTS, not about its status,
     * which is why it belongs here and not on the enum. Without it, this method
     * would be callable from Awaiting Parts — a legal transition to In Progress
     * — and would report that work had been approved when none was ever
     * proposed.
     *
     * ORDER: transition, then clear. Being honest about it, the order is not
     * observable in this design — every path that reaches the transition has a
     * non-empty list, and every path that fails has already been rejected by
     * the guard above. It is written this way because "validate first, mutate
     * last" is worth following mechanically rather than case by case. A rule
     * you only apply where you can prove it matters is a rule you will forget
     * to apply on the day it does.
     *
     * @throws NothingAwaitingAuthorisation nothing is outstanding
     * @throws IllegalStatusTransition      the transition is illegal
     */
    public function authoriseAdditionalWork(): void
    {
        if ($this->pendingAuthorisations === []) {
            throw NothingAwaitingAuthorisation::on($this->id);
        }

        $this->transitionTo(JobCardStatus::InProgress);

        $this->pendingAuthorisations = [];
    }

    /** @return list<string> */
    public function pendingAuthorisations(): array
    {
        return $this->pendingAuthorisations;
    }

    public function awaitParts(): void
    {
        $this->transitionTo(JobCardStatus::AwaitingParts);
    }

    public function partsArrived(): void
    {
        $this->transitionTo(JobCardStatus::InProgress);
    }

    /**
     * INVARIANT I8: a job card cannot be completed while additional work awaits
     * authorisation.
     *
     * THIS GUARD IS REDUNDANT, and it is worth knowing that rather than
     * discovering it. discoverAdditionalWork() moves the status to
     * AwaitingAuthorisation, and the transition table has no path from there to
     * Completed — so the state machine already refuses this, and deleting these
     * four lines makes nothing newly possible.
     *
     * It is kept as Lesson 1.4 §5's "one rule, two presentations" case. What it
     * changes is the message: instead of "Awaiting Authorisation cannot become
     * Completed", which describes the model, the caller is told there is
     * outstanding work nobody has approved, which describes the workshop.
     *
     * The honest counter-argument, which the challenge asks you to make: a
     * reader now cannot tell which of the two is the rule, and if the table
     * ever changes to allow that transition, this guard silently becomes the
     * only thing enforcing I8 — a duplicate that has quietly become the
     * original. That is a real cost and it is why the same reasoning does not
     * justify doing this everywhere.
     *
     * WHAT THE TREE ADDS TO THAT ARGUMENT: the two presentations are now two
     * TYPES with two failure codes, so a client can handle them differently —
     * `outstanding-authorisations` is something a service advisor can act on
     * from a list of descriptions; `illegal-status-transition` is not. A
     * message alone could never have supported that.
     *
     * REWRITTEN IN LESSON 4.3, AND THE BODY IS THE LESSON.
     *
     * The guards did not move. They moved OUT, into checkCanComplete(), and
     * this method now asks that question and throws the first answer.
     *
     * Which means the two styles cannot disagree. There is one list of reasons
     * and two ways of reading it: ask and get all of them, or act and get the
     * first. A caller who checks and is then refused anyway is the failure mode
     * this pattern is famous for, and it is structurally impossible here.
     *
     * @throws OutstandingAuthorisations
     * @throws IllegalStatusTransition
     */
    public function complete(): void
    {
        $this->checkCanComplete()->orThrow();

        // transitionTo() re-checks the transition, which checkCanComplete() has
        // already cleared. The second check is unreachable and it stays,
        // because this is the only method that assigns $this->status and a
        // method that mutates state should enforce its own invariant rather
        // than trust its callers. The cost is one match() on a happy path.
        $this->transitionTo(JobCardStatus::Completed);

        // AFTER the transition, not before. If transitionTo() throws, nothing
        // is recorded — and the courtesy-vehicle handler subscribed to this
        // event does not return a car for a job card that is still in progress.
        $this->recordThat(new JobCardCompleted($this->id));
    }

    /**
     * Every reason this job card cannot be completed, or none.
     *
     * TWO REASONS AT ONCE IS NOT HYPOTHETICAL. A job card in Awaiting
     * Authorisation with an outstanding item has both: the item nobody has
     * approved, and a transition table with no path from that state to
     * Completed. `complete()` reports the first and abandons the second,
     * because unwinding carries one object.
     *
     * A user filling in a form wants both. So does a UI greying out buttons,
     * and so does an operator on the phone to a customer.
     *
     * THE ORDER IS PART OF THE CONTRACT. `orThrow()` throws the first, so this
     * order decides what `complete()` throws — and it is the order the guards
     * were in before this lesson, deliberately, so that Lesson 4.2's checks
     * still pass. Statement order inside a method used to be an implementation
     * detail; it is now an observable decision, and that is a real cost of this
     * pattern.
     */
    #[\NoDiscard('A Decision that is not read has decided nothing.')]
    public function checkCanComplete(): Decision
    {
        $refusals = [];

        if ($this->pendingAuthorisations !== []) {
            $refusals[] = Refusal::from(
                OutstandingAuthorisations::on($this->id, $this->pendingAuthorisations)
            );
        }

        if (!$this->status->canTransitionTo(JobCardStatus::Completed)) {
            $refusals[] = Refusal::from(
                IllegalStatusTransition::from($this->id, $this->status, JobCardStatus::Completed)
            );
        }

        return $refusals === []
            ? Decision::allowed()
            : Decision::refusedBecause(...$refusals);
    }

    /**
     * @throws IllegalStatusTransition
     */
    public function invoice(): void
    {
        $this->checkCanInvoice()->orThrow();

        $this->transitionTo(JobCardStatus::Invoiced);

        // The total is captured HERE, at the moment of billing, rather than
        // left for a handler to recompute later. total() is derived, and
        // Lesson 2.1 section 6 explains why that is safe today; "safe today" is
        // not "safe forever", and what was BILLED is a fact about a moment.
        $this->recordThat(new JobCardInvoiced($this->id, $this->total()));
    }

    /**
     * Invoicing has exactly one guard, so this Decision can never hold two
     * refusals — and it exists anyway.
     *
     * NOT for symmetry. Because the caller is a screen with a button on it, and
     * that screen should not have to know that "can I complete this?" is
     * answered by a Decision while "can I invoice this?" is answered by
     * catching an exception. An interface that changes shape depending on how
     * many rules happen to sit behind it is an interface that leaks its own
     * implementation.
     *
     * If a second invoicing rule arrives — a credit limit, an unpaid balance —
     * nothing outside this class changes.
     */
    #[\NoDiscard('A Decision that is not read has decided nothing.')]
    public function checkCanInvoice(): Decision
    {
        if (!$this->status->canTransitionTo(JobCardStatus::Invoiced)) {
            return Decision::refusedBecause(
                Refusal::from(
                    IllegalStatusTransition::from($this->id, $this->status, JobCardStatus::Invoiced)
                )
            );
        }

        return Decision::allowed();
    }

    public function cancel(): void
    {
        $this->transitionTo(JobCardStatus::Cancelled);
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /**
     * INVARIANT I9: only the transitions in JobCardStatus::allowedNext() are
     * legal.
     *
     * One method, used by every lifecycle operation. Each of those states which
     * transition it is attempting and lets the table decide, which is why none
     * of them contains a status condition of its own — that is example 03 part
     * 1, and it is where the bug was.
     *
     * THE MESSAGE-BUILDING MOVED OUT IN LESSON 4.2, and the shape of what is
     * left is the point. This method now decides ONE thing — is the transition
     * legal — and the exception knows how to describe itself.
     *
     * That split is worth noticing because it is what makes the tree pay. The
     * sprintf that used to live here was eleven lines of presentation inside a
     * private method of an aggregate root, and every other guard had its own
     * copy of the same idea.
     *
     * @throws IllegalStatusTransition
     */
    private function transitionTo(JobCardStatus $next): void
    {
        if (!$this->status->canTransitionTo($next)) {
            throw IllegalStatusTransition::from($this->id, $this->status, $next);
        }

        $this->status = $next;
    }

    /**
     * @throws JobCardIsTerminal
     */
    private function assertNotTerminal(string $operation): void
    {
        if ($this->status->isTerminal()) {
            throw JobCardIsTerminal::cannot($this->id, $operation, $this->status);
        }
    }
}
