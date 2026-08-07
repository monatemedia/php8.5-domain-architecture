# Lesson 1.1 — Rich vs Anemic Models
> **Module 1: Domain Modelling** · Advanced Domain Architecture

| | |
|---|---|
| **Time** | ~25 min reading · ~25 min running the examples. Challenge on top. |
| **Comes after** | Module 0, and Gate 0 |
| **Covers** | The anemic pattern · Why it is the default · Moving behaviour to the data · Tell-Don't-Ask · Asymmetric visibility · When anemic is right |
| **You write** | `domain/workshop/CourtesyVehicle.php` |
| **Needs PHP** | 8.5+ |

---

## 📁 Lesson folder

```
lesson-1.1-rich-vs-anemic/
├── README.md
├── examples/
│   ├── 01-the-anemic-pattern.php        ← the shape, and why it looks fine
│   ├── 02-the-second-caller.php         ← where it actually fails
│   ├── 03-moving-behaviour-inward.php   ← the same model, rich
│   └── 04-asymmetric-visibility.php     ← PHP 8.4/8.5 tools for the job
├── challenge/
│   ├── CHALLENGE.md
│   ├── checks.php                       ← the spec. Read it first.
│   ├── starter.php
│   └── solution/
└── quiz/QUIZ.md
```

---

## 1 — The anemic pattern

An **anemic domain model** is a class that holds data and nothing else — public properties, or
getters and setters for every field — with all the behaviour in a separate "service" that operates
on it.

```php
final class CourtesyVehicle
{
    public string  $fleetCode = '';
    public bool    $allocated = false;
    public ?string $allocatedToJobCard = null;
}

final class CourtesyVehicleService
{
    public function allocate(CourtesyVehicle $v, string $jobCardReference): void
    {
        if ($v->allocated) {
            throw new RuntimeException('Already out.');
        }
        $v->allocated = true;
        $v->allocatedToJobCard = $jobCardReference;
    }
}
```

Structurally this is a **record plus functions**. It is a procedural program, and the fact that the
record is spelled `class` does not change that. Every property is public, so every invariant the
service enforces is a suggestion.

---

## 2 — Why it is the default, not a mistake

The usual framing — "anemic models are an anti-pattern" — is unhelpful, because it implies
carelessness. The real reasons are structural:

**Every ORM hands you this shape for free.** Active Record models, generated entities, scaffolding —
all of them produce a class with a property per column. That is the correct output for an ORM,
whose job is to represent a row.

**It works.** For a system with shallow rules, an anemic model plus a service is clear, easy to
follow, and easy to change. Module 0 said this and it is still true. Most software should stop
here.

**Layered-architecture tutorials teach it.** "Controllers call services, services operate on
models" is the most widely taught structure in web development, and it describes an anemic model
without naming it.

So the objection is never "anemic models are bad". It is narrower:

> **This particular model has eleven rules spread across four services, and nobody can tell you
> what a valid one is.**

That sentence is checkable. "Anemic models are an anti-pattern" is not.

---

## 3 — Where it actually fails

Not where people expect. It does not fail because the code is ugly. It fails at **the second
caller**.

The service enforces the rule. Then, six months later:

- A bulk import script sets `allocated = true` directly, because calling the service for 4,000 rows
  was slow.
- An admin "fix it" screen assigns the field, because the service refused and the operator needed
  it done.
- A second service appears — `FleetService` — which also allocates, and implements the rule
  slightly differently.
- A test factory constructs the object in a state the service would never produce, and now a test
  passes that should not.

Every one of those is reasonable in isolation. None of them is prevented by anything. The rule was
never *enforced*; it was *observed*, by whoever remembered.

**The diagnostic question:** if I hand you an instance of this class, can you tell me it is valid?
With an anemic model, the honest answer is "depends where it came from" — which is the same answer
as Lesson 0.2's shared-model tell, and for the same underlying reason.

`examples/02-the-second-caller.php` runs this.

---

## 4 — Moving behaviour to the data that owns it

The fix is not "put everything in the entity". It is narrower and more mechanical:

> **A rule about the state of X belongs on X.**

```php
final class CourtesyVehicle
{
    public private(set) bool    $allocated = false;
    public private(set) ?string $allocatedToJobCard = null;

    public function __construct(public readonly string $fleetCode) {}

    public function allocateTo(string $jobCardReference): void
    {
        if ($this->allocated) {
            throw new CourtesyVehicleAlreadyOut($this->fleetCode, $this->allocatedToJobCard);
        }
        $this->allocated          = true;
        $this->allocatedToJobCard = $jobCardReference;
    }

    public function returnToFleet(): void
    {
        if (!$this->allocated) {
            throw new CourtesyVehicleNotOut($this->fleetCode);
        }
        $this->allocated          = false;
        $this->allocatedToJobCard = null;
    }
}
```

What changed:

| Before | After |
|--------|-------|
| `$v->allocated = true` legal from anywhere | Only `allocateTo()` can set it |
| The rule lives in a service | The rule lives on the object |
| Two entry points, two implementations | One entry point |
| "Is this valid?" — depends where it came from | "Is this valid?" — yes, always |

Note what did **not** change: no interface, no abstract class, no factory, no repository. Moving a
rule inward is a small move, and doing only the small move is usually correct.

---

## 5 — Tell, don't ask

The style rule underneath section 4, and a useful thing to have in your fingers.

```php
// ASK — the caller fetches state, decides, and mutates
if (!$vehicle->allocated) {
    $vehicle->allocated = true;
    $vehicle->allocatedToJobCard = $ref;
}

// TELL — the caller states intent; the object decides
$vehicle->allocateTo($ref);
```

Every "ask" is a decision made outside the object, which means it is a decision that can be made
*differently* somewhere else. Count the `if` statements in your services that read another object's
state: each one is a rule that has escaped.

**A caveat, because this rule is over-applied.** "Tell, don't ask" is about *decisions*, not about
*reads*. Asking an object for its value in order to display it, sum it, or log it is fine and
always will be. The smell is asking for state in order to decide what to do to that same object.

---

## 6 — The PHP tools for this

### Asymmetric visibility — PHP 8.4

```php
public private(set) bool $allocated = false;
```

Readable everywhere, writable only from inside the class. This is the single most useful language
feature for this lesson: it removes the getter boilerplate that made anemic models tempting, while
keeping the write protected.

Before 8.4 you wrote a private property plus a public getter — six lines to say what one now says.

### `readonly` — PHP 8.1

```php
public function __construct(public readonly string $fleetCode) {}
```

For state that is fixed at construction. A fleet code identifies the vehicle; it does not change.

### `readonly class` — PHP 8.2

Every property readonly, enforced by the class declaration. Correct for value objects (Lesson 1.2),
wrong for entities, which by definition change over time.

### Constructor promotion — PHP 8.0

```php
public function __construct(public readonly string $fleetCode) {}   // one line, not four
```

> **On older versions:** all of these have plain equivalents — private property plus getter, a
> `final` class with no setters, manual assignment in the constructor. The design does not depend on
> the syntax. See `COURSE_PHILOSOPHY.md`, *A Note on PHP Versions*, carried over from the previous
> course.

---

## 7 — When anemic is right

Because Rule 6 does not stop applying inside Module 1.

**A DTO is meant to be anemic.** A structure carrying data across a boundary — an API request
shape, a form payload, a row read for a report — has no invariants to protect because it is not the
model. Putting behaviour on it is the opposite error.

**A read model is meant to be anemic.** Lesson 3.3 builds these. An object whose only job is to be
rendered does not need to refuse anything.

**A record with no rules is fine anemic.** If the only thing you can say about a valid one is "the
fields are the right types", a rich model has nothing to enforce and you have added a file.

The tell for "this should be rich" is not "it is a domain concept". It is: **there is a rule about
its state, and more than one place can currently break it.**

---

## 8 — Common mistakes

| Mistake | Why it is wrong | Instead |
|---------|----------------|---------|
| Moving *all* logic into entities | Some operations genuinely belong to no single entity | Lesson 2.4 covers domain services properly |
| Keeping setters "for the ORM" | The ORM can be taught to hydrate without them — Lesson 3.2 | Named operations; hydrate via a mapper |
| Replacing setters with `withX()` on an entity | Entities have identity and change in place; withers are for value objects | Named operations that mutate |
| Adding a getter for every private property | You have rebuilt the anemic model with more typing | Expose what callers genuinely need, and no more |
| Rich models everywhere, including DTOs | Section 7 | Rich where there are rules |
| Enforcing the rule in the entity *and* the service | Two copies drift; the day they disagree is a bug | One home. The boundary may still validate for a good message — see 1.4 |

---

## 9 — Where else this shows up

The anemic/rich distinction is not specific to DDD. It is the same argument as:

- **Encapsulation**, from the first week of any OOP course, applied to business rules rather than to
  linked lists
- **"Primitive obsession"** in refactoring literature, one level up
- **Immutable state containers** in front-end work: reducers own the transitions, components do not
  mutate

If you have ever been annoyed by a React component that reaches into a store and sets a field
directly, you already have the instinct. This is that, with money attached.

---

## ✅ Lesson checklist

- [ ] Read this README fully — especially sections 3 and 7
- [ ] Run and study `examples/01-the-anemic-pattern.php`
- [ ] Run and study `examples/02-the-second-caller.php`
- [ ] Run and study `examples/03-moving-behaviour-inward.php`
- [ ] Run and study `examples/04-asymmetric-visibility.php`
- [ ] Read `challenge/checks.php` before writing anything
- [ ] Complete the challenge — `php .../challenge/starter.php` prints `ACCEPTANCE: all checks passed`
- [ ] Compare against `challenge/solution/` and log any disagreement in `PROGRESS.md`
- [ ] Take `quiz/QUIZ.md` closed-book

---

*Next lesson: **1.2 — Value Objects** — the same argument, at the smallest possible scale.*
