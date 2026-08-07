# Challenge — Lesson 5.1: When the Framework Owns Your Models

> **You write:** `infrastructure/Legacy/JobCardRecord.php` — one file
> **You are given:** `challenge/given/Legacy/ActiveRecord.php` — read it, do not edit it
> **You change in `domain/workshop/`:** nothing
> **You run:** `php module-5-living-with-a-framework/lesson-5.1-when-the-framework-owns-your-models/challenge/starter.php`
> **Then run:** `php regression.php`

One file, three rungs of the ladder, and a set of checks that assert something the course has never
asserted before: that you did **not** build the fourth thing.

---

## Read `checks.php` first. Sections 5 and 6 are the lesson.

---

## 0 — The situation

`Infrastructure\Legacy\ActiveRecord` is a sixty-line Active Record base. It is not yours to change,
in the same way Eloquent's `Model` is not yours to change. It gives you magic attribute access,
magic assignment, `save()`, `find()` and `hydrate()`, and every one of those is faithful to the real
thing.

Your job is to build `JobCardRecord` on top of it, climbing rungs 1, 2 and 3 — and stopping.

Note where it goes: **`infrastructure/`, not `domain/workshop/`.** A class that extends an ORM base
and knows how to save itself is infrastructure by the course's own test. The rules it enforces are
domain rules; the object is not a domain object. Both are true, and being able to hold them at once
is most of this lesson.

---

## 1 — Rung 1: stop the setters

Three columns have rules and must be unwritable from outside:

```php
private const array GUARDED = ['status', 'total_cents', 'currency'];
```

Take over `__set`. Guarded columns throw a `\LogicException` naming the column; everything else calls
`parent::__set()` and stays exactly as convenient as it was.

**Leave the unguarded columns alone.** This is a legacy application and `$record->customer_note =
'...'` is not a rule anybody is protecting. The checks assert both halves — that the guarded ones are
refused *and* that an arbitrary new column still works.

`total_cents` and `currency` are guarded together because they are two halves of one value. Writing
one without the other produces money in the wrong currency, silently.

---

## 2 — Rung 2: the invariant moves in

One private `transitionTo(JobCardStatus $next)`, exactly the shape `Workshop\JobCard` uses, plus
four named methods: `startWork()`, `complete()`, `invoice()`, `cancel()`.

**Use `Workshop\JobCardStatus` and its transition table.** Do not write a copy. The checks compare
your record's behaviour against `JobCardStatus::Booked->allowedNext()` directly, so a hand-written
table that drifts will fail.

That enum was never persistence-shaped, which is why it works unchanged from an ORM model. Value
objects and enums are the parts of a domain model that transfer for free.

`transitionTo()` writes `$this->attributes['status']` directly. That is not a workaround for your own
guard — the guard's job is to stop code *outside* the class writing the column, and this is inside.

---

## 3 — Rung 3: value objects over the columns

```php
public function total(): Money;
public function priceAt(Money $total): void;
```

`total()` builds a `Workshop\Money` from `total_cents` and `currency`. `priceAt()` writes both back.

**`priceAt()` takes a `Money`, not an `int`.** The checks assert the parameter type. A method taking
`int $cents` lets a caller pass a number from anywhere — the primitive obsession of Lesson 1.2 — and
leaves the currency writable separately.

**The schema does not move.** The checks assert the columns are still an `int` and a `string` after
`priceAt()`. If you find yourself wanting to store serialised objects, you have left the rung.

Store the status as the enum's **backed value**, so a row written today is readable by anything that
never heard of the enum, and a row written last year still loads. A change to a legacy system that
cannot be rolled back is a change nobody will approve.

---

## 4 — Rung 4 costs you nothing, and the checks prove it

Throw `Workshop\IllegalStatusTransition` — Module 4's class, unchanged — from an Active Record model.

Then look at what section 5 of `checks.php` asserts: `ProblemDetailsMapper` maps it to a 409 with its
context intact, needs no new catalogue entry, and produces a body **byte-identical** to the one it
produces from the domain aggregate.

You write no code for this. It falls out of the mapper keying on `failureCode()` rather than on a
class. **An exception tree and a boundary mapper have no opinion about persistence, which is why they
can be added to anything** — and it makes Module 4 the cheapest large improvement available to a
legacy PHP application.

---

## 5 — What NOT to build

The checks in section 6 assert the absence of:

- **`Workshop\JobCard`.** No aggregate, no second model.
- **A repository.** No `JobCards`, no `Repository`.
- **A mapper.** No `Mapper` of any kind.

That is rung 5, and this challenge is about not paying for it. A solution that quietly introduces a
mapping layer has answered a different question — probably a more interesting one, and not the one
asked.

They also assert `domain/workshop/` is unchanged and imports nothing from `Infrastructure`. The arrow
still points inward; it just now has a legacy record on the outside of it.

---

## Acceptance criteria

- [ ] `JobCardRecord extends ActiveRecord`, in `Infrastructure\Legacy`, outside `domain/workshop/`
- [ ] `status`, `total_cents` and `currency` are unwritable from outside; other columns are not
- [ ] Four named lifecycle methods, all routed through one private `transitionTo()`
- [ ] The transition rules come from `Workshop\JobCardStatus`, not a copy
- [ ] `total()` returns `Money`; `priceAt()` takes `Money`; both columns stay scalar
- [ ] Illegal transitions raise `Workshop\IllegalStatusTransition` with its context
- [ ] `ProblemDetailsMapper` maps it to 409 with no changes
- [ ] No aggregate, no repository, no mapper
- [ ] `save()`/`find()`/`hydrate()` still work, and a legacy row still loads
- [ ] `php regression.php` is green

---

## Hints

<details>
<summary>Hint 1 — taking over __set</summary>

```php
public function __set(string $name, mixed $value): void
{
    if (in_array($name, self::GUARDED, true)) {
        throw new \LogicException(sprintf(
            '%s is guarded. Use the named method that changes it.',
            $name
        ));
    }

    parent::__set($name, $value);
}
```

`__set` only fires for **inaccessible** properties. `$this->attributes` is a real protected property,
so writing it from inside the class does not go through this at all.

</details>

<details>
<summary>Hint 2 — the status column</summary>

```php
public function status(): JobCardStatus
{
    return JobCardStatus::from((string) $this->attributes['status']);
}
```

`JobCardStatus::from()` on the backed value. Store `$next->value` on the way back in — not the case
name, not the label. The label is for people; the value is for the column.

</details>

<details>
<summary>Hint 3 — the exception needs a JobCardId</summary>

`IllegalStatusTransition::from()` wants one, and you have a string column:

```php
public function id(): JobCardId
{
    return JobCardId::fromString((string) $this->attributes['id']);
}
```

Rung 3 again, quietly: an identity that was a string is now a type, and it cost one method.

</details>

<details>
<summary>Hint 4 — if a check in section 6 fails</summary>

Those checks read your class's source with comments stripped and look for words. If one fails, you
have imported or named something from rung 5. The likely candidates are `Workshop\JobCard` (the
aggregate — you want `JobCardId` and `JobCardStatus`, which are different classes) or a repository
you added to make `save()` feel cleaner.

</details>

---

## When it passes

Compare with `solution/`. Worth looking at:

- The doc block naming which rung each part of the class is, and what it cost
- The honest note on `__set` being a runtime check rather than a type, and that not going away until
  rung 5
- Why `transitionTo()` writes the attribute directly rather than fighting its own guard

---

## Then, without running anything

**Q1.** `__set` is a runtime check. Write down what you would lose and what you would gain by moving
to rung 5 purely to make it a compile-time one — and then say whether that alone would justify it.

**Q2.** The record stores status as the enum's backed value, so old rows load and new rows are
readable by code that never heard of the enum. Name the change you could have made instead that would
have been irreversible, and say why that matters more in a legacy system than in a new one.

**Q3.** Module 2's aggregate boundaries did **not** transfer — README section 6. Write two sentences
on what you would actually do about `$jobCard->vehicle->registration` in a codebase where 200 places
already use it.

**Q4.** This challenge deliberately produced a *second* model of the job card — `JobCardRecord`
alongside `Workshop\JobCard` — in a course that spent Module 1 arguing against exactly that. Reconcile
the two positions, then say what you would do if both existed in one real codebase.
