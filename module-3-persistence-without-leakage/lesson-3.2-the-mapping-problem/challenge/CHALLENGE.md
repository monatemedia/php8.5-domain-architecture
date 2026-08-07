# Challenge — Lesson 3.2: Reconstitution and the Mapper

> **You write:** `reconstitute()` on `LabourLine`, `PartLine` and `JobCard` ·
> `infrastructure/JobCardMapper.php`
> **You run:** `php module-3-persistence-without-leakage/lesson-3.2-the-mapping-problem/challenge/starter.php`
> **Done when it prints:** `ACCEPTANCE: all checks passed`

The longest challenge in the course. Every defence Modules 1 and 2 built is now something you have
to work around without breaking.

---

## Read `checks.php` first. The round-trip section is the real specification.

---

## 1 — Three `reconstitute()` methods

All three are **additive**. No existing signature moves, `create()` and `open()` are untouched, and
`php regression.php` must stay green — you are editing three classes that four earlier lessons
specified.

### `LabourLine::reconstitute(LineId, string $description, LabourUnits, Money): self`
### `PartLine::reconstitute(LineId, PartNumber, string $description, int $quantity, Money): self`

Take the `LineId` rather than minting one. That is the whole point: `create()` mints, `reconstitute()`
accepts, and they are different operations that happen to produce the same type.

**Skip the guards.** No description check, no quantity check. This row already passed them when the
line was created, and re-running them would mean the model trusts its own database less than its
callers — so the day a rule tightens, every historical row becomes unloadable.

### `JobCard::reconstitute(JobCardId, VehicleId, AccountHolderId, Currency, JobCardStatus, array $lines, array $pendingAuthorisations): self`

Four lines of body, each doing something the public API forbids.

| It must | Because |
|---------|---------|
| Build through the **private constructor** | `open()` is for opening |
| **Record no events** | Loading is not opening — 2.3 |
| Set the status **directly** | I9 governs *changes*; loading is not a change |
| Install the lines **with their existing identities** | Otherwise `removeLine()` breaks across requests |
| Restore `pendingAuthorisations` | It is state, and it must survive |

**Recording nothing comes free** — the constructor does not record, only `open()` does. That was
decided in 2.3 for a reason stated at the time, and this is where it pays off. If you find yourself
needing a flag, go and look at where the event is recorded.

**The status one is the sharpest.** A job card stored as `Invoiced` did not transition to `Invoiced`
now; it was invoiced last Tuesday. Replaying the lifecycle to get there is absurd, impossible for
`Cancelled`, and re-fires every event on the way. The checks assert that a reconstituted `Invoiced`
job card is still terminal — the rules apply *from now on*, the past just is not replayed.

---

## 2 — `infrastructure/JobCardMapper.php`

`final readonly class`, namespace `Infrastructure`.

| Method | |
|--------|--|
| `toRow(JobCard): array` | the job card's own row |
| `toLineRows(JobCard): array` | `list<array>`, one per line, in order |
| `fromRows(array $row, array $lineRows): JobCard` | |

### The row shapes

```
job card   id, vehicle_id, account_holder_id, currency, status,
           pending_authorisations   (JSON)

line       job_card_id, line_id, kind, description,
           tenths, rate_minor                        (labour; null on parts)
           part_number, quantity, unit_price_minor   (parts; null on labour)
```

`currency` and `status` store the **enum's backing value** — `'EUR'`, `'in_progress'` — and come back
through `Currency::from()` and `JobCardStatus::from()`.

### No PDO, no SQL

The checks assert the file contains neither. Mapping is the part worth testing exhaustively — every
line type, every status, every empty case — and tests that need a database get run less often than
tests that do not. The repository fetches rows; the mapper turns rows into aggregates. 3.4 supplies
the SQL.

### The `kind` discriminator, and the `instanceof`

The domain has no notion of "kind" — Lesson 2.1 rejected `isLabour()` on the `Line` interface because
`JobCard` does not care. Storage does care, so both the column and the `instanceof` that produces it
live **here**.

> A type check in a mapper is serialisation. The same check in a domain method is logic that belongs
> on the object.

### Fail loudly, both ways

- `toLineRows()` on a `Line` implementation it does not recognise → throw. A silently dropped line
  is a wrong total on an invoice.
- `fromRows()` with an unknown `kind` → throw. The schema has moved and this class has not.

The checks test both. Returning `null` or skipping the row is the failure being tested for.

### Value objects go through their normal factories

`Money::of()` and `LabourUnits::ofTenths()`, guards and all. Those are cheap, total, and true of
every value that has ever existed.

> **Reconstitution relaxes the rules of the aggregate, not the rules of everything it holds.**

---

## Acceptance criteria

- [ ] Three `reconstitute()` methods; `create()` and `open()` unchanged
- [ ] `JobCard::reconstitute()` records nothing, and `open()` still does
- [ ] A reconstituted `Invoiced` job card is still terminal
- [ ] The mapper is in `infrastructure/`, namespace `Infrastructure`
- [ ] No `PDO`, `SELECT`, `INSERT` or `sqlite` anywhere in the mapper
- [ ] No domain file references `Infrastructure` in code
- [ ] Round trip preserves identity, line ids and order, classes, total, status and pending list
- [ ] Unknown line class and unknown `kind` both throw
- [ ] **`php regression.php` is green**

---

## Hints

<details>
<summary>Hint 1 — JobCard::reconstitute(), in full</summary>

```php
$jobCard = new self($id, $vehicleId, $accountHolderId, $currency);

$jobCard->status = $status;

foreach ($lines as $line) {
    $jobCard->lines[$line->id()->value()] = $line;
}

$jobCard->pendingAuthorisations = array_values($pendingAuthorisations);

return $jobCard;
```

Writing private properties from a static method of the same class is allowed — PHP's visibility is
per-class, not per-instance. Note there is no `recordThat()` and nothing suppressing one.

</details>

<details>
<summary>Hint 2 — the line rows, and array union</summary>

```php
$base = ['job_card_id' => $jobCardId, 'line_id' => $line->id()->value(), 'description' => $line->description()];

if ($line instanceof LabourLine) {
    return $base + ['kind' => 'labour', 'tenths' => ..., 'part_number' => null, ...];
}
```

`+` on arrays keeps the left-hand keys and adds the rest. Every row must have the same key set, with
nulls where a column does not apply — otherwise a real database will refuse the insert in 3.4.

</details>

<details>
<summary>Hint 3 — fromRows, and match on the discriminator</summary>

```php
return match ($row['kind']) {
    'labour' => LabourLine::reconstitute(...),
    'part'   => PartLine::reconstitute(...),
    default  => throw new \RuntimeException(...),
};
```

`match` is strict, and its `default` arm makes the "schema moved" case impossible to forget — which
a `switch` with no `default` does not.

</details>

<details>
<summary>Hint 4 — the currency is on the job card, not the line</summary>

Line rows store `rate_minor` and `unit_price_minor` as plain integers. The currency comes from the
job card's row, and every line gets the same one — which is invariant enforcement falling out of the
storage design, because `addLine()` refuses a mismatched currency anyway.

Read the job card's currency first, then pass it down to each line.

</details>

---

## When it passes

Compare with `solution/`. Worth looking at:

- The doc block on `JobCard::reconstitute()` — it lists what each of its four lines violates
- Whether they threw or returned null for an unknown line class, and what the message says
- The note on JSON-in-a-column, and the rule of thumb for when that stops being acceptable

---

## Then, without running anything

**Q1.** `reconstitute()` is public and anything can call it. Write the code review comment you would
leave on a pull request that called it from a controller.

**Q2.** Somebody proposes storing the job card's **total** in its row, "so reports do not have to
load the lines". Argue against using Lesson 2.1 §6 — then say the one circumstance in which they
would be right.

**Q3.** `pendingAuthorisations` is JSON in a column. Name the request that would force it into its
own table, and say why that request changes the answer.

**Q4.** The mapper knows the private-ish shape of two line classes. If a third line type is added —
say a sublet charge from an outside specialist — list every file that has to change. Is that number
acceptable?

Q4 is the honest cost of hand-rolled mapping, and your answer to it is the argument for and against
an ORM.
