# Lesson 2.1 — Aggregate Roots and Boundaries ⭐
> **Module 2: Aggregates & Consistency** · Advanced Domain Architecture

| | |
|---|---|
| **Time** | ~35 min reading · ~30 min running the examples. Challenge is the largest so far. |
| **Comes after** | Module 1, and Gate 1 |
| **Covers** | What an aggregate is · Choosing a boundary · The root as the only door · Protecting the collection · Lifecycles as state machines · Stored vs derived totals, revisited |
| **You write** | `Line.php`, `LineId.php`, `PartNumber.php`, `PartLine.php`, `JobCardStatus.php`, `JobCardId.php`, `AccountHolderId.php`, `JobCard.php` — and one addition to `LabourLine.php` |
| **Needs PHP** | 8.5+ |

---

## 📁 Lesson folder

```
lesson-2.1-aggregate-roots-and-boundaries/
├── README.md
├── examples/
│   ├── 01-what-a-boundary-is-for.php    ← two objects that must be right together
│   ├── 02-the-leaky-collection.php      ← the getter that undoes every invariant
│   └── 03-lifecycle-as-a-state-machine.php
├── challenge/
│   ├── CHALLENGE.md
│   ├── checks.php                       ← the spec. Long. Read it first.
│   ├── starter.php
│   └── solution/
└── quiz/QUIZ.md
```

---

## 1 — The definition, and what it excludes

> **An aggregate is the set of objects that must be correct *together, at the same instant*, inside
> one transaction.**

That is the whole definition. Read what it excludes:

- It is **not** "things that belong together conceptually". A vehicle and its job cards belong
  together conceptually and are two aggregates.
- It is **not** "everything reachable from this object". Reachability is a property of your object
  graph, not of the business.
- It is **not** a table, a module, a namespace, or a screen.

The **aggregate root** is the one object outside code is allowed to hold a reference to. Everything
inside is reached through it, changed through it, and never handed out in a form that can be
modified behind its back.

```
        ┌─ JobCard ──────────────────────────┐
   ───► │  (the root — the only way in)      │
        │                                    │
        │   status                           │
        │   lines: [ LabourLine, PartLine ]  │  ← reachable ONLY through the root
        │   total (derived from lines)       │
        └────────────────────────────────────┘
                     │
                     │  VehicleId, AccountHolderId
                     ▼
            other aggregates, referenced by identity only
```

---

## 2 — Choosing the boundary

The question is never *"is a line part of a job card?"* It is:

> **If these two changed a second apart, would the business be harmed?**

Worked through, for this domain:

| Pair | A second apart? | Verdict |
|------|-----------------|---------|
| A job card and its lines | **Harmful.** For that second the total does not match the lines, and somebody could invoice it. | One aggregate |
| A job card and its vehicle | Harmless. Correcting a mileage reading has nothing to do with what is being billed. | Two aggregates |
| A job card and the account holder | Harmless. Changing a billing address mid-repair is fine. | Two aggregates |
| A job card and a courtesy vehicle | **Arguable**, and it is invariant I10. Lesson 2.2. | Deliberately unresolved here |

Notice that the answers come from the business, not from the code. "Would the business be harmed" is a question you ask a person.

### Big aggregates are the second most common failure

After the shared model from Lesson 0.2. And they fail *slowly*, which is what makes them expensive:

```php
❌  JobCard holds the full AccountHolder, which holds every other JobCard
```

Everything works with forty rows. Then:

- Loading one job card loads the account holder's entire history.
- Two clerks editing two unrelated job cards for the same account holder now collide, because the
  transaction covers both.
- The "load one aggregate" call becomes the slowest query in the system, and nobody can safely make
  it smaller because everything depends on the shape.

**The rule:** reference other aggregates **by identity**, never by object.

```php
✅  final class JobCard {
        private VehicleId       $vehicleId;
        private AccountHolderId $accountHolderId;
    }
```

This looks like a downgrade the first time — you have lost `$jobCard->vehicle->registration`. That
loss is the point. It is the boundary being visible in the code, and getting the registration is now
somebody's explicit decision to load a second aggregate.

---

## 3 — One aggregate per transaction

The corollary, stated now and paid for in Lesson 2.2:

> **An aggregate boundary *is* a transaction boundary. Change one aggregate per transaction.**

Which immediately raises: what about a rule spanning two? That is invariant I10, it is the whole of
Lesson 2.2, and the honest short answer is that you choose between making the boundary bigger
(usually wrong) and accepting that the rule holds *eventually* (usually right) — and the choice
belongs to the business, not to you.

---

## 4 — The leaky collection

The single most common way an aggregate that looks correct enforces nothing.

```php
final class JobCard
{
    /** @var list<Line> */
    private array $lines = [];

    public function lines(): array
    {
        return $this->lines;      // looks harmless
    }
}
```

PHP copies arrays on assignment, so the caller cannot append to *your* array. But the array holds
**objects**, and objects are handles:

```php
$line = $jobCard->lines()[0];
$line->changeQuantity(9999);      // if Line is mutable, every guard above is decorative
```

Lesson 1.3 relied on exactly this and said so: returning the reading history was safe *because
`OdometerReading` is immutable*. Here the contents are entities, so it is not safe by default.

**Three defences, and you want the first two:**

1. **Make the contents immutable where you can.** `PartLine` and `LabourLine` in this challenge are
   `readonly`, so handing them out is safe. Changing a line means removing it and adding another —
   which sounds heavy-handed until you notice it forces every change through the root, where the
   invariants live.
2. **Never expose the collection for modification.** No `addLine()` on the line, no array returned
   by reference, no `ArrayObject`.
3. **Return a copy if the contents are genuinely mutable.** A last resort — a deep copy is easy to
   get subtly wrong, and it means you have mutable entities inside an aggregate, which is worth
   questioning first.

`examples/02-the-leaky-collection.php` runs the failure.

---

## 5 — The lifecycle is a state machine, and it belongs in one place

`DOMAIN.md` gives the job card's transitions exhaustively. **Everything not in that table must
throw.**

The temptation is to scatter the checks — `if ($this->status === Completed)` in `invoice()`, another
check in `complete()`, another in `cancel()`. That is Lesson 1.4's scattered-guards problem wearing
a lifecycle costume.

Put the transition rules on the enum:

```php
enum JobCardStatus: string
{
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }
}
```

Now there is one table, it reads like the one in `DOMAIN.md`, and a business person can check it.
The aggregate's methods each state *which* transition they are attempting and let the enum decide
whether it is legal.

Two things this buys that are easy to miss:

- **`Invoiced` and `Cancelled` are terminal** simply by having an empty list of allowed successors.
  No special-casing.
- A new state is one row, not a hunt through nine methods.

---

## 6 — Stored vs derived totals — the opposite conclusion to Lesson 1.4

Lesson 1.4 made `LabourLine::total()` derived, and argued that *the cheapest invariant is one that
cannot be written down wrong*. That was correct there.

**Invariant I5 says a job card's total always equals the sum of its lines.** Derive it and the
invariant is unrepresentable. So this course derives it too — and you should know why that decision
is less obvious here, because Lesson 1.4's follow-up question pointed straight at it.

| | Derived | Stored |
|---|---------|--------|
| Can drift from the lines | never | yes, if any path updates one without the other |
| Cost of reading | O(lines), every time | O(1) |
| A rate change next year | retroactively re-prices history | history is fixed |

The third row is the one that decides it in a real billing system, and it is *not* decided in favour
of deriving. An invoice is a statement of what was charged and must not change afterwards.

**What saves the derived version here is Lesson 1.2.** `Money` is immutable and each line holds its
own copy of the rate it was created with, so recomputing next year gives the same answer. The
derivation is safe *because the inputs are frozen*, not because deriving is inherently safe.

The moment that stops being true — a change to how tax is calculated, a new rounding rule — the
calculation itself becomes historical, and you must store the computed total alongside the parts and
accept the drift risk knowingly. Module 3 comes back to this when the aggregate has to be persisted.

> Two lessons in this course reaching opposite conclusions about the same technique is not an
> inconsistency. It is what *"it depends"* looks like when both sides are actually stated.

---

## 7 — What goes *inside* the boundary

A practical checklist for the challenge:

| Thing | Inside `JobCard`? | Why |
|-------|:-----------------:|-----|
| `LabourLine`, `PartLine` | ✅ | The total must match them at every instant |
| `JobCardStatus` | ✅ | It *is* the job card's state |
| `VehicleId` | ✅ as an id | The reference is inside; the vehicle is not |
| `Vehicle` | ❌ | Different aggregate. Changing the odometer must not lock the job card. |
| `AccountHolder` | ❌ | Same |
| `CourtesyVehicle` | ❌ | Same — and I10 is Lesson 2.2 |
| The tax rate | ❌ | A policy. Passed in. `DOMAIN.md`, "What is not an invariant". |

---

## 8 — Common mistakes

| Mistake | Why it is wrong | Instead |
|---------|----------------|---------|
| Holding other aggregates by object | Loads and locks far too much; collisions between unrelated edits | Hold their identity |
| Returning the internal collection | Callers mutate entities behind the root's back | Immutable contents; no modification path |
| Transition checks scattered across methods | Lesson 1.4, with a lifecycle | One table on the enum |
| An `addLine()` that validates after appending | A refused call leaves the line attached | Validate first, mutate last |
| A setter on the root "just for the status" | Every transition rule becomes optional | Named operations: `complete()`, `invoice()` |
| Making the boundary match the screen | The UI changes; the consistency requirement does not | Ask what must be true together |
| Aggregates for everything | Rule 6. Most objects need no boundary at all. | Only where an invariant spans objects |

---

## 9 — Where else this shows up

The shape is *one root owning a collection, with a lifecycle and a point of no return*:

| Here | E-commerce | Insurance | Accounting |
|------|-----------|-----------|------------|
| JobCard → lines | Order → order lines | Claim → items | Journal → entries |
| Cannot invoice incomplete | Cannot charge unfulfilled | Cannot settle unassessed | Cannot close unbalanced |
| Invoiced is terminal | Charged is terminal | Settled is terminal | Posted is terminal |
| References VehicleId | References CustomerId | References PolicyId | References AccountId |

If your aggregate has no lifecycle and no collection, it is probably an entity that does not need a
boundary — and Rule 6 applies.

---

## ✅ Lesson checklist

- [ ] Read this README fully — especially sections 2, 4 and 6
- [ ] Run and study `examples/01-what-a-boundary-is-for.php`
- [ ] Run and study `examples/02-the-leaky-collection.php`
- [ ] Run and study `examples/03-lifecycle-as-a-state-machine.php`
- [ ] Re-read the transition table in `DOMAIN.md` — the challenge implements it exactly
- [ ] Read `challenge/checks.php` before writing anything. It is the longest one yet.
- [ ] Complete the challenge
- [ ] **Re-run Lesson 1.4's starter.** It must still pass — see the note in `CHALLENGE.md`.
- [ ] Compare against `challenge/solution/`, log disagreements in `PROGRESS.md`
- [ ] Take `quiz/QUIZ.md` closed-book

---

*Next lesson: **2.2 — Transactions: What a Boundary Actually Wraps** — and invariant I10, which
nothing you have built so far can enforce.*
