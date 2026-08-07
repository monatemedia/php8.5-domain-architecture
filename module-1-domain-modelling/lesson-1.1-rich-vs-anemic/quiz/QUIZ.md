# Quiz — Lesson 1.1: Rich vs Anemic Models
> Closed book. **20 questions. Threshold: 15.**

---

## Section A — Multiple choice

**Q1.** An anemic domain model is best described as:

- A) A class with too few methods.
- B) A record plus functions — data in one class, all behaviour in a service.
- C) A class that does not use inheritance.
- D) A model with no database backing.

---

**Q2.** The most accurate reason anemic models are so common is:

- A) Developers are careless about design.
- B) Every ORM, scaffold and layered-architecture tutorial hands you that shape for free.
- C) They perform better.
- D) They are easier to test.

---

**Q3.** An anemic model plus a service *first* fails when:

- A) The class grows past 200 lines.
- B) A second caller changes the state without going through the service.
- C) The database schema changes.
- D) The team adopts static analysis.

---

**Q4.** Which of these bypasses is the *most* dangerous, and why?

- A) The bulk import, because it touches the most rows.
- B) The admin fix-it screen, because a human triggered it.
- C) The test factory, because it makes tests pass against a state production cannot produce.
- D) The second service, because it duplicates code.

---

**Q5.** "Tell, don't ask" objects to:

- A) Any method that returns a value.
- B) Reading an object's state in order to decide what to do to that same object.
- C) Getters in general.
- D) Public properties.

---

**Q6.** `public private(set) bool $allocated = false;` means:

- A) Readable and writable everywhere.
- B) Readable everywhere, writable only from inside the class.
- C) Writable once, at construction.
- D) Readable only from inside the class.

---

**Q7.** Which is correct for a property that is fixed at construction and never changes?

- A) `public private(set)`
- B) `public readonly`
- C) `private` with a setter
- D) `public`

---

**Q8.** Why is `final readonly class` wrong for an entity?

- A) Entities need inheritance.
- B) Entities change over their lifetime; readonly forbids writes even from inside after construction.
- C) `readonly` classes cannot have methods.
- D) It is not wrong — entities should be readonly.

---

**Q9.** Which of these *should* stay anemic?

- A) A job card with a lifecycle.
- B) A DTO carrying an API request payload across a boundary.
- C) A courtesy vehicle with allocation rules.
- D) A money value.

---

**Q10.** In the challenge, `allocateTo('')` throws `\InvalidArgumentException` and allocating an
already-out vehicle throws `\DomainException`. The distinction is:

- A) Arbitrary — either would do.
- B) Whether the identical call could succeed at a different moment.
- C) Whether the error is recoverable.
- D) Whether the caller is internal or external.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 11 | Replacing every public property with a getter makes a model rich. | |
| 12 | An entity should use `withX()` methods to return modified copies. | |
| 13 | Moving a rule onto an entity usually requires introducing an interface and a factory. | |
| 14 | A class with `private(set)` on every property is guaranteed not to be anemic. | |
| 15 | Reading an object's value in order to display it violates "tell, don't ask". | |

---

## Section C — Short answer

**Q16.** In the challenge you were told a refused operation must leave the object unchanged. Why
does this matter more in Module 2 than it does here?

*Your answer:*

---

**Q17.** The reference solution stores one field (`?string $allocatedToJobCard`) and derives
availability from it, rather than storing two. State the general principle, and give one situation
where storing both would be the better call.

*Your answer:*

---

**Q18.** Your `CourtesyVehicle` now enforces "cannot be allocated twice". `DOMAIN.md` invariant I10
says a courtesy vehicle is on at most one open job card *at any time*. Are these the same rule?
Explain.

*Your answer:*

---

**Q19.** A colleague adds `public function setAllocatedToJobCard(?string $ref): void` to your class
"so the ORM can hydrate it". What has been lost, and what would you propose instead?

*Your answer:*

---

**Q20.** Give one concrete rule from your own work, or from a system you know, that currently lives
in a service and would be better on the object. Then give one that is currently on an object and
should not be.

*Your answer:*

---
---

<details>
<summary><strong>Answer key</strong></summary>

**Q1 — B.** A record plus functions. The word `class` is doing no work; structurally it is a
procedural program.

**Q2 — B.** The shape is handed to you by every tool and most teaching material. Framing it as
carelessness makes the diagnosis useless, because the people writing it are not being careless.

**Q3 — B.** The second caller. On day one, with one caller, an anemic model is indistinguishable
from a rich one in behaviour.

**Q4 — C.** The test factory, because it removes your ability to detect the others. A test suite
running against states production cannot produce is testing a different system, and it will report
green while the real one is broken. The bulk import at least fails visibly eventually.

**Q5 — B.** The smell is asking for state in order to decide what to do to that same object. Reads
for display, summing or logging are fine and always will be — the rule is routinely over-applied to
mean "no getters", which it does not.

**Q6 — B.** Read out, write in. PHP 8.4.

**Q7 — B.** `public readonly`.

**Q8 — B.** `readonly` blocks writes even from inside the class after construction, so an entity
with a lifecycle cannot express its own transitions. Value objects want exactly that; entities do
not.

**Q9 — B.** A DTO. It has no invariants because it is not the model — it is a shape for carrying
data across a boundary, and putting behaviour on it is the opposite error.

**Q10 — B.** Whether the identical call could succeed at another moment. Empty string: never
acceptable, in any state → argument problem. Already out: acceptable tomorrow → business rule. That
distinction is the seed of Module 4's exception tree.

**Q11 — FALSE.** Getters plus setters is the anemic model with more typing. What makes a model rich
is that the *rules* live on it.

**Q12 — FALSE.** Withers are for value objects, which have no identity and are replaced wholesale.
An entity has continuous identity and changes in place; a `withStatus()` on an entity produces two
objects that are supposedly the same thing, and then you have to decide which one is real.

**Q13 — FALSE.** The challenge produced one class and two exception types. Interfaces, factories
and repositories arrive later, if the rules justify them — and frequently they do not.

**Q14 — FALSE.** A class with `private(set)` everywhere and an `allocate()` method that assigns
without checking anything is anemic with better syntax. The language protects the rule you wrote; it
does not write it.

**Q15 — FALSE.** See Q5. Reads for display are not decisions.

**Q16** Here, `allocateTo()` makes one assignment, so validating late leaves at worst one field
wrong and the fix is obvious. In Module 2 an aggregate method changes several things at once — a
status, a collection, a total, a recorded event — and a guard that fires after two of the four have
been applied leaves an object that is neither its old state nor its new one. Nothing catches that:
the exception propagates, the caller handles it, and a half-mutated aggregate stays in memory and
gets saved. Validating everything before mutating anything makes a refusal genuinely atomic, which
is the only thing that makes "the object is always valid" true rather than aspirational.

**Q17** *The principle:* the strongest way to enforce a relationship between two pieces of state is
to store only one of them. Two fields that must agree can disagree; one field cannot, and no
discipline is required to keep it that way.

*Where storing both is better:* when the derived value is expensive and read often — a total across
a thousand lines, recomputed on every read. Then you store both and accept that you must maintain
the invariant, usually by making the only write path the one that updates both. Lesson 2.1's job
card total is exactly this trade-off, and it goes the other way there for a reason worth reading.

**Q18** No, and the difference is the second object. Your rule is about *one vehicle in memory*: it
refuses a second `allocateTo()` on the same instance. I10 is about *the world*: at any moment, no
courtesy vehicle appears on two open job cards. Your version is defeated by loading the same vehicle
twice, allocating each copy to a different job card, and saving both — every individual object
obeyed its rule, and the system is still wrong. Enforcing I10 requires something that can see all
open job cards at once, which no single entity can, and deciding whether the check must be
instantaneous or may be eventually consistent. That decision is Lesson 2.2 and it belongs to the
workshop manager as much as to you.

**Q19** What has been lost is every guarantee the class provides. A public setter for the job card
reference means the "already out" rule can be bypassed in one line, and "is this object valid?" goes
back to "depends where it came from" — which is exactly where the lesson started.

What to propose instead depends on which problem they actually have. If it is genuinely about
loading from storage, the answer is a mapper that reconstitutes the object without running the
constructor's guards — Lesson 3.2 is entirely about this, and it does not require a setter. If the
ORM cannot be persuaded, that is a real constraint and Lesson 5.1 covers the compromises honestly.
What is not acceptable is adding a public setter and calling it a persistence concern: the ORM will
use it once and application code will use it forever.

**Q20** No key — these are yours. Two things to check in your own answer:

For the first: can you name the *second place* that currently breaks the rule? If you cannot, the
rule may be fine where it is, and moving it is Rule 6 being ignored inside the module about the
patterns.

For the second: the most common right answer is something that needs data the object does not have
— a rule that depends on other objects, on configuration, or on the current time. Those are not
entity rules and forcing them onto an entity is how entities acquire dependencies they should not
have.

</details>

---

**Score:** ____ / 20   ·   **Threshold: 15**
