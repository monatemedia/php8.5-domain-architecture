# Challenge — Lesson 2.2: The CourtesyFleet Aggregate

> **You write:** `domain/workshop/CourtesyVehicleId.php`, `domain/workshop/CourtesyFleet.php`
> — plus one addition to `CourtesyVehicle.php`
> **You run:** `php module-2-aggregates-and-consistency/lesson-2.2-transactions-and-consistency/challenge/starter.php`
> **Done when it prints:** `ACCEPTANCE: all checks passed`

You are finally enforcing **invariant I10**, which has been open since Lesson 1.1's follow-up
questions.

---

## Read `checks.php` first.

---

## 1 — `CourtesyVehicleId`

Same shape as the four identity classes you already have. `final readonly`, no public constructor,
`generate()` · `fromString()` · `value()` · `equals()` · `__toString()`.

**Not the fleet code.** `CV-01` is unique within the fleet and the workshop says it out loud, so it
is tempting. Apply Lesson 1.3's test: *a natural key is safe only when the business would call it a
different thing if the key changed.* Fleet codes get reused — CV-01 is sold, a new car arrives, and
it becomes CV-01. The workshop does not think of that as the same vehicle, but every historical job
card keyed on `CV-01` now points at a car that was never here.

This is the fifth of these classes. Lesson 2.1's quiz argued that four was within the range where
duplication beats the wrong abstraction. If you think five crosses the line and you extracted a
shared base instead, that is defensible — write it in `PROGRESS.md`.

---

## 2 — `CourtesyVehicle` — one addition, nothing removed

Add a `CourtesyVehicleId`, minted in the constructor, and `id(): CourtesyVehicleId`.

**The constructor signature does not change.** `new CourtesyVehicle('CV-01')` still works, and
**Lesson 1.1's checks must still pass** — the last acceptance criterion below is that you re-run
them. Same discipline as `LabourLine` in 2.1.

Do **not** change `allocateTo(string $jobCardReference)` to take a `JobCardId`. It would be better,
and it would break 1.1. Living with that is the point — see section 8 of the README.

---

## 3 — `CourtesyFleet` — the second aggregate root

`final class`, **not** `readonly`. No public constructor; `CourtesyFleet::create()` instead.

| Member | Behaviour |
|--------|-----------|
| `create(): self` | An empty fleet |
| `addVehicle(string $fleetCode): CourtesyVehicleId` | Creates the vehicle **inside** the aggregate and returns its identity |
| `size(): int` | |
| `has(CourtesyVehicleId): bool` | |
| `fleetCodeOf(CourtesyVehicleId): string` | `\DomainException` if not in this fleet |
| `isAvailable(CourtesyVehicleId): bool` | `\DomainException` if not in this fleet |
| `availableVehicles(): array` | `list<CourtesyVehicleId>` |
| `allocate(CourtesyVehicleId, JobCardId): void` | |
| `returnFromJobCard(JobCardId): void` | `\DomainException` if that job card has nothing out |
| `allocationFor(JobCardId): ?CourtesyVehicleId` | `null`, not an exception |
| `jobCardFor(CourtesyVehicleId): ?JobCardId` | `null` if available; `\DomainException` if not in this fleet |

### Nothing gets in, nothing gets out

`CourtesyVehicle` is a **mutable entity**. This is Lesson 2.1's leaky collection with I10 as the
casualty: a caller holding one can allocate it directly, behind the fleet's back.

So the boundary is sealed in both directions, and the checks test both:

- **Out:** no public method returns a `CourtesyVehicle`. Accessors return identities.
- **In:** `addVehicle()` takes a **fleet code**, not a vehicle — because a caller who passed an
  object in would keep a handle to it.

An aggregate root that creates its own members is a normal and underused pattern. One rule covers
both directions: *nothing inside the boundary should have a reference to it from outside.*

### The rules

**Membership**

- `addVehicle('')` → `\InvalidArgumentException` (the `CourtesyVehicle` constructor already does this)
- A fleet code already in the fleet → `\DomainException`. This is a genuine aggregate-scope
  invariant: only something holding all the vehicles can answer it, which is I10's argument at a
  smaller scale.

**Allocation** — two rules, enforced in two different places, and knowing which is which is the
lesson:

| Rule | Enforced by | Why there |
|------|-------------|-----------|
| A job card has at most one courtesy vehicle | **the fleet** | Answering it needs to see every vehicle |
| A vehicle is out on at most one job card (**I10**) | **the vehicle**, as in Lesson 1.1 | The check was always right; the fleet just guarantees there is one instance for it to run against |

Read that second row twice. **The fleet does not add a new check for I10.** Lesson 1.1's guard was
never wrong — it had nothing to run against when the same record was loaded twice. Ownership, not
cleverness, is the fix.

- Allocating a vehicle not in the fleet → `\DomainException`
- Check the job-card rule **first**. Told "CV-01 is already out" when the real problem is that this
  job card already has CV-02, the caller goes and finds another car and hits the same wall.

**Return**

- `returnFromJobCard()` finds whatever that job card has out and returns it
- Nothing out for that job card → `\DomainException`
- A returned vehicle can be allocated again

---

## Acceptance criteria

- [ ] Two new files in `domain/workshop/`, namespace `Workshop`
- [ ] `CourtesyFleet` is `final`, not `readonly`, with no public constructor
- [ ] No public method of `CourtesyFleet` returns a `CourtesyVehicle`
- [ ] `addVehicle()` takes a `string`, not a `CourtesyVehicle`
- [ ] A refused operation changes nothing
- [ ] No public property is writable from outside; no public `set*` methods
- [ ] No file imports anything outside `Workshop`
- [ ] **Lesson 1.1's starter still passes:**
      `php module-1-domain-modelling/lesson-1.1-rich-vs-anemic/challenge/starter.php`

---

## Hints

<details>
<summary>Hint 1 — how to store it</summary>

One array, keyed by the string form of the vehicle's identity:

```php
/** @var array<string, CourtesyVehicle> */
private array $vehicles = [];
```

Do not keep a second `array<vehicleId, jobCardId>` alongside it. Each `CourtesyVehicle` already
knows what it is out on, and a second copy is two facts that must agree — Lesson 1.1's hint, and the
bug from Lesson 2.1 example 01.

`allocationFor()` is then a loop comparing `$vehicle->allocatedToJobCard()`. That is O(n), and at
twelve vehicles it does not matter. If it ever does, you have outgrown this design — README §4.

</details>

<details>
<summary>Hint 2 — one private helper earns its keep</summary>

```php
private function vehicleOrFail(CourtesyVehicleId $id): CourtesyVehicle
{
    if (!isset($this->vehicles[$id->value()])) {
        throw new \DomainException(/* ... */);
    }
    return $this->vehicles[$id->value()];
}
```

Four public methods need it. Note the return type is fine — it is `private`, so it is not handing
anything outside the boundary.

</details>

<details>
<summary>Hint 3 — the string conversion</summary>

`allocateTo()` takes a string and `allocatedToJobCard()` returns one, so:

```php
$vehicle->allocateTo($jobCardId->value());
// and coming back:
return $reference === null ? null : JobCardId::fromString($reference);
```

Yes, it is ugly. Section 8 of the README is about why it is left that way.

</details>

---

## When it passes

Compare with `solution/`. Worth looking at:

- Whether they check the job-card rule before or after `vehicleOrFail()`, and whether it matters
- Whether their `addVehicle()` constructs the vehicle before or after the duplicate check
- How they worded the "not in this fleet" message

---

## Then, without running anything

**Q1.** `CourtesyFleet` holds every courtesy vehicle, so every allocation locks the whole fleet —
including cars nobody touched. That is the large-aggregate failure from Lesson 2.1 §2. Defend the
design anyway. Then say the number at which your defence stops working, and how you would find that
number out.

**Q2.** `new CourtesyVehicle('CV-01')` still works from anywhere, and produces a vehicle the fleet
knows nothing about. The boundary is a convention, not a compiler-enforced fact. Name two things
that would make it harder to violate by accident, and say what each costs.

**Q3.** The fleet does not know whether a job card is still *open*. A completed job card could hold
a courtesy vehicle forever. Where should the return be triggered from, and why can the fleet not
simply ask the job card?

**Q4.** Example 03 chose "first claim wins" when reconciling a collision. Name two other policies a
workshop might reasonably choose, and describe the phone call each one produces.

Q3 is Lesson 2.3, and it is the question that makes domain events necessary rather than decorative.
