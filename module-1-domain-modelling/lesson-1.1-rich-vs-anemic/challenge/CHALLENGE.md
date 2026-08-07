# Challenge — Lesson 1.1: Make the Courtesy Vehicle Own Its Rules

> **You write:** `domain/workshop/CourtesyVehicle.php`
> **You run:** `php module-1-domain-modelling/lesson-1.1-rich-vs-anemic/challenge/starter.php`
> **Done when it prints:** `ACCEPTANCE: all checks passed`

---

## Read `checks.php` first

Seriously. It is in this folder, it is the specification stated precisely, and it is not hidden
from you. This prose brief exists to explain *why*; `checks.php` is *what*.

---

## The brief

The workshop lends out courtesy vehicles. Today the model is anemic — a record with public fields
and a `CourtesyVehicleService` that enforces the rules — and example 02 showed the four ordinary
ways that gets bypassed.

Rewrite it so the rules live on the object and there is no way past them.

Two rules, both from `DOMAIN.md`:

- **A courtesy vehicle that is already out cannot be allocated again.**
- **A courtesy vehicle that is not out cannot be returned.**

Plus the input guards you would expect: a fleet code is required, and so is a job card reference.

---

## The file

Create `domain/workshop/CourtesyVehicle.php` — from the course root, not from this folder.

```php
<?php
declare(strict_types=1);

namespace Workshop;

final class CourtesyVehicle
{
    // your work here
}
```

The namespace is `Workshop`, the file is `domain/workshop/CourtesyVehicle.php`, and
`lib/autoload-domain.php` finds it. That mapping is fixed for the whole course.

---

## The public API

The checks call exactly these. Nothing else is prescribed — how you store state is yours.

| Member | Behaviour |
|--------|-----------|
| `__construct(string $fleetCode)` | Throws `\InvalidArgumentException` if the code is empty or only whitespace |
| `fleetCode(): string` | The code it was constructed with |
| `isAvailable(): bool` | True when it is not out |
| `allocatedToJobCard(): ?string` | The job card reference it is out on, or `null` |
| `allocateTo(string $jobCardReference): void` | Throws `\InvalidArgumentException` on an empty reference; throws `\DomainException` if it is already out |
| `returnToFleet(): void` | Throws `\DomainException` if it is not out |

### On the exception types

Two different base classes, deliberately:

- **`\InvalidArgumentException`** — the caller passed nonsense. An empty string is not a fleet code
  in any universe, and no state of the vehicle would make it acceptable.
- **`\DomainException`** — the argument was fine but the *business rule* said no. "Already out" is
  a statement about this vehicle right now, and the same call would succeed tomorrow.

That distinction is the seed of Module 4's whole exception tree, and it is worth getting into your
fingers now. If you cannot decide which one a failure is, ask: *would the identical call succeed at
a different moment?* Yes → `DomainException`. Never → `InvalidArgumentException`.

You may subclass either. The checks assert against the base classes, so a subclass passes.

---

## Acceptance criteria

- [ ] The class is `final` and lives in `domain/workshop/CourtesyVehicle.php`
- [ ] No public property can be assigned from outside the class
- [ ] No public method whose name begins with `set`
- [ ] Both rules refuse with `\DomainException`
- [ ] Both input guards refuse with `\InvalidArgumentException`
- [ ] **A refused operation leaves the object unchanged** — this one is easy to miss
- [ ] A returned vehicle can be allocated again

That sixth criterion is the one worth thinking about. A method that validates *after* it has already
assigned half its state leaves a corrupted object behind when it throws. Check your ordering.

---

## Hints

<details>
<summary>Hint 1 — where do I put the state?</summary>

Two properties: whether it is out, and what it is out on. They are not independent — "out with no
job card" and "available but still pointing at a job card" are both nonsense, and example 02
produced both.

One option is to keep the nullable reference only, and derive availability from it:

```php
private ?string $allocatedToJobCard = null;

public function isAvailable(): bool
{
    return $this->allocatedToJobCard === null;
}
```

Now the two facts cannot disagree, because there is only one fact. That is a real technique and it
is worth more than it looks: **the strongest way to enforce a relationship between two pieces of
state is to store only one of them.**

The reference solution does it this way. If you kept both fields and made them consistent, that is
also correct — log the disagreement in `PROGRESS.md` and decide which you prefer.

</details>

<details>
<summary>Hint 2 — the "unchanged after refusal" criterion</summary>

```php
// Wrong: assigns, then validates
public function allocateTo(string $ref): void
{
    $this->allocatedToJobCard = $ref;
    if (/* already out */) { throw ...; }
}
```

Validate everything first, mutate last. In a method with one assignment this is easy; in Module 2's
aggregate methods with five, it is a discipline. Get into the habit now.

</details>

<details>
<summary>Hint 3 — asymmetric visibility</summary>

`public private(set) ?string $allocatedToJobCard = null;` gives you a property readable from
outside and writable only from inside — no getter needed. PHP 8.4+.

You do not have to use it. `private` plus the accessor methods listed above is equally correct and
works on any version. The checks pass either way; they test that outside code cannot *write*, not
how you achieved it.

</details>

<details>
<summary>Hint 4 — stuck on a specific check</summary>

Run the starter. It prints one line per assertion and names what failed, with the exception message
where there was one. Work down the list from the top — a failure early on usually causes several
later ones.

</details>

---

## When it passes

Open `solution/CourtesyVehicle.php` and compare. Look for:

- Where they validate versus where you do
- Whether they stored one field or two
- What their exception messages contain — and whether the message is built from data or hard-coded

Anything you would have done differently goes in the *Modelling decisions I disagreed with* table at
the foot of `PROGRESS.md`. That table is not decoration; being able to argue the case is the skill.

---

## Then, without running anything

Answer these two in writing.

**Q1.** You have moved the rules onto `CourtesyVehicle`. Somebody still has to answer *"which
courtesy vehicle is free?"* across the whole fleet. Can that live on `CourtesyVehicle`? If not,
where does it go, and what does that tell you about the limits of what you just did?

**Q2.** `DOMAIN.md` invariant I10 says a courtesy vehicle is on at most one open job card at any
time. You have just enforced something that looks like that. Are they the same rule? If not, what
is the difference, and which object could possibly enforce the other one?

Q2 is the door into Module 2. If your answer is "they are the same", read I10 again — the phrase
*at any time* is doing work, and there is a second object involved.
