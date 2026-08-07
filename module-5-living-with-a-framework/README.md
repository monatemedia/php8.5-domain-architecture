# Module 5 — Living With a Framework

> **Built and verified on PHP 8.5.7.** Two lessons, two challenges, two quizzes, and Gate 5 in
> `GATES.md`.

| Lesson | Subject | You build |
|--------|---------|-----------|
| [5.1](lesson-5.1-when-the-framework-owns-your-models/) | When the framework owns your models | `Legacy\JobCardRecord` — three rungs of the ladder, and no more |
| [5.2](lesson-5.2-strangling-a-legacy-model/) | Strangling a legacy model | `PartStock`, two new exception leaves, `Legacy\StockReservations` |

---

## What the module is for

Four modules built a domain layer with no framework in it. **Almost nobody reading this gets to start
there.**

What you have is a Laravel application from 2019 where the Eloquent model *is* the domain object and
is used in 200 places. Rewriting it is not on the table and should not be — a rewrite that takes eight
months and delivers the same features is how architecture gets a bad name in an organisation.

So this module is not "how to do DDD in Laravel". It is two questions:

1. **Given that the ORM owns your model, which parts of the previous four modules can you have, what
   does each cost, and where do you stop?**
2. **How do you introduce a domain layer into working software without stopping to rewrite it?**

Deliberately framework-neutral. The course ships a sixty-line Active Record base you can read in
full, and shows the Data Mapper shape beside it, because those two cover nearly everything in PHP.
A challenge written against Eloquent would teach Eloquent's API, and half the people taking this
course are on Symfony.

---

## The two arguments

**5.1 — the ladder.** Named methods (free) → invariants in the model (cheap) → value objects over
existing columns (cheap) → typed exceptions and boundary mapping (cheap) → a separate domain layer
with a mapper (expensive). Rungs 1–4 capture most of Modules 1, 2 and 4 with no schema change, no
migration and no rewrite.

The surprise is rung 4. `ProblemDetailsMapper` keys on `failureCode()` and never asks where an
exception came from, so the problem body from an Active Record model is **byte-identical** to the one
from a domain aggregate. **Module 4 is the cheapest large improvement available to a legacy PHP
application**, and the challenge asserts it rather than claiming it.

**5.2 — the strangler.** Extract one aggregate, at the invariant that is generating incidents. Keep
the signature identical so a call site changes by one word. Load the dirty data and *count* it before
enforcing anything. Write a characterisation test, name the three places the old code was wrong, and
decide each one deliberately.

Then **stop at one**, while two worse services are still sitting there. That looks like an unfinished
job and it is the discipline: a strangler that grows to three services in its first week is a rewrite
that has not admitted it yet.

---

## What the module is honest about

**Module 2's aggregate boundaries do not transfer.** `$jobCard->vehicle->registration` is one property
access the whole team already writes, and forbidding it buys consistency guarantees the application
was not making anyway. You can hold the line on new code; you will not retrofit it.

**The capstone's "zero infrastructural imports" is an ideal for greenfield**, not a bar to hold an
existing application to. A course that implies otherwise sends people back to work to pick fights they
cannot win.

**"Done" is the wrong target.** A codebase where 30% is modelled properly and 70% is a well-behaved
Active Record layer is the correct outcome, not a failure — and the 30% is the part where incorrect
state is expensive. Anyone who wants a completion date is asking about a rewrite.

---

## Where else this shows up

| Stack | "The model" is | Rung 5 is |
|-------|----------------|-----------|
| Laravel / Eloquent | an Active Record | a second model plus a mapper. Expensive. |
| Symfony / Doctrine | a Data Mapper entity | often unnecessary — the entity is already most of an aggregate |
| CodeIgniter, legacy in-house | an array from a query | rung 1 is where the value is; there is no model to improve |
| Rails, Django | an Active Record | the same conversation, a different language |

---

## Running it

```bash
php verify.php      # every reference solution, plus the regression sweep
php check.php       # where you are
php regression.php  # just the sweep, when it is what failed
```

Module 5 introduces one harness change: a lesson's `challenge/given/` directory is registered as a
third `Infrastructure\` root, for code the course supplies rather than the student writing. The
miniature ORM lives there.
