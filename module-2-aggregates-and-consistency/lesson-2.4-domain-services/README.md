# Lesson 2.4 — Domain Services
> **Module 2: Aggregates & Consistency** · Advanced Domain Architecture

| | |
|---|---|
| **Time** | ~25 min reading · ~20 min running the examples. Challenge is the smallest in Module 2. |
| **Comes after** | Lesson 2.3 — Domain Events |
| **Covers** | The four tests · Domain vs application service · Why "I'll put it in a service" is a symptom · Placing three operations · Returning reasons, not booleans |
| **You write** | `TaxRate.php`, `CourtesyVehicleEligibility.php` |
| **Needs PHP** | 8.5+ |

---

## 📁 Lesson folder

```
lesson-2.4-domain-services/
├── README.md
├── examples/
│   ├── 01-three-operations.php       ← the same three, placed three ways
│   └── 02-domain-or-application.php  ← the line most codebases smear
├── challenge/
│   ├── CHALLENGE.md
│   ├── checks.php
│   ├── starter.php
│   └── solution/
└── quiz/QUIZ.md
```

---

## 1 — Start from the suspicion

Most classes with `Service` in the name should not exist.

Lesson 1.1 said the anemic model puts all the behaviour in a "service" that operates on data, and
that this is a procedural program wearing an object costume. Nothing in this lesson retracts that.
**"I'll put it in a service" is usually a symptom, not a design.**

So this lesson is mostly about *not* writing one. The genuine case is narrow, and the way to find it
is to fail four tests.

---

## 2 — The four tests

All four must hold. Fail any one and it belongs somewhere else.

### 1. The business has a name for it

The service manager says *"does this one qualify for a courtesy car?"* — that is an operation with a
name, and it existed before software did.

`JobCardProcessor`, `OrderManager`, `VehicleHelper` fail here. Nobody in the workshop has ever said
any of them, which usually means you have invented a container for logic that belongs on something
with a real name.

### 2. It belongs to no single object

Lesson 1.4's test, one level up: **can you decide it holding only one object?**

- *Apply tax to an amount* — a rate and an amount. Two values, both in hand. **Fails.** It belongs
  on the rate.
- *Does this job card qualify for a courtesy vehicle?* — needs the job card **and** a negotiated
  threshold. The job card must not hold the threshold; the threshold cannot see the job card.
  **Passes.**

### 3. It is stateless

Constructed with its policy, then asked. It remembers nothing between calls.

A "service" that accumulates results, caches, or has a `$lastResult` is not a domain service; it is
an object with a lifecycle nobody designed. Rule 5 of the previous course, and Module 6 of it is
about what stateful services cost.

### 4. It works only on domain objects

Hand it a `JobCard` and it answers. No repository, no clock, no HTTP, no database.

**If it took a `JobCardId` and loaded the job card, it would be an application service** — and that
is the line most codebases smear. Section 4.

---

## 3 — Placing three operations

The challenge, in miniature. Three operations from this domain, and where each belongs:

| Operation | Home | Why |
|-----------|------|-----|
| Apply a tax rate to an amount | **`TaxRate`**, a value object | Needs a rate and an amount. Nothing else. The knowledge of how much tax a rate produces is a fact about the rate. |
| Authorise additional work | **`JobCard`**, the entity | It changes that job card's state, and only that job card's. Already written, in 2.1. |
| Decide courtesy vehicle eligibility | **a domain service** | Needs a job card *and* a threshold the workshop negotiates. Neither can hold the other. |

**Two of the three are not services**, and that ratio is roughly right for a real model.

> **Why the tax rate is not a `TaxCalculator`.** A service taking a rate and an amount is the same
> arithmetic with an extra class and an extra injection. It is exactly the kind of "service" that
> makes people believe domain services are ceremony — and when they look like this, they are.

> **Why eligibility is not `$jobCard->isEligibleForCourtesyVehicle()`.** It would read beautifully.
> It is wrong because the minimum spend is **negotiated** — 0 for fleet accounts, it was different
> last year, and it will move again. That is a policy (`DOMAIN.md`, *What is not an invariant*), and
> a policy welded into an entity is a bug with a commencement date.

---

## 4 — Domain service versus application service

The distinction that decides which directory a class lives in, and the one most codebases get
wrong.

| | Domain service | Application service |
|---|---|---|
| Takes | domain objects | identifiers, requests, DTOs |
| Does | decides something | orchestrates: load, call, save, dispatch |
| Depends on | nothing | repositories, dispatchers, clocks, transactions |
| Lives in | `domain/workshop/` | outside it |
| Is it business logic? | yes | no — it is plumbing that *calls* business logic |

```php
// DOMAIN service — decides.
final readonly class CourtesyVehicleEligibility {
    public function isEligible(JobCard $jobCard): bool;
}

// APPLICATION service — orchestrates. Not in domain/.
final class AllocateCourtesyVehicle {
    public function __construct(
        private JobCards $jobCards,           // a repository
        private CourtesyFleet $fleet,
        private CourtesyVehicleEligibility $eligibility,
    ) {}

    public function __invoke(JobCardId $id, CourtesyVehicleId $vehicleId): void {
        $jobCard = $this->jobCards->get($id);            // load
        if (!$this->eligibility->isEligible($jobCard)) { // decide  <- the domain
            throw new \DomainException(/* ... */);
        }
        $this->fleet->allocate($vehicleId, $id);         // act
    }
}
```

**The test, in one line:** *if you deleted the database, would this class still make sense to
someone who runs the business?* Rule 1, and the question the whole course is built on.

The eligibility service survives that. The allocation service does not, and should not — it is
supposed to be plumbing.

---

## 5 — Return reasons, not booleans

A small decision with a large payoff, and the challenge tests it.

```php
public function reasonsAgainst(JobCard $jobCard): array;   // list<string>, empty when it qualifies
public function isEligible(JobCard $jobCard): bool;        // reasonsAgainst() === []
```

**Why.** "Not eligible" sends a service adviser to guess. *"Job card JC-1001 comes to EUR 22.50,
below the EUR 150.00 minimum for a courtesy vehicle"* lets them tell the account holder something
true, and it lets them see immediately if the rule is wrong.

That second part is Rule 4 doing real work: **the workshop can correct a sentence, and cannot
correct a boolean.** A domain expert reading `false` learns nothing; reading that sentence, they may
well say "no, fleet accounts don't have a minimum" — and you have found a missing rule in a meeting
instead of in an incident.

The boolean is then defined in terms of the reasons, not the other way round, so the two can never
disagree.

---

## 6 — When a service is the wrong answer

Three symptoms, all of which mean stop.

**It has one method and one caller.** That is a function. Rule 6 — a class you invoke once is
ceremony unless the name is doing work.

**It is called `XManager` or `XProcessor`.** Nobody in the business says those words. Ask what the
operation is actually called; the answer usually names a method on an existing object.

**Every entity is anemic and every service is fat.** This is the diagnosis Lesson 1.1 gave, and
adding "domain" to the front of "service" does not change it. Twelve domain services around four
getter-and-setter entities is an anemic model with more folders, and it is architecture theatre from
Lesson 0.1 §7.

> The rough ratio to expect: a healthy model has **very few** domain services. Two or three in a
> sizeable system. If you have twenty, the behaviour has gone to the wrong place.

---

## 7 — Common mistakes

| Mistake | Why it is wrong | Instead |
|---------|----------------|---------|
| A service for an operation on one object | It belongs on that object | Put it there |
| A service holding a repository, in `domain/` | It is an application service | Move it out |
| A policy hard-coded in the service | Same bug as hard-coding it in the entity | Pass it in |
| A stateful service | An object lifecycle nobody designed | Construct with policy; remember nothing |
| Returning a bare `false` | Nobody can act on it and nobody can correct it | Reasons |
| `XManager`, `XProcessor`, `XHelper` | Nobody says these words | Ask what it is called |
| Many services, thin entities | Anemic model, more folders | Lesson 1.1 |

---

## 8 — Where else this shows up

The domain/application split is the same line as:

- **Pure functions versus effectful ones.** A domain service is a pure function over domain objects;
  an application service is the part that does IO.
- **Reducers versus thunks** in front-end state management — same shape, same reasons.
- **A database function versus a stored procedure that opens a cursor.** One decides, one
  orchestrates.

If you can say why a pure function is easier to test than one that reads a file, you already have
the argument.

---

## ✅ Lesson checklist

- [ ] Read this README fully — especially sections 2 and 4
- [ ] Run and study `examples/01-three-operations.php`
- [ ] Run and study `examples/02-domain-or-application.php`
- [ ] Read `challenge/checks.php`
- [ ] Complete the challenge
- [ ] `php regression.php` — everything earlier, against your newest code
- [ ] Compare against `challenge/solution/`, log disagreements in `PROGRESS.md`
- [ ] Take `quiz/QUIZ.md` closed-book
- [ ] Take **Gate 2** in `GATES.md`, with every file shut

---

*Module 2 ends here. `domain/workshop/` now holds twenty-two classes, two aggregate roots, ten
invariants and three events. Module 3 makes you save it — and the mapping problem is where most
people meet the cost of everything you have built.*
