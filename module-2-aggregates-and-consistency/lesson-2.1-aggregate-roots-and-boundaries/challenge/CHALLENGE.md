# Challenge — Lesson 2.1: The JobCard Aggregate

> **You write:** eight new files in `domain/workshop/`, plus one addition to `LabourLine.php`
> **You run:** `php module-2-aggregates-and-consistency/lesson-2.1-aggregate-roots-and-boundaries/challenge/starter.php`
> **Done when it prints:** `ACCEPTANCE: all checks passed`

The biggest challenge in the course so far. Budget an evening, and do it in the order below —
each piece is small, and `JobCard` at the end is only assembling things that already work.

---

## Read `checks.php` first. It is long, and it is the specification.

---

## Order of work

```
1. LineId            identity for a line
2. Line              the interface both line types satisfy
3. LabourLine        ← ONE addition to the file you wrote in 1.4
4. PartNumber        value object
5. PartLine          the other kind of line
6. JobCardStatus     the transition table
7. JobCardId, AccountHolderId
8. JobCard           the aggregate root
```

---

## 1 — `LineId`

Same shape as `VehicleId` from 1.3. `final readonly`, no public constructor.

`generate(): self` · `fromString(string): self` (throws `\InvalidArgumentException` if empty) ·
`value(): string` · `equals(LineId): bool` · `__toString(): string`

---

## 2 — `Line` (an interface)

```php
interface Line
{
    public function id(): LineId;
    public function description(): string;
    public function total(): Money;
}
```

That is the entire contract, and its narrowness is the point. `JobCard` does not care whether a
line is labour or parts — it needs to identify it, describe it on an invoice, and add it up.
Anything more on this interface would be `JobCard` reaching into things that are none of its
business.

---

## 3 — `LabourLine` — one addition, nothing removed

Make it `implements Line` and add `id(): LineId`.

**`create()` keeps its exact 1.4 signature.** It mints a `LineId` internally.

That constraint is the interesting part of this lesson, so do not work around it:

- Every existing caller keeps working.
- **Lesson 1.4's checks must still pass afterwards.** The final acceptance criterion below is that
  you re-run them.
- A concept crosses from value object to entity by *gaining identity*, without a single breaking
  change. That is what the value/entity distinction looks like in motion, and it is more useful
  than any definition of it.

`LabourLine` stays `final readonly`. It is now an entity with immutable attributes — which sounds
contradictory and is not. Identity is what makes it an entity; immutability is a separate choice,
and here it is what makes the collection safe to hand out (see example 02).

---

## 4 — `PartNumber`

`final readonly`, no public constructor. `fromString(string $raw): self` ·
`value(): string` · `equals(PartNumber): bool` · `__toString(): string`

**Normalise:** trim, remove all spaces, uppercase. **Keep hyphens** — they are part of a part
number, unlike in a registration.

**Then validate.** All failures throw `\InvalidArgumentException`:

- 3 to 24 characters
- only `A`–`Z`, `0`–`9` and `-`
- must start and end with a letter or digit, never a hyphen

| Input | Result |
|-------|--------|
| `'k9k-1234'` | `K9K-1234` |
| `' K9K 1234 '` | `K9K1234` |
| `'AB'` | refused — too short |
| `'-K9K'` / `'K9K-'` | refused — hyphen at an end |
| `'K9K_1234'` | refused — illegal character |

---

## 5 — `PartLine`

`final readonly`, `implements Line`, no public constructor.

`PartLine::create(PartNumber $partNumber, string $description, int $quantity, Money $unitPrice): self`

- Trims the description; throws `\InvalidArgumentException` if it is then empty
- Throws `\InvalidArgumentException` if `$quantity < 1`
- Mints a `LineId`

`id()` · `partNumber()` · `description()` · `quantity()` · `unitPrice()` · `total()`

`total()` is `unitPrice` × `quantity`, **derived**. A zero unit price is allowed — parts are
sometimes supplied under warranty.

---

## 6 — `JobCardStatus`

A backed enum with exactly these seven cases and values:

```
Booked                'booked'
InProgress            'in_progress'
AwaitingAuthorisation 'awaiting_authorisation'
AwaitingParts         'awaiting_parts'
Completed             'completed'
Invoiced              'invoiced'
Cancelled             'cancelled'
```

| Method | Behaviour |
|--------|-----------|
| `allowedNext(): array` | `list<self>` — the transition table from `DOMAIN.md`, transcribed exactly |
| `canTransitionTo(self $next): bool` | |
| `isTerminal(): bool` | true when `allowedNext()` is empty |
| `label(): string` | `'Awaiting Authorisation'` — the value, underscores to spaces, title case |

The table, from `DOMAIN.md`:

| From | Allowed next |
|------|--------------|
| Booked | InProgress, Cancelled |
| InProgress | AwaitingAuthorisation, AwaitingParts, Completed, Cancelled |
| AwaitingAuthorisation | InProgress |
| AwaitingParts | InProgress |
| Completed | Invoiced |
| Invoiced | — |
| Cancelled | — |

**No state may transition to itself.** Completing an already-completed job card is a duplicate
submission, and the model should say so rather than silently succeeding.

**`Completed → Cancelled` is illegal**, and it is the one people get wrong. Work has been done; you
undo that with a credit note, which is a different operation with different accounting consequences.

---

## 7 — `JobCardId` and `AccountHolderId`

Same shape as `VehicleId`. Yes, that is a third and fourth near-identical class — see Q2 below,
which asks you to argue about it.

---

## 8 — `JobCard` — the aggregate root

`final class`, **not** `readonly`.

```php
JobCard::open(
    JobCardId $id,
    VehicleId $vehicleId,
    AccountHolderId $accountHolderId,
    Currency $currency,
): self
```

Opens in `Booked` with no lines. Note what it takes: **identities**, not objects. There is no
`Vehicle` and no `AccountHolder` inside this aggregate, and section 2 of the README is about why.

| Member | Behaviour |
|--------|-----------|
| `id()` · `vehicleId()` · `accountHolderId()` · `currency()` · `status()` | |
| `lines(): array` | `list<Line>` in insertion order |
| `lineCount(): int` | |
| `total(): Money` | **I5.** Derived. `Money::zero($currency)` when there are no lines. |
| `addLine(Line $line): void` | **I6** |
| `removeLine(LineId $lineId): void` | **I6** |
| `startWork()` · `awaitParts()` · `partsArrived()` · `complete()` · `invoice()` · `cancel()` | **I9** |
| `discoverAdditionalWork(string $description): void` | |
| `authoriseAdditionalWork(): void` | |
| `pendingAuthorisations(): array` | `list<string>` |

### The invariants, precisely

**I5 — the total always equals the sum of the lines.** Derive it. Never store it.

**I6 — no line may be added to or removed from a job card in a terminal state.** Both operations
throw `\DomainException` when the status `isTerminal()`. `removeLine()` with an unknown `LineId`
also throws `\DomainException`.

**Currency.** `addLine()` throws `\DomainException` if the line's total is in a different currency
from the job card. `Money` would refuse the addition anyway; catching it here gives a message that
names the job card rather than one about arithmetic.

**I8 — a job card cannot be completed while additional work awaits authorisation.** `complete()`
throws `\DomainException` if `pendingAuthorisations()` is non-empty.

> **This guard is redundant, and you should notice that.** `discoverAdditionalWork()` moves the
> status to `AwaitingAuthorisation`, and the transition table has no path from there to `Completed`.
> So the state machine already refuses it.
>
> Keep the check anyway, and be able to say why: it is Lesson 1.4 §5's *"one rule, two
> presentations"* case. Deleting it makes nothing newly possible — it changes the error message
> from "Awaiting Authorisation cannot become Completed" to "there is outstanding work nobody has
> approved", which is the sentence a service adviser can act on.
>
> Q3 below asks you to argue for deleting it. There is a real case.

**I9 — only the transitions in the table are legal.** Every lifecycle method attempts one
transition and lets `JobCardStatus` decide. Do not write per-method status conditions — that is
example 03 part 1, and it is where the bug was.

### `discoverAdditionalWork()` and `authoriseAdditionalWork()`

- `discoverAdditionalWork(string $description)` — trims; `\InvalidArgumentException` if empty.
  Transitions to `AwaitingAuthorisation`, then appends to the pending list.
  **Order is observable here.** Called from `Booked` the transition is illegal, and the description
  must not be recorded — a job card in `Booked` with a pending item has no legal path to clear it.
- `authoriseAdditionalWork()` — throws `\DomainException` if nothing is pending, then transitions to
  `InProgress`, then clears the list.
  That first guard is about the job card's **contents**, not its status, which is why it belongs on
  the aggregate rather than in the transition table. Without it the method is callable from
  `AwaitingParts` — a legal transition to `InProgress` — and would report work as approved that
  nobody ever proposed.

> **A limitation you should notice.** `AwaitingAuthorisation` cannot transition to itself, so only
> one item can be pending at a time and a second discovery is refused. Real workshops find two
> things at once. The strict version is kept here because the alternative is a status condition
> inside a lifecycle method, which is the habit this lesson exists to break. Worth disagreeing
> with — put it in `PROGRESS.md` if you would do it differently.

---

## Acceptance criteria

- [ ] Eight new files in `domain/workshop/`, namespace `Workshop`
- [ ] `JobCard` is `final` and **not** `readonly`; everything else new is `final readonly`
- [ ] `JobCard` holds `VehicleId` and `AccountHolderId` — no `Vehicle`, no `AccountHolder`
- [ ] No stored total anywhere
- [ ] `lines()` cannot be used to change the aggregate
- [ ] No public property is writable from outside; no public `set*` methods
- [ ] Transition rules exist in exactly one place
- [ ] No file imports anything outside `Workshop`
- [ ] **Lesson 1.4's starter still passes.** Run it:
      `php module-1-domain-modelling/lesson-1.4-where-invariants-live/challenge/starter.php`

That last one is not a formality. If it fails, you changed `LabourLine` in a way that broke an
earlier lesson, and a course where finishing one lesson un-finishes another is a course nobody
trusts.

---

## Hints

<details>
<summary>Hint 1 — one transition method</summary>

```php
private function transitionTo(JobCardStatus $next): void
{
    if (!$this->status->canTransitionTo($next)) {
        throw new \DomainException(/* name both states, and what IS legal from here */);
    }
    $this->status = $next;
}
```

Then `complete()` is a guard plus `$this->transitionTo(JobCardStatus::Completed);`. If you find
yourself writing `if ($this->status === ...)` inside a lifecycle method, stop — that rule belongs in
the table.

</details>

<details>
<summary>Hint 2 — the order in the two additional-work methods</summary>

`discoverAdditionalWork()` must **not** append if the transition is going to be refused — otherwise
a job card in `Booked` collects a pending item it can never clear. So: validate, transition, then
append. Or transition first and let it throw.

`authoriseAdditionalWork()` must **not** clear the list if the transition is refused, or the
authorisation is silently lost. So: transition, then clear.

Both are the same rule from Lesson 1.4 §6 — *validate first, mutate last* — and following it
mechanically gets both right without thinking about them separately.

</details>

<details>
<summary>Hint 3 — removeLine and identity</summary>

Store the lines however you like; `lines()` must return a `list<Line>` in insertion order. Keying
by `(string) $lineId` internally makes `removeLine()` trivial, but then `lines()` needs
`array_values()` to be a list again.

</details>

<details>
<summary>Hint 4 — LabourLine, without breaking 1.4</summary>

```php
final readonly class LabourLine implements Line
{
    private function __construct(
        private LineId      $id,
        private string      $description,
        private LabourUnits $units,
        private Money       $ratePerTenth,
    ) {}

    public static function create(string $description, LabourUnits $units, Money $ratePerTenth): self
    {
        // ... existing guards, unchanged ...
        return new self(LineId::generate(), $description, $units, $ratePerTenth);
    }

    public function id(): LineId { return $this->id; }
}
```

The public signature is identical. Everything 1.4 asserted is still true.

</details>

---

## When it passes

Compare with `solution/`. Worth looking at specifically:

- How they store the lines, and whether `lines()` gives you a list
- Whether their `addLine()` currency check comes before or after the terminal check, and whether
  the order is observable
- The wording of the transition failure — does it tell you what *is* legal from here?

---

## Then, without running anything

**Q1.** `JobCard` holds a `VehicleId`, so `$jobCard->vehicle->registration()` is gone. Somebody
still has to print the registration on the invoice. Where does that happen, and what has the
boundary actually bought in exchange for the inconvenience?

**Q2.** You now have four near-identical identity classes: `VehicleId`, `JobCardId`,
`AccountHolderId`, `LineId`. Make the case that this is unacceptable duplication and should be a
shared base class or trait. Then make the case against. Which do you believe?

**Q3.** Argue for deleting the redundant I8 guard in `complete()`. Give the strongest version, then
say what you would actually do.

**Q4.** `DOMAIN.md` invariant I10 — a courtesy vehicle is on at most one open job card at any time.
You have just built the aggregate. Can `JobCard` enforce I10? Say precisely what it would need to
see, and why giving it that would be a mistake.

Q4 is Lesson 2.2 and it is the question the whole module turns on.
