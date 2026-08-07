<?php
declare(strict_types=1);

namespace Infrastructure\Legacy;

use Workshop\Currency;
use Workshop\IllegalStatusTransition;
use Workshop\JobCardId;
use Workshop\JobCardStatus;
use Workshop\Money;

/**
 * An Active Record job card, with three rungs of the ladder climbed.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * WHAT THIS CLASS IS NOT
 * -----------------------
 * It is not `Workshop\JobCard`, it does not replace it, and there is no mapper
 * between them. This is the same domain modelled a second time, in the shape
 * the framework imposes, for the situation where a separate domain layer has
 * not been paid for.
 *
 * Note where it lives: `infrastructure/`. A class that extends an ORM base and
 * knows how to save itself is infrastructure by the course's own test — delete
 * the database and `save()` means nothing. The rules it enforces are domain
 * rules; the object is not a domain object. Both things are true and the
 * lesson is about being able to hold them at once.
 *
 * THE THREE RUNGS, IN ORDER OF WHAT THEY COST
 * --------------------------------------------
 *
 * RUNG 1 — NAMED METHODS INSTEAD OF PUBLIC SETTERS.  Free.
 *   `$record->complete()` rather than `$record->status = 'completed'`. The
 *   diff is small, no schema changes, no new files. It is the single highest
 *   value-per-hour change available in a legacy codebase, and section 3 of the
 *   README argues why.
 *
 * RUNG 2 — THE INVARIANT MOVES INTO THE MODEL.  Cheap.
 *   I9 — only the transitions in the table are legal — is enforced HERE, not
 *   in a service, not in a controller, and not in three of each. The rule is
 *   now unavoidable for anything holding the record.
 *
 * RUNG 3 — VALUE OBJECTS BEHIND THE COLUMNS.  Cheap.
 *   `total()` returns a `Workshop\Money` built from `total_cents` and
 *   `currency`. The columns stay exactly as they are; the schema does not
 *   move. In a real ORM this is a cast or a custom type, and it is the same
 *   idea.
 *
 * WHAT WAS NOT DONE, AND THE POINT OF NOT DOING IT
 * -------------------------------------------------
 * RUNG 5 — a separate domain layer with a mapper — is not here. There is no
 * `Workshop\JobCard` in this file, no repository, no reconstitution.
 *
 * Most of the value of Modules 1, 2 and 4 has been captured anyway: the
 * invalid transition is impossible, the money cannot be a float, and the
 * failure is a typed domain exception. What has NOT been captured is testing
 * the rules without a database, and a domain that survives replacing the ORM.
 *
 * Whether those two are worth a mapping layer is Lesson 5.1's actual question,
 * and the honest answer for most applications is no.
 *
 * THE PART THAT COST NOTHING AT ALL
 * ----------------------------------
 * `IllegalStatusTransition` is Module 4's class, unchanged, thrown from an
 * Active Record model. `ProblemDetailsMapper` maps it to a 409 with its
 * context intact and needs no new entry, because it keys on `failureCode()`
 * and knows nothing about where the exception came from.
 *
 * Module 4 transfers into a legacy application WHOLESALE, with no persistence
 * changes of any kind. That is worth knowing before you start anything else:
 * it is the cheapest large improvement in this course.
 */
final class JobCardRecord extends ActiveRecord
{
    /**
     * Columns nothing outside this class may write.
     *
     * `status` is here because the transition table governs it. `total_cents`
     * and `currency` are here because they are two halves of one value and
     * writing one without the other produces money in the wrong currency.
     *
     * @var list<string>
     */
    private const array GUARDED = ['status', 'total_cents', 'currency'];

    /**
     * RUNG 1, and this is the only line in the file that is fighting the
     * framework rather than using it.
     *
     * The base class writes any column on assignment. That is what an Active
     * Record model is for, and it is why rules on one are so easy to route
     * around: `$record->status = 'invoiced'` needs no method and asks no
     * permission.
     *
     * Taking over `__set` makes the guarded columns unwritable from outside
     * while leaving every other column exactly as convenient as it was. The
     * unguarded ones still work — this is a legacy application and `$record->
     * customer_note = '...'` is not a rule anybody is protecting.
     *
     * BE HONEST ABOUT WHAT THIS DOES NOT DO. It is a runtime check, not a type.
     * A `LogicException` at 3am is worse than a compile error, and PHP will
     * not give you the compile error here — a magic setter is magic. This is
     * the cost of the framework owning your model, and it does not go away
     * until rung 5.
     */
    public function __set(string $name, mixed $value): void
    {
        if (in_array($name, self::GUARDED, true)) {
            throw new \LogicException(sprintf(
                '%s is guarded. Use the named method that changes it — the transition '
                . 'rules live there, and assigning the column would route around them.',
                $name
            ));
        }

        parent::__set($name, $value);
    }

    // ── Reading, as value objects ───────────────────────────────────────────

    public function id(): JobCardId
    {
        return JobCardId::fromString((string) $this->attributes['id']);
    }

    /**
     * RUNG 3. Two columns in, one value object out.
     *
     * The schema did not move. `total_cents` is still an integer column and
     * `currency` is still a three-character one; a report written in SQL last
     * year still works. What changed is that no PHP caller adds money with `+`
     * any more.
     *
     * In Eloquent this is a cast or an accessor; in Doctrine an embeddable or
     * a custom type. The mechanism differs and the decision does not.
     */
    public function total(): Money
    {
        return Money::of(
            (int) ($this->attributes['total_cents'] ?? 0),
            Currency::from((string) ($this->attributes['currency'] ?? 'EUR'))
        );
    }

    /**
     * The column holds the enum's backed value, which is what makes this
     * change reversible: a row written before today reads back correctly, and
     * a row written today is readable by anything that never heard of the
     * enum.
     *
     * That property is worth more than it looks. A change to a legacy system
     * that cannot be rolled back is a change nobody will approve.
     */
    public function status(): JobCardStatus
    {
        return JobCardStatus::from((string) $this->attributes['status']);
    }

    // ── The lifecycle, as named methods ─────────────────────────────────────

    /** @throws IllegalStatusTransition */
    public function startWork(): void
    {
        $this->transitionTo(JobCardStatus::InProgress);
    }

    /** @throws IllegalStatusTransition */
    public function complete(): void
    {
        $this->transitionTo(JobCardStatus::Completed);
    }

    /** @throws IllegalStatusTransition */
    public function invoice(): void
    {
        $this->transitionTo(JobCardStatus::Invoiced);
    }

    /** @throws IllegalStatusTransition */
    public function cancel(): void
    {
        $this->transitionTo(JobCardStatus::Cancelled);
    }

    /**
     * RUNG 3 again, on the write side. Money in, columns out.
     *
     * Note it takes a `Money` and not an int. A method taking `int $cents`
     * would let a caller pass a number from anywhere, which is the primitive
     * obsession Lesson 1.2 was about — and the currency would still be
     * writable independently, which is what `GUARDED` exists to prevent.
     */
    public function priceAt(Money $total): void
    {
        $this->attributes['total_cents'] = $total->amountMinor();
        $this->attributes['currency']    = $total->currency()->value;
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /**
     * RUNG 2. One method, and it is the same shape as `Workshop\JobCard`'s.
     *
     * It writes `$this->attributes` DIRECTLY rather than going through
     * `$this->status = ...`, because the guard above would reject it. That is
     * not a workaround: the guard's job is to stop code OUTSIDE the class
     * writing the column, and this is inside.
     *
     * The enum is `Workshop\JobCardStatus` — the domain's, unchanged, with its
     * transition table. The rule was never persistence-shaped, so it did not
     * need rewriting for an ORM. Value objects and enums are the parts of a
     * domain model that transfer for free.
     *
     * @throws IllegalStatusTransition
     */
    private function transitionTo(JobCardStatus $next): void
    {
        $current = $this->status();

        if (!$current->canTransitionTo($next)) {
            throw IllegalStatusTransition::from($this->id(), $current, $next);
        }

        $this->attributes['status'] = $next->value;
    }
}
