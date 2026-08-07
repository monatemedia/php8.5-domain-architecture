# Lesson 2.2 — Transactions: What a Boundary Actually Wraps ⭐
> **Module 2: Aggregates & Consistency** · Advanced Domain Architecture

| | |
|---|---|
| **Time** | ~35 min reading · ~30 min running the examples. Challenge on top. |
| **Comes after** | Lesson 2.1 — Aggregate Roots and Boundaries |
| **Covers** | One aggregate per transaction · The rule that spans two · Eventual consistency as a business decision · Compensating actions · Cardinality decides the shape |
| **You write** | `CourtesyVehicleId.php`, `CourtesyFleet.php` — and one addition to `CourtesyVehicle.php` |
| **Needs PHP** | 8.5+ |

---

## 📁 Lesson folder

```
lesson-2.2-transactions-and-consistency/
├── README.md
├── examples/
│   ├── 01-one-aggregate-per-transaction.php   ← the half-applied write
│   ├── 02-the-rule-that-spans-two.php         ← I10, and the hole nothing in Module 1 closes
│   └── 03-eventually-consistent.php           ← the other answer, with its window measured
├── challenge/
│   ├── CHALLENGE.md
│   ├── checks.php
│   ├── starter.php
│   └── solution/
└── quiz/QUIZ.md
```

---

## 1 — The rule, and the trouble it causes

Lesson 2.1 ended on a sentence that sounded like a footnote:

> **An aggregate boundary *is* a transaction boundary. Change one aggregate per transaction.**

Two reasons, and the second is the one people underrate.

**Correctness.** An aggregate guarantees it is never invalid. That guarantee is only worth anything
if the whole of it is written at once — a half-applied write leaves a state the model says cannot
exist, and there is no rollback for an object already in memory.

**Contention.** A transaction locks what it touches. Two aggregates per transaction means two locks,
in an order somebody has to think about, and the moment two code paths pick different orders you
have a deadlock that appears only under load.

The trouble is that businesses have rules that span aggregates, and `DOMAIN.md` has one:

> **I10 — a courtesy vehicle is on at most one open job card at any time.**

You have been carrying that invariant since Lesson 1.1, where the follow-up questions raised it and
left it. Module 1 could not enforce it. Lesson 2.1 declared it out of scope. This lesson answers it.

---

## 2 — Why nothing so far can enforce I10

Your `CourtesyVehicle` from Lesson 1.1 refuses a second allocation. That looks like I10 and is not.

```php
$vehicle = new CourtesyVehicle('CV-01');
$vehicle->allocateTo('JC-1001');
$vehicle->allocateTo('JC-1002');   // refused. Good.
```

Now the same thing, the way it actually happens:

```php
$copyA = $repository->find($id);   // loaded for job card 1001
$copyB = $repository->find($id);   // loaded for job card 1002, in another request

$copyA->allocateTo('JC-1001');     // fine
$copyB->allocateTo('JC-1002');     // also fine — it is a different object

$repository->save($copyA);
$repository->save($copyB);         // one car, two open job cards
```

**Every individual object obeyed its rule, and the system is wrong.** That is the shape of every
cross-aggregate consistency bug, and no amount of care inside a single entity closes it.

`examples/02-the-rule-that-spans-two.php` runs exactly this.

---

## 3 — Answer 1: put the rule inside a boundary that can see it

If a rule spans two things, find the object that owns *both* and put the rule there.

For I10 that object is the **fleet**. A `CourtesyFleet` holds every courtesy vehicle, so there is
exactly one instance of each — which is precisely what the two-copies bug needed and did not have.

```
     ┌─ CourtesyFleet ───────────────────────┐
───► │  (root)                               │
     │    CV-01  → JC-1001                   │
     │    CV-02  → (available)               │
     │    CV-03  → JC-4471                   │   ← I10 lives here.
     └───────────────────────────────────────┘      It can see all of them.
```

**What this buys:** I10 is instantaneous and transactional. Two concurrent allocations serialise on
one aggregate, and the second one is refused. No windows, no compensation, no reconciliation job.

**What it costs, and it is not small:**

- Every allocation now locks the entire fleet. Two allocations of two *different* cars contend with
  each other for no business reason.
- Loading the fleet loads every vehicle in it.
- The fleet is a bottleneck by construction, and the busier you get the worse it is.

> **This is the large-aggregate failure from Lesson 2.1 §2, chosen deliberately rather than
> stumbled into.** The difference is that you know what you bought.

---

## 4 — Cardinality decides whether §3 is right

The single most useful thing in this lesson.

| Fleet size | Allocations per day | Verdict |
|-----------:|--------------------:|---------|
| 12 | ~8 | Fine. Contention is theoretical; the aggregate loads in a millisecond. |
| 200 | ~150 | Getting uncomfortable. Contention is real at 9am. |
| 10,000 | ~7,000 | Broken. This is the Lesson 2.1 failure, now in production. |

**The same design is correct and incorrect depending on a number that has nothing to do with the
code.** A workshop with twelve courtesy cars should absolutely put I10 in a fleet aggregate and
never think about it again. A national rental company must not.

Two things follow, and both are uncomfortable:

1. **You cannot decide this from the model.** You have to ask how many, and how often. If nobody
   knows, you are guessing, and you should say so out loud rather than picking the sophisticated
   option.
2. **The right answer changes over time.** A design that was correct at twelve cars is not wrong
   because someone made a mistake; it is wrong because the business grew. That is a normal and
   healthy reason to change a boundary, and treating it as a past failure discourages people from
   ever choosing the simple thing.

---

## 5 — Answer 2: let it hold *eventually*

The alternative. Do not make the rule instantaneous. Allow the allocation, detect a collision after
the fact, and **compensate**.

```
   t=0    JC-1001 allocates CV-01     ──┐
   t=0    JC-4471 allocates CV-01     ──┤ both succeed. The rule is violated.
                                        │
   t=+2s  a check notices two claims  ──┤
   t=+2s  the later one is revoked    ──┘ the rule holds again.
```

**What this buys:** no shared lock, no bottleneck, no fleet-sized aggregate. It scales to the
national rental company.

**What it costs:** there is a window — here two seconds — during which the business rule is
false. Somebody has to decide that is acceptable, and somebody has to write the compensation, and
the compensation has real-world consequences: a phone call to a customer who was told they had a
car.

**A compensating action is not a rollback.** You cannot un-tell someone they had a car. What you can
do is issue a correction and make it visible. If your compensation is silent, you have not made the
system eventually consistent — you have made it quietly wrong.

`examples/03-eventually-consistent.php` runs this, measures the window, and shows a compensation
that is visible rather than silent.

---

## 6 — Which one is not your decision

The most important paragraph in Module 2.

> **"Is it acceptable that two people both thought they had CV-01 for ten seconds?"**

That is not a technical question. It has a right answer, and the answer lives in the workshop.

- **Courtesy car:** almost certainly fine. Somebody rings the second customer, apologises, and
  offers CV-02. The cost is a phone call.
- **Operating theatre:** absolutely not. There is no compensation for two surgeries booked into one
  room.
- **Seat on a flight:** airlines deliberately choose the eventually-consistent version and
  compensate with money. They have priced the phone call.
- **Money leaving a bank account:** no. The compensation — clawing funds back — is sometimes
  impossible.

Notice that the *technique* is identical in all four and the *decision* is completely different.
This is why a developer who picks between them alone, at their desk, is not making an engineering
decision — they are guessing at a business one and hiding it in a class.

**How to actually ask.** Not "should this be eventually consistent?", which means nothing to anyone.
Ask: *"if two of your people both booked the same courtesy car within a few seconds, what would
happen today?"* The answer is usually a description of an existing manual process — which tells you
both that the collision already happens and how the business already compensates for it.

---

## 7 — What the aggregate must NOT do

Two temptations, both of which recreate the problem you just solved.

**Do not hand out the members.** `CourtesyFleet` holds `CourtesyVehicle` objects, and those are
*mutable entities*. Returning them is Lesson 2.1's leaky collection with sharper teeth: a caller
holding a `CourtesyVehicle` can allocate it directly, behind the fleet's back, and I10 is defeated
again.

So the fleet's accessors return **identities**, never vehicles. That is why the challenge's
`availableVehicles()` gives you a `list<CourtesyVehicleId>`.

**Do not let members in from outside either.** If `add(CourtesyVehicle $vehicle)` took an object,
the caller would keep a handle to it, which is the same leak pointing the other way. The challenge
therefore has the fleet *create* its own vehicles:

```php
$id = $fleet->addVehicle('CV-01');   // returns the identity, not the vehicle
```

An aggregate root creating its own members is a normal and underused pattern. The rule is simple:
**nothing inside the boundary should have a reference to it from outside.**

---

## 8 — The cost of additive-only change, which you will feel

Lesson 2.1 changed `LabourLine` additively and it was free. This lesson changes `CourtesyVehicle`
additively and it is not quite free, and that is worth watching.

`CourtesyVehicle::allocateTo()` takes a `string $jobCardReference`, because it was written in Lesson
1.1, before `JobCardId` existed. Changing the parameter type would break Lesson 1.1's checks. So the
fleet converts:

```php
$vehicle->allocateTo($jobCardId->value());          // typed -> string, going in
JobCardId::fromString($vehicle->allocatedToJobCard()); // string -> typed, coming out
```

That round trip is a **wart**, it is real, and it is left in on purpose. It is exactly what
constrains you in a codebase you cannot freely change — and Module 5 is about doing this at scale to
code somebody else wrote. Noticing that the cost of not breaking things is *paid somewhere* is more
useful than a course where every refactor is clean.

The honest fix, when you can take it, is one deprecation cycle: add `allocateToJobCard(JobCardId)`,
migrate callers, remove the old one. That is three commits and a conversation, not a rewrite.

---

## 9 — Common mistakes

| Mistake | Why it is wrong | Instead |
|---------|----------------|---------|
| Two aggregates in one transaction | Two locks, order-dependent, deadlocks under load | One per transaction; §3 or §5 for the rest |
| Growing the boundary until the rule fits | The aggregate becomes the system — 2.1 §2 | Ask about cardinality first |
| Choosing eventual consistency for elegance | It is a business decision with a phone call attached | Ask the workshop |
| A silent compensating action | The system is quietly wrong rather than eventually right | Make the correction visible |
| Handing out the aggregate's members | The rule is bypassed behind the root's back | Return identities |
| Accepting members from outside | The caller keeps a handle — the same leak, reversed | Let the root create them |
| Treating a grown-out design as a past failure | Discourages ever choosing the simple thing | Boundaries are allowed to change |

---

## 10 — Where else this shows up

Every one of these is the same decision:

| Domain | The spanning rule | Usually resolved by |
|--------|------------------|---------------------|
| Ticketing | One seat, one booking | Eventually consistent, compensated with money |
| Warehousing | Stock reserved once | A reservation aggregate — §3 |
| Banking | An account cannot go below zero | Instantaneous. Always. |
| Calendars | One room, one meeting | Usually §3; the cardinality is small |
| Usernames | Uniqueness across all users | A unique index — the database is doing §3 for you |

That last row is worth sitting with. **A unique constraint in a database is a spanning rule enforced
by a shared lock**, and it is the correct answer far more often than a hand-rolled aggregate. Rule 6
applies to consistency strategies too.

---

## ✅ Lesson checklist

- [ ] Read this README fully — especially sections 4 and 6
- [ ] Run and study `examples/01-one-aggregate-per-transaction.php`
- [ ] Run and study `examples/02-the-rule-that-spans-two.php`
- [ ] Run and study `examples/03-eventually-consistent.php`
- [ ] Read `challenge/checks.php`
- [ ] Complete the challenge
- [ ] **Re-run Lesson 1.1's starter.** It must still pass.
- [ ] Compare against `challenge/solution/`, log disagreements in `PROGRESS.md`
- [ ] Take `quiz/QUIZ.md` closed-book

---

*Next lesson: **2.3 — Domain Events** — how an aggregate tells the rest of the system what happened
without knowing that anyone is listening. It is also how the compensation in §5 gets triggered
without the fleet knowing a compensation exists.*
