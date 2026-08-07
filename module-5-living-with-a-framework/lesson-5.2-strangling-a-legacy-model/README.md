# Lesson 5.2 — Strangling a Legacy Model

> **Module 5 · Lesson 2 of 2 · the last lesson before the capstone**
> Prerequisites: 5.1. You will extract exactly one aggregate and leave two services alone.

---

## 1. The only realistic way in

Lesson 5.1 ended with a ladder and a warning: rung 5 — a separate domain layer — is expensive, and
you should not start there in an application with no test suite.

This lesson is the path that works instead. It has a name, from Martin Fowler: the **strangler fig**,
after the plant that grows around a tree until the tree is gone and the fig is standing on its own.
The useful part of the metaphor is not the ending. It is that **the tree is load-bearing the entire
time.**

The alternative is the rewrite, and rewrites fail in a predictable way: six months in, the new system
does eighty percent of what the old one does, the last twenty percent turns out to be where all the
business rules were, and nobody can remember why the old code has a special case for fleet accounts
in March.

**A strangler is not a slower rewrite.** It is a different thing, and the difference is that at every
point you can stop and still be better off than when you started. That property is what gets it
approved.

---

## 2. The exercise

The course supplies a legacy parts-stock system: one row class and three services.

```
Infrastructure\Legacy\PartStockRow          part_number, on_hand, reserved. No behaviour.
Infrastructure\Legacy\ReservationService    reserve() and release(). Half-enforces the rule.
Infrastructure\Legacy\PickingService        fit(). Enforces nothing at all.
Infrastructure\Legacy\StockReportService    available(), isLow(). Reads.
```

The invariant nobody wrote down:

```
0 <= reserved <= onHand
```

You cannot promise the same alternator to two job cards, and a shelf cannot hold minus four of
anything. `ReservationService` enforces the upper bound on `reserve()` and nothing else.
`PickingService` will drive `on_hand` negative without comment.

**You extract one aggregate — `Workshop\PartStock` — and strangle one service.** The other two are
still there when you finish, and the checks assert it.

---

## 3. Where to start: the invariant that bites

Not the most interesting aggregate. Not the biggest table. **The invariant that is generating
incidents.**

Stock qualifies: the symptom is a job card promised parts that are not on the shelf, discovered by a
technician standing at the rack, and it ends in a phone call to a customer. That is the kind of
problem somebody will let you spend a week on.

Why that matters more than the technical merits: an extraction that nobody asked for competes with
feature work and loses. An extraction that closes a class of incident has a sponsor.

Three secondary tests, in order:

**Does the rule span more than one field?** `reserved <= onHand` does. A rule about one field is
usually a value object — cheaper, and Lesson 1.2 already covered it.

**Is there a single obvious place a change can come from?** Two services write these columns. That is
few enough to strangle one and see.

**Can you name the incident?** If not, you are extracting because you read a book. Come back when
you can.

---

## 4. Same signature, or it does not ship

```php
ReservationService::reserve(PartStockRow $row, int $quantity): void
StockReservations::reserve(PartStockRow $row, int $quantity): void
```

Identical. A call site changes by one word — the type it asks the container for — and nothing else.

**This is the property most often given up first, and giving it up is what kills the extraction.**
The moment the new method needs different arguments, every call site has to change in the same pull
request. A two-day change becomes a six-week branch, the branch gets a merge conflict with a release,
and it is abandoned.

So the aggregate takes an `int` even though `InvalidQuantity` exists to say the int was wrong, and
even though a `Quantity` value object would make the invalid case unrepresentable and be strictly
better. **Improve the signature later, when the call sites are yours.** That is a real cost of
working this way and it is worth stating plainly rather than pretending the strangler is free.

The adapter — `toAggregate()` and `apply()` — is an **anti-corruption layer**, which is a grand name
for two private methods. It is Lesson 3.2's mapper, smaller, pointed at a table somebody else
designed.

---

## 5. Load the dirty data, then count it

`PartStock::of()` **does not enforce the invariant**. It will happily build a stock with nine reserved
and three on hand.

That looks wrong and is the most important decision in the lesson.

A factory that refused an inconsistent row would be correct in principle and undeployable in
practice: the first bad row in production takes down the screen that was going to show you how many
bad rows there are. **Legacy data is not clean, and an extraction that cannot load the data is not an
extraction.**

Lesson 3.2's rule again — *the invariant governs changes, and loading is not a change* — arriving
somewhere much less comfortable, because there the rows had been written by code that enforced the
rules and here they have not.

So instead:

```php
#[\NoDiscard('The point of asking is the answer')]
public function isConsistent(): bool;
```

Not a guard. A **report**. Run it across the table before touching a single call site:

- **Eleven bad rows** → fix them, carry on.
- **Forty thousand** → stop. The invariant you were about to enforce is not the one the business has
  been operating under. That is a conversation, not a commit.

**Most failed extractions fail exactly here, and they fail late** — after the new path is written,
when it starts throwing on data that has been fine for six years and somebody rolls it back on a
Friday.

There is one thing `of()` does refuse: a **negative** count. A row saying minus four are on the shelf
is not dirty data, it is impossible data — a bug to be found rather than tolerated. The distinction
is worth having: inconsistent means two fields disagree, impossible means one field is nonsense.

---

## 6. The characterisation test

You are about to replace a procedure that has been running for six years. The only honest question is
**what it currently does** — not what it was supposed to do, and not what the ticket says.

A characterisation test runs both paths over the same inputs and records where they differ:

| Input | Legacy | New | |
|-------|--------|-----|-|
| reserve 1 of 6 free | reserved 5 | reserved 5 | agree |
| reserve 6 of 6 free | reserved 10 | reserved 10 | agree |
| reserve 7 of 6 free | refused | refused | agree |
| release 4 of 4 held | reserved 0 | reserved 0 | agree |
| **reserve −5** | **reserved −1** | **refused** | differ |
| **reserve 0** | **reserved 4** | **refused** | differ |
| **release 9 of 4 held** | **reserved −5** | **refused** | differ |

The three divergences are **named**, because *"we found some bugs"* is not a decision and *"we
changed behaviour in these three cases"* is. It is what goes in the pull request description and what
somebody signs off.

### Then decide, and it is not automatic

For each divergence, three questions:

1. **Is anything relying on it?** Grep the call sites. Check the logs. `reserve(-5)` is reachable from
   a form field with no validation — is anything actually sending negatives?
2. **What happens if you fix it?** Somebody's flow that limped along now throws. Better, usually —
   but "usually" is doing work in that sentence.
3. **Can you fix it separately?** A behaviour change shipped inside a refactor is how a refactor gets
   blamed for an outage. Sometimes the right move is to reproduce the bug in the new path and remove
   it in a second, tiny commit that says exactly what it is doing.

The reference implementation fixes all three, because nobody depends on being able to reserve minus
five. **The method of deciding is the transferable part**, not the answer.

---

## 7. Stop at one

`PickingService` is worse than anything you fixed. `fit()` will drive `on_hand` to minus three, and
every availability calculation downstream is then wrong. It is still there when you finish, and the
checks assert that it still is — and that it *still breaks the invariant*.

That looks like an unfinished job. It is the discipline.

**A strangler that grows to three services in its first week is a rewrite that has not admitted it
yet.** Extract one aggregate, prove the new path matches the old where the old was right, ship it,
stop.

The reward for stopping: `PickingService` is now trivially convertible. `PartStock::consume()` already
exists and already refuses. Somebody changes four lines in an afternoon, with your characterisation
tests to lean on — and they can do it next quarter, or never, and the codebase is still better than
it was.

**The reward compounds and the risk does not.** That is the whole argument for working this way.

`StockReportService` is left alone for a different reason: it is a **read model** wearing a service's
name, and Lesson 3.3 already settled what to do with those. It decides nothing and every method is a
subtraction. Routing it through an aggregate would load an object in order to do arithmetic that is
already correct.

---

## 8. The tree grows two leaves, and something notices

`InsufficientStock` and `InvalidQuantity` are the tenth and eleventh leaves of Module 4's tree, added
by somebody working on a legacy extraction two modules later.

**This is the exact case Lesson 4.4 wrote an exhaustiveness check for.** That check walks every leaf
and asserts each maps to something other than 500, so the two new ones cost four lines of catalogue
in `ProblemDetailsMapper` — and, crucially, *not adding them* would have failed the build rather than
quietly producing opaque 500s that nobody found until a client asked.

Where they hang follows 4.2 exactly:

- `InsufficientStock` is a `WorkshopException` → **409**. Whether six alternators are enough depends
  on the shelf, and only the stock knows.
- `InvalidQuantity` is an `InvalidWorkshopInput` → **422**. Minus five is negative in the controller,
  in the form, and in the caller's own variable.

And **no `StockException` node** for a single class. Lesson 4.2's rule holds: name the catch clause
and the field it holds, or you are drawing a diagram. When `PickingService` is strangled and brings a
second stock failure, the node earns its place then.

---

## 9. What "done" looks like

You have not migrated the application. You have:

- one invariant that is now impossible to break **through one path**
- a named list of three behaviour changes, each decided deliberately
- a survey of how much bad data exists
- two services that still work exactly as they did
- a domain class that can be tested without a database, a row, or an ORM

And the next extraction is cheaper than this one was, because the adapter pattern exists, the
exception tree has a stock branch, and somebody has seen it work.

**That is a good quarter.** A course that promised more would be lying about what fits between the
feature work.

---

## 10. Where else this shows up

| Domain | The invariant that bites | Strangle first |
|--------|--------------------------|----------------|
| E-commerce | reserved inventory vs. on hand | the reservation service. Identical shape to this lesson. |
| Insurance | claim total vs. policy limit | claim submission |
| Banking | available vs. cleared balance | the transfer path |
| Publishing | published state vs. required fields | the publish action |

Every row is the same shape: **two numbers or two states that must agree, written from more than one
place, with the rule enforced in some of them.** If you can name one in your own codebase, you have
found your first extraction.

---

## 11. Examples

```bash
php module-5-living-with-a-framework/lesson-5.2-strangling-a-legacy-model/examples/01-rewrite-or-strangle.php
php module-5-living-with-a-framework/lesson-5.2-strangling-a-legacy-model/examples/02-characterisation.php
php module-5-living-with-a-framework/lesson-5.2-strangling-a-legacy-model/examples/03-count-before-you-enforce.php
```

Example 03 is the one to run if section 5 felt like caution. It surveys a table, finds the bad rows,
and shows the deployment that would have failed.

---

## 12. Then

- `challenge/CHALLENGE.md` — one aggregate, two leaves, one strangler
- `quiz/QUIZ.md` — 20 questions, threshold 15
- `GATES.md` — **Gate 5**, which reaches back to Modules 1, 3 and 4

Module 5 is done. Next is the **capstone**: the workshop end to end, with a test that scans every
import in `domain/` and proves there is nothing infrastructural in any of them.
