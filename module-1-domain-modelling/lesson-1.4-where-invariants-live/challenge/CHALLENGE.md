# Challenge — Lesson 1.4: LabourUnits and LabourLine

> **You write:** `domain/workshop/LabourUnits.php`, `domain/workshop/LabourLine.php`
> **You run:** `php module-1-domain-modelling/lesson-1.4-where-invariants-live/challenge/starter.php`
> **Done when it prints:** `ACCEPTANCE: all checks passed`

Uses `Money` and `Currency` from Lesson 1.2. Finish that first if you have not.

---

## Read `checks.php` first.

---

## Before you write anything

`examples/03-relocating-the-checks.php` shows a labour line with **nine checks in four places, two
of which disagree**. That is what you are replacing.

Take invariant **I3** apart before you start. Write your answers down — it takes two minutes and it
decides the whole design:

> Labour is billed in whole tenths of an hour, rounded up, with a minimum of five tenths per line.

| Part | Invariant, policy, or sanity check? | Whose? |
|------|-------------------------------------|--------|
| Whole tenths | ? | ? |
| Rounded up | ? | ? |
| Minimum five tenths | ? | ? |

The third row is genuinely arguable and the follow-up questions come back to it.

---

## 1 — `LabourUnits`

```php
final readonly class LabourUnits { /* ... */ }
```

Billable labour on **one line**, measured in tenths of an hour.

| Member | Behaviour |
|--------|-----------|
| `LabourUnits::fromMinutes(int $minutes): self` | Rounds **up** to the next tenth; applies the five-tenth minimum. Throws `\InvalidArgumentException` if negative. |
| `LabourUnits::ofTenths(int $tenths): self` | Rebuild from storage. Throws `\InvalidArgumentException` if below five. |
| `tenths(): int` | |
| `plus(LabourUnits $other): self` | |
| `equals(LabourUnits $other): bool` | |
| `__toString(): string` | `0.5 h`, `1.0 h`, `2.3 h` — always one decimal place |

Constructor not public.

### The rounding table — these exact values are checked

| Minutes | Tenths | Why |
|--------:|-------:|-----|
| 0 | 5 | the minimum still applies |
| 1 | 5 | rounds up to 1, then the minimum |
| 6 | 5 | exactly 1 tenth, then the minimum |
| 30 | 5 | exactly 5 tenths |
| 31 | 6 | rounds **up**, never down |
| 36 | 6 | exactly 6 tenths |
| 37 | 7 | rounds up |
| 60 | 10 | one hour |

**Do this without a float.** `ceil($minutes / 6)` introduces a float into billing arithmetic for no
reason. `intdiv($minutes + 5, 6)` is the same thing in integers — satisfy yourself that it is, with
a few values, before you trust it.

---

## 2 — `LabourLine`

```php
final readonly class LabourLine { /* ... */ }
```

| Member | Behaviour |
|--------|-----------|
| `LabourLine::create(string $description, LabourUnits $units, Money $ratePerTenth): self` | Trims the description; throws `\InvalidArgumentException` if it is then empty |
| `description(): string` | The **trimmed** description |
| `units(): LabourUnits` | |
| `ratePerTenth(): Money` | |
| `total(): Money` | **Derived**, never stored |

Constructor not public.

### `total()` is computed, not stored

This is the point of the whole challenge.

A stored total can disagree with the parts. A derived one cannot, so the invariant *"the total
matches the line"* needs no guard at all — it cannot be expressed falsely.

**The cheapest invariant is one that cannot be written down wrong.** Reach for that before reaching
for a check.

There is a cost, and it is real: deriving across a thousand lines on every read is not free. Lesson
2.1 revisits this exact trade-off and comes to the opposite conclusion for a different reason, which
is worth watching for.

### Why the rate is *per tenth*, not per hour

The workshop quotes an hourly rate. This class takes a rate per tenth, and the reason is that
`Money` has no `divide()` — deliberately, because dividing money raises a question about the
remainder that a value object cannot answer.

So the conversion from "90 an hour" to "9 per tenth" happens **once**, where the rate is configured,
rather than on every line. That is the same move as normalising a registration once at construction:
do the awkward thing at one boundary, and everything downstream is arithmetic that cannot go wrong.

It is also a trade-off with a downside — the stored rate is no longer the number the business says
out loud, so somebody has to know about the conversion. Whether that is worth it is a fair argument,
and Q2 below asks you to have it.

---

## Acceptance criteria

- [ ] Both files in `domain/workshop/`, namespace `Workshop`
- [ ] Both `final readonly class` with non-public constructors
- [ ] No `float` anywhere in either file
- [ ] `total()` is derived, not stored — no total property exists
- [ ] Every guard is at construction; nothing checks after the fact
- [ ] A zero rate is accepted (the workshop does goodwill work)
- [ ] Neither file imports anything outside `Workshop`

---

## Hints

<details>
<summary>Hint 1 — rounding up in integers</summary>

`intdiv($minutes + 5, 6)` equals `ceil($minutes / 6)` for any `$minutes >= 0`.

Check it: 0 → 0. 1 → 1. 6 → 1. 7 → 2. 12 → 2. 13 → 3.

Then apply the minimum with `max($tenths, 5)`.

</details>

<details>
<summary>Hint 2 — __toString without a float</summary>

```php
sprintf('%d.%d h', intdiv($this->tenths, 10), $this->tenths % 10)
```

Tenths are single digits by definition, so no padding is needed. 5 → `0.5 h`. 10 → `1.0 h`.
23 → `2.3 h`.

</details>

<details>
<summary>Hint 3 — two factories, and why ofTenths() is stricter than you expect</summary>

`ofTenths()` refuses anything below five, including values `fromMinutes()` would have produced by
rounding up. That is correct: five tenths is the minimum a *line* can bill, so a stored value of 3
is corrupt data, not a small line.

If that feels wrong to you, you have found the argument the follow-up questions are about.

</details>

---

## When it passes

Compare with `solution/`. Look at:

- Where they put the five-tenth minimum, and whether it is marked as arguable
- Whether `plus()` goes through `ofTenths()` or the private constructor, and what that changes
- What their exception messages tell a service adviser to actually do

---

## Then, without running anything

**Q1.** You have implemented the five-tenth minimum as an invariant of `LabourUnits`. Argue that it
is a policy. Then say what you would actually do, given that `DOMAIN.md` states it as an invariant.

**Q2.** `LabourLine` takes a rate *per tenth*, but the workshop quotes *per hour*. Someone has to
know about that conversion. Where should it live, and what is the failure mode if it is done in more
than one place?

**Q3.** `total()` is derived. Suppose a rate changes next year. What happens to every historical
line's total, and is that what the business wants? Name the change you would make if it is not —
and notice that the change turns a derived value back into a stored one.

Q3 is the door into Module 2, and it is the reason Lesson 2.1 comes to the opposite conclusion about
storing totals.
