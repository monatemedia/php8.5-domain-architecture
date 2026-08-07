# Challenge — Lesson 2.4: One Value Object, One Domain Service

> **You write:** `domain/workshop/TaxRate.php`, `domain/workshop/CourtesyVehicleEligibility.php`
> **You run:** `php module-2-aggregates-and-consistency/lesson-2.4-domain-services/challenge/starter.php`
> **Done when it prints:** `ACCEPTANCE: all checks passed`

The smallest challenge in Module 2. Two classes, and the work is deciding which kind each one is —
plus one thing you must **not** write, which the checks also test for.

---

## Read `checks.php` first.

---

## The brief: place three operations

| Operation | Where it goes | You write |
|-----------|---------------|-----------|
| Apply a tax rate to an amount | on the **value object** that owns the knowledge | `TaxRate::applyTo()` |
| Authorise additional work | on the **entity** whose state it changes | nothing — it is already on `JobCard` |
| Decide courtesy vehicle eligibility | a **domain service** | `CourtesyVehicleEligibility` |

**Two of the three are not services.** That ratio is roughly right for a real model, and getting it
wrong in the other direction — a service for everything — is Lesson 1.1's anemic model with more
folders.

---

## 1 — `TaxRate` (a value object)

`final readonly`, no public constructor.

| Member | Behaviour |
|--------|-----------|
| `ofBasisPoints(int): self` | Hundredths of a percent. 2000 is 20%. |
| `ofPercent(int): self` | Convenience — multiplies by 100 |
| `zero(): self` | |
| `basisPoints(): int` · `isZero(): bool` · `equals(TaxRate): bool` | |
| `applyTo(Money): Money` | The **tax due**, not the total |
| `addTo(Money): Money` | Amount **plus** tax |
| `__toString(): string` | `20.00%`, `12.50%`, `5.05%` |

**Basis points, not a percentage and never a float.** Real rates are not always whole percentages —
12.5%, 5.5%, 7.25% all exist — and the moment a rate is a float you have put a float into money
arithmetic through the back door. The checks assert no float appears anywhere in the file.

**Guards** — both `\InvalidArgumentException`:

- Negative → refused.
- Above 10000 basis points (100%) → refused. Rates above 100% do exist in the world; a rate above
  100% *arriving here* is overwhelmingly a units error, someone passing `20` meaning 20%. This is a
  **sanity check, not a business rule** — Lesson 1.4 §3, and knowing which it is before somebody
  meets it is the point.

**Rounding: half up, in integers.**

```php
intdiv($amountMinor * $basisPoints + 5000, 10000)
```

That is `round($x * $bp / 10000)` with nothing floating anywhere. Check it: 20% of 999 is 199.8,
which must come out as **200**.

> Rounding is itself a policy, and this class picks one. Some jurisdictions round tax down per line;
> some round the invoice total rather than each line. A production system takes the mode as an
> argument. This one does not — say so in a comment rather than pretending the question does not
> exist.

**Do not write a `TaxCalculator`.** The checks assert that `Workshop\TaxCalculator` and
`Workshop\TaxService` do **not** exist. A service taking a rate and an amount is the same arithmetic
with an extra class and an extra injection, and it is exactly what makes people think domain
services are ceremony.

---

## 2 — `CourtesyVehicleEligibility` (a domain service)

`final readonly`, no public constructor.

| Member | Behaviour |
|--------|-----------|
| `requiringSpendOf(Money): self` | The policy, **passed in** |
| `unconditional(Currency): self` | A zero minimum — the fleet contract |
| `minimumSpend(): Money` | |
| `reasonsAgainst(JobCard): array` | `list<string>`, empty when it qualifies |
| `isEligible(JobCard): bool` | Defined as `reasonsAgainst() === []` |

### The rules

A job card qualifies unless:

- its status **`isTerminal()`** — lending a car against an invoiced or cancelled job is not a thing
  the workshop does; or
- its **`total()` is below the minimum spend**.

`\DomainException` if the job card's currency differs from the minimum's. `Money` would refuse the
comparison anyway; catching it here names the job card instead of complaining about arithmetic.

### Why not `$jobCard->isEligibleForCourtesyVehicle()`

It would read beautifully. It is wrong because the minimum spend is **negotiated** — 0 for fleet
accounts, different last year, and Marcus will move it again. That is a policy (`DOMAIN.md`, *What
is not an invariant*), and a policy welded into an entity is a bug with a commencement date.

The checks assert that no method on `JobCard` contains "eligib".

### Reasons, not a boolean

`isEligible()` must be **defined in terms of** `reasonsAgainst()`, not the other way round, so the
two can never disagree.

"Not eligible" sends a service adviser to guess. *"Job card JC-1001 comes to EUR 22.50, below the
EUR 150.00 minimum"* lets them tell the account holder something true — and lets the workshop
manager say *"no, fleet accounts don't have a minimum"*, which is a missing rule found in a meeting
rather than in an incident. **They can correct a sentence; they cannot correct a boolean.**

### Stateless

One property: the threshold. The checks assert exactly one, typed `Money`. A service that
accumulated anything across calls would not be a domain service — Rule 5.

---

## Acceptance criteria

- [ ] Both files in `domain/workshop/`, namespace `Workshop`
- [ ] Both `final readonly`, no public constructors
- [ ] No float anywhere in `TaxRate`
- [ ] No `TaxCalculator` or `TaxService` class exists
- [ ] `CourtesyVehicleEligibility` has exactly one property, and it is the policy
- [ ] `isEligible()` and `reasonsAgainst()` take a **`JobCard`**, never a `JobCardId`
- [ ] `JobCard` has no eligibility method
- [ ] Neither file imports anything outside `Workshop`
- [ ] `php regression.php` is green

---

## Hints

<details>
<summary>Hint 1 — half-up rounding, and why the +5000</summary>

Integer division truncates. Adding half the divisor first turns truncation into round-half-up:

```
999 * 2000 = 1,998,000        + 5000 = 2,003,000   /10000 = 200   ✓  (199.8 -> 200)
1000 * 1250 = 1,250,000       + 5000 = 1,255,000   /10000 = 125   ✓  (125.0 -> 125)
```

Use `intdiv(self::BASIS_POINTS_PER_UNIT, 2)` rather than `/ 2` — PHP's `/` returns a float unless
the division is exact, and relying on that in money code is a habit worth not having.

</details>

<details>
<summary>Hint 2 — isEligible in terms of reasonsAgainst</summary>

```php
public function isEligible(JobCard $jobCard): bool
{
    return $this->reasonsAgainst($jobCard) === [];
}
```

One line, and it makes the two impossible to disagree. Writing them separately is two
implementations of one rule — Lesson 1.4 §5, in miniature.

</details>

<details>
<summary>Hint 3 — the service reads only the public API</summary>

`$jobCard->status()->isTerminal()` and `$jobCard->total()`. The service does not reach inside the
aggregate, and it could not: `total()` is derived and the lines are not exposed for modification.

That is the boundary from 2.1 doing its job in a place you did not have to think about.

</details>

---

## When it passes

Compare with `solution/`. Worth looking at:

- Whether they guarded the currency before or after building the reasons list
- Whether they documented the rounding choice, or quietly made one
- Their note on why eligibility is arguably a *policy object* rather than a service — the two are
  frequently the same thing wearing different names

---

## Then, without running anything

**Q1.** Argue that `CourtesyVehicleEligibility` is not a domain service at all, but a policy object.
Then say whether the distinction changes a single line of code, and what that tells you.

**Q2.** `TaxRate::applyTo()` picks half-up rounding. Name a jurisdiction rule that would break it,
and say what the signature would become if you supported both.

**Q3.** Somebody proposes `CourtesyVehicleEligibility::isEligible(JobCardId $id)`, loading the job
card inside. What does that change about which directory the class lives in, and what does it cost?

**Q4.** Module 2 is finished. `domain/workshop/` has twenty-two classes and no way to save any of
them. Name the single hardest thing about persisting `JobCard`, given everything you did to it in
2.1.

Q4 is Module 3, and Lesson 3.2 is the one that stops people in practice.
