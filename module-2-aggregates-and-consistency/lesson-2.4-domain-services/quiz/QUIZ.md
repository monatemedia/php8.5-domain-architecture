# Quiz — Lesson 2.4: Domain Services
> Closed book. **20 questions. Threshold: 15.**
> The last quiz in Module 2. Then Gate 2.

---

## Section A — Multiple choice

**Q1.** Which is **not** one of the four tests for a genuine domain service?

- A) The business has a name for the operation.
- B) It belongs to no single object.
- C) It is registered in the container.
- D) It works only on domain objects.

---

**Q2.** "Apply a tax rate to an amount" is not a domain service because:

- A) It is arithmetic.
- B) A rate and an amount are all you need, so it belongs on the value that owns the knowledge.
- C) `Money` should own it.
- D) Tax is infrastructure.

---

**Q3.** "Decide courtesy vehicle eligibility" *is* one because:

- A) It has "decide" in the name.
- B) It needs a job card **and** a negotiated threshold, and neither can hold the other.
- C) It returns a boolean.
- D) It is called from a controller.

---

**Q4.** The difference between a domain service and an application service:

- A) Domain services are `final readonly`.
- B) Application services take identifiers and orchestrate — load, decide, act — and depend on
  infrastructure.
- C) Domain services live in a different namespace.
- D) Application services are stateful.

---

**Q5.** `CourtesyVehicleEligibility::isEligible(JobCardId $id)`, loading the job card inside, would:

- A) Be more convenient and otherwise identical.
- B) Make it an application service, belonging outside `domain/`.
- C) Break the type system.
- D) Be fine as long as the repository is an interface.

---

**Q6.** Why is the minimum spend passed in rather than a constant on `JobCard`?

- A) Constants cannot be `Money`.
- B) It is a policy — 0 for fleet accounts, different last year, and it will move again.
- C) `JobCard` is already too large.
- D) Testing would be harder.

---

**Q7.** Tax rates are stored as basis points because:

- A) They are smaller than floats.
- B) Real rates are not always whole percentages, and a float in money arithmetic is the bug value
  objects exist to prevent.
- C) The database requires integers.
- D) Percentages are ambiguous.

---

**Q8.** `intdiv($x * $bp + 5000, 10000)` is used instead of `round($x * $bp / 10000)` because:

- A) It is faster.
- B) The second introduces a float into money arithmetic.
- C) `round()` is banned in PHP 8.5.
- D) It handles negative numbers better.

---

**Q9.** Returning `reasonsAgainst()` rather than a bare `false`:

- A) Is a presentation concern that belongs at the boundary.
- B) Lets a service adviser act, and lets the workshop manager correct a sentence they can read.
- C) Makes the class stateful.
- D) Is only useful for logging.

---

**Q10.** A healthy domain model of a sizeable system has roughly:

- A) One domain service per aggregate.
- B) Two or three domain services in total.
- C) One per use case.
- D) As many as there are entities.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 11 | "I'll put it in a service" is usually a symptom rather than a design. | |
| 12 | A class named `JobCardProcessor` passes the first of the four tests. | |
| 13 | `isEligible()` should be defined in terms of `reasonsAgainst()`, not the reverse. | |
| 14 | Twelve domain services around four getter-and-setter entities is a well-layered model. | |
| 15 | A domain service may hold a repository as long as it is an interface. | |

---

## Section C — Short answer

**Q16.** Argue that `CourtesyVehicleEligibility` is a *policy object*, not a domain service. Then
say whether the distinction changes a single line of code.

*Your answer:*

---

**Q17.** `applyTo()` rounds half up. Name a rule that would break it, and give the signature you
would use to support both.

*Your answer:*

---

**Q18.** Give one operation from a system you know that currently lives in a service and should be a
method on something. Then one that is correctly a service, and say which of the four tests it
passes.

*Your answer:*

---

**Q19.** The upper bound on `TaxRate` refuses anything above 100%. Excise duties above 100% exist.
Is the guard wrong? Answer using Lesson 1.4's three kinds of guard.

*Your answer:*

---

**Q20.** Module 2 is done. `domain/workshop/` has twenty-two classes and no way to save any of them.
Name the single hardest thing about persisting `JobCard`, given what you did to it in 2.1.

*Your answer:*

---
---

<details>
<summary><strong>Answer key</strong></summary>

**Q1 — C.** Container registration is a wiring detail and says nothing about what a class is.

**Q2 — B.** Two values, both in hand — Lesson 1.4's test. (C is the interesting wrong answer: tax
does not belong on `Money` either, because `Money` is used for prices, costs and thresholds that
have nothing to do with tax. The knowledge belongs to the rate.)

**Q3 — B.** Neither can hold the other: the job card must not hold a negotiated threshold, and the
threshold cannot see the job card.

**Q4 — B.** Identifiers and orchestration, with infrastructure. The test in one line: delete the
database — does it still make sense to someone who runs the business?

**Q5 — B.** It would need a repository, so it could not be constructed without infrastructure. D is
the trap: an interface makes it *substitutable*, not *absent*, and the class still cannot be built
in a test with a number.

**Q6 — B.** A policy welded into an entity is a bug with a commencement date — nobody finds it until
the day it changes, and then the change is a deployment rather than a setting.

**Q7 — B.** 12.5%, 5.5% and 7.25% all exist, and a float rate is a float in money arithmetic
arriving through the back door.

**Q8 — B.** No float anywhere. Adding half the divisor turns integer truncation into round-half-up.

**Q9 — B.** Both halves matter, and the second is the one people miss: a domain expert reading
`false` learns nothing, and reading the sentence may well say "fleet accounts don't have a minimum".
That is a missing rule found in a meeting rather than an incident.

**Q10 — B.** Very few. If you have twenty, the behaviour has gone to the wrong place.

**Q11 — TRUE.** Lesson 1.1, and adding "domain" to the front does not change it.

**Q12 — FALSE.** Nobody in the workshop has ever said "processor". A name the business does not use
usually means you have invented a container for logic that belongs on something with a real name.

**Q13 — TRUE.** Otherwise they are two implementations of one rule, and the day they disagree is a
bug nobody can adjudicate.

**Q14 — FALSE.** It is an anemic model with more folders — architecture theatre from Lesson 0.1 §7.
Every cost paid, no guarantee bought.

**Q15 — FALSE.** An interface makes the dependency substitutable, not absent. The class still cannot
be constructed without infrastructure, still cannot be built in a test from a number, and still
fails the "delete the database" question. It is an application service with a domain-sounding name.

**Q16** *The case:* it holds a negotiated threshold and answers a question about it. That is a
policy — a rule the business currently prefers — and "policy object" describes it more precisely
than "domain service", which is a category so broad it mostly tells you where the class does *not*
live.

*Does it change any code?* **No. Not one line.** Same class, same methods, same constructor, same
directory. Which is the useful thing to notice: the two terms overlap almost entirely, and a long
argument about which word applies produces no different software.

That is worth remembering whenever DDD vocabulary starts generating more discussion than code. What
mattered here was that the threshold is **passed in** rather than welded into `JobCard` — and that
decision is the same under either name.

**Q17** Any rule that does not round half up: rounding tax *down* per line, which several
jurisdictions require; or rounding the invoice total rather than each line, which produces a
different figure on a multi-line invoice and is the more common divergence.

The signature becomes something like:

```php
public function applyTo(Money $amount, Rounding $rounding = Rounding::HalfUp): Money
```

with `Rounding` a backed enum. Note what that does: it turns a hidden assumption into a stated
parameter with a default. The default keeps every existing caller working, and the parameter means
the assumption is now visible to anyone who needs a different one — which is the honest version of
"we picked half up".

**Q18** No key — these are yours. Two checks on your own answer. For the first: can you name the
*object* the operation belongs to? If not, it may genuinely be a service. For the second: does it
pass all four tests, or only the one you thought of? Test 4 catches most false positives — if it
loads anything, it is an application service.

**Q19** It is a **sanity check**, not a business rule, and it is right in this codebase and would be
wrong in another.

Its purpose is to catch a units error — somebody passing `20` meaning 20%, or `0.2` meaning a
fraction — and at the scale of a vehicle workshop no legitimate rate approaches 100%. So it catches
typos and refuses nothing real.

But Lesson 1.4 §3 warned that a sanity check with no override path becomes a business rule by
accident. If this class were reused where excise duties above 100% are ordinary, the guard would
refuse a true rate, somebody would enter a wrong one to get past it, and the resulting invoice would
be believed. The honest handling is what the reference implementation does: say in the code that it
is a sanity check, so the next person knows it is theirs to move rather than a fact about tax.

**Q20** The lines. `JobCard` holds a private `array<string, Line>` where `Line` is an *interface*
with two implementations, both `readonly`, both with private constructors and no setters.

To rebuild one from a database you have to reconstruct each line without calling `create()` — which
mints a **new** `LineId`, so calling it would give every line a fresh identity on every load and
`removeLine()` would stop working across requests. So reconstitution needs a way in that the public
API deliberately does not provide, and it must not re-run the guards that already passed when the
data was first written.

Then the same problem one level up: `JobCard::open()` records a `JobCardOpened` event, and loading a
job card must not announce that a vehicle was booked in for the second time.

That is the mapping problem, it is Lesson 3.2, and it is the thing that stops people in practice —
which is exactly why every rich model you have built is now the obstacle.

</details>

---

**Score:** ____ / 20   ·   **Threshold: 15**

Then **Gate 2** in `GATES.md`, with every file shut. That is Module 2 finished.
