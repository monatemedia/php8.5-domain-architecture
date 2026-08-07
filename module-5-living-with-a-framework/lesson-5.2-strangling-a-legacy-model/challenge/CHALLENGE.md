# Challenge — Lesson 5.2: Strangling a Legacy Model

> **You write:** `domain/workshop/PartStock.php` · `domain/workshop/InsufficientStock.php` ·
> `domain/workshop/InvalidQuantity.php` · `infrastructure/Legacy/StockReservations.php`
> **You edit:** `infrastructure/ProblemDetailsMapper.php` — two catalogue entries
> **You are given:** `challenge/given/Legacy/` — a row and three services. Do not edit them.
> **You run:** `php module-5-living-with-a-framework/lesson-5.2-strangling-a-legacy-model/challenge/starter.php`
> **Then run:** `php regression.php`

Extract exactly **one** aggregate. Two of the three services are still there when you finish, and
the checks assert it.

---

## Read `checks.php` first. Section 4 is the lesson.

---

## 0 — What you have inherited

```
PartStockRow          part_number, on_hand, reserved. No behaviour.
ReservationService    reserve(), release(). Half-enforces the rule.
PickingService        fit(). Enforces nothing.
StockReportService    available(), isLow(). Reads.
```

The invariant nobody wrote down:

```
0 <= reserved <= onHand
```

`ReservationService::reserve()` checks the upper bound. Nothing checks anything else — and two things
follow from that which you will find by running it, not by reading it.

---

## 1 — `domain/workshop/PartStock.php`

```php
final class PartStock
{
    private function __construct(
        private readonly PartNumber $partNumber,
        private int $onHand,
        private int $reserved,
    ) {}

    public static function of(PartNumber $partNumber, int $onHand, int $reserved): self;

    #[\NoDiscard('The point of asking is the answer')]
    public function isConsistent(): bool;

    public function partNumber(): PartNumber;
    public function onHand(): int;
    public function reserved(): int;
    public function available(): int;

    public function reserve(int $quantity): void;
    public function release(int $quantity): void;
    public function consume(int $quantity): void;
    public function receive(int $quantity): void;
}
```

**`of()` does not enforce the invariant.** It will build a stock with nine reserved and three on hand.

That looks wrong and it is the most important decision in the lesson. A factory that refused an
inconsistent row would be correct in principle and undeployable in practice — the first bad row in
production takes down the screen that was going to show you how many bad rows there are.

**It does refuse a negative count.** Inconsistent means two fields disagree; impossible means one
field is nonsense. A row saying minus four are on the shelf is a bug to be found, not dirt to be
tolerated.

`isConsistent()` is a **report**, not a guard. Nothing calls it before doing anything. See section 3.

**`consume()` moves both fields together.** That is the argument for this being an aggregate rather
than two integers: there is no moment at which the parts have been fitted but are still reserved.

---

## 2 — Two new leaves of Module 4's tree

| Class | Extends | Code | Status |
|-------|---------|------|--------|
| `InsufficientStock` | `WorkshopException` | `insufficient-stock` | 409 |
| `InvalidQuantity` | `InvalidWorkshopInput` | `invalid-quantity` | 422 |

The split follows 4.2's test — *could the caller have known without asking the domain?*

- Whether six alternators are enough depends on the shelf. Only the stock knows. → state, 409.
- Minus five is negative in the controller, in the form, and in the caller's own variable. → input,
  422.

**No `StockException` node.** One class below it means nothing to hoist and no clause anybody writes.
4.2's rule holds. When `PickingService` is strangled and brings a second stock failure, the node earns
its place then.

`InsufficientStock` carries `partNumber`, `requested`, `available` and `short` in its context —
enough for a client to say *"we can order 1 more"* without parsing a sentence.

Then add both to `ProblemDetailsMapper`'s catalogue. **Lesson 4.4's exhaustiveness check is what will
tell you if you forget** — which is the whole reason it exists.

---

## 3 — `infrastructure/Legacy/StockReservations.php`

The strangler. Two public methods with **identical signatures** to the ones they replace:

```php
public function reserve(PartStockRow $row, int $quantity): void;
public function release(PartStockRow $row, int $quantity): void;
```

The checks compare them by reflection. A call site must change by one word — the type it asks the
container for — and nothing else.

This is the property most often given up first, and giving it up is what kills the extraction. The
moment the new method needs different arguments, every call site changes in the same pull request,
and a two-day change becomes a six-week branch.

**So the aggregate takes an `int`**, even though a `Quantity` value object would make the invalid case
unrepresentable and be strictly better. Improve the signature later, when the call sites are yours.

Plus one more, for section 5 of the README:

```php
#[\NoDiscard('The point of asking is the answer')]
public function isConsistent(PartStockRow $row): bool;
```

Two private methods do the mapping — `toAggregate()` and `apply()`. That is an **anti-corruption
layer**, which is a grand name for Lesson 3.2's mapper pointed at a table somebody else designed.

**It does not save.** The caller owns the transaction, exactly as it did before this class existed.

---

## 4 — The characterisation test

`checks.php` runs both paths over the same inputs. Seven agree; three do not.

| Input | Legacy | Yours |
|-------|--------|-------|
| reserve 1, 6, 7 of 6 free | as expected | must match exactly |
| release 1 and 4 of 4 held | as expected | must match exactly |
| **reserve −5** | reserved goes to −1 | refused |
| **reserve 0** | succeeds, does nothing | refused |
| **release 9 of 4 held** | reserved goes to −5 | refused |

The three divergences are asserted **explicitly, in both directions** — the legacy outcome is pinned
down as well as yours. *"We found some bugs"* is not a decision; *"we changed behaviour in these three
cases"* is, and it is what goes in the pull request.

The reference implementation fixes all three, because nobody depends on being able to reserve minus
five. **The method of deciding is the transferable part**, and the questions are in README section 6.

---

## 5 — Stop at one

`PickingService` is worse than anything you have fixed — `fit()` drives `on_hand` to minus three
without comment. It is still there when you finish, and the checks assert both that it still works
**and that it can still break the invariant.**

That looks like an unfinished job. It is the discipline. A strangler that grows to three services in
its first week is a rewrite that has not admitted it yet.

`StockReportService` is left alone for a different reason: it is a read model wearing a service's
name, and Lesson 3.3 settled those. It decides nothing and every method is a subtraction.

The checks assert neither service mentions `PartStock`, and that no second aggregate appeared.

---

## Acceptance criteria

- [ ] `PartStock` is in `domain/workshop/`, has no `save()`, no id, no parent class
- [ ] `of()` loads an inconsistent row; `isConsistent()` reports it; a negative count is refused
- [ ] Reserve beyond available, release beyond reserved, consume beyond reserved: all refused
- [ ] `consume()` moves both counts together
- [ ] Non-positive quantities refused on every operation
- [ ] Two new leaves on the right branches, with kebab-case codes and JSON-safe context
- [ ] No `StockException` node
- [ ] Both mapped in `ProblemDetailsMapper` — 409 and 422, neither a 500
- [ ] `StockReservations` matches `ReservationService` signature for signature
- [ ] Old and new agree on all seven correct inputs, and differ on all three wrong ones
- [ ] `PickingService` and `StockReportService` untouched and still working
- [ ] `php regression.php` is green

---

## Hints

<details>
<summary>Hint 1 — the shape of a guard</summary>

```php
public function reserve(int $quantity): void
{
    $this->assertPositive($quantity);

    if ($quantity > $this->available()) {
        throw InsufficientStock::forPart($this->partNumber, $quantity, $this->available());
    }

    $this->reserved += $quantity;
}
```

Quantity check first, then the domain rule. Both `release()` and `consume()` are the same shape with
`$this->reserved` in place of `$this->available()`.

</details>

<details>
<summary>Hint 2 — the adapter</summary>

```php
private function toAggregate(PartStockRow $row): PartStock
{
    return PartStock::of(
        PartNumber::fromString((string) $row->part_number),
        (int) $row->on_hand,
        (int) $row->reserved
    );
}

private function apply(PartStock $stock, PartStockRow $row): void
{
    $row->on_hand  = $stock->onHand();
    $row->reserved = $stock->reserved();
}
```

Then each public method is three lines: load, operate, write back. No `save()`.

</details>

<details>
<summary>Hint 3 — if the characterisation test reports a mismatch</summary>

It names the case and prints both outcomes. The likely causes:

- **`of()` is enforcing the invariant.** Then `release 4 of 4 held` on a row that was already
  inconsistent throws where the legacy one succeeded. Loading is not a change.
- **`available()` is clamped at zero.** On a dirty row it must be allowed to be negative, or
  `reserve(1)` succeeds where it should refuse.

</details>

<details>
<summary>Hint 4 — if the mapper checks fail</summary>

You added the leaves and not the catalogue entries. Both go in `ProblemDetailsMapper::CATALOGUE`,
keyed by the failure code. This is the exact case Lesson 4.4's exhaustiveness check was written for,
and it is now catching it in a different module — which is the check working, a year early.

</details>

---

## When it passes

Compare with `solution/`. Worth looking at:

- `PartStock::of()`'s doc block on loading dirty data, and why `isConsistent()` is a report
- `StockReservations`' note on identical signatures being the property you must not trade
- `InvalidQuantity`'s admission that it should have been a value object, and why it is not yet
- `PickingService`'s doc block on why it is deliberately still broken

---

## Then, without running anything

**Q1.** For each of the three divergences, write the investigation you would actually run before
deciding. Name the tool — grep, the logs, a query, a conversation — and say what answer would change
your mind.

**Q2.** The reference implementation fixes all three in one commit. Describe how you would ship the
same work across three commits, and what you would gain if one of them caused an incident.

**Q3.** `isConsistent()` returns 40,000 bad rows. Write down what you would do next, in order, and
what you would say to the person who owns the parts department.

**Q4.** `PartStock` takes an `int` quantity because the legacy call sites pass one. Write down the
change you would make once the call sites are yours, and name what it would buy that
`InvalidQuantity` does not.

**Q5.** You have extracted one aggregate. Somebody asks how long until the whole application is done
this way. Answer honestly — including whether "done" is the right target.
