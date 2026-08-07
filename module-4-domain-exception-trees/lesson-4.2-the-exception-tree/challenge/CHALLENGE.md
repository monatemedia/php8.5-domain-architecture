# Challenge — Lesson 4.2: The Exception Tree

> **You write:** eleven new classes in `domain/workshop/`
> **You edit:** `JobCardNotFound` · `JobCardAlreadyExists` · `JobCard` · `infrastructure/SqliteJobCards`
> **You run:** `php module-4-domain-exception-trees/lesson-4.2-the-exception-tree/challenge/starter.php`
> **Then run:** `php regression.php` — it is part of the acceptance criteria, not an afterthought
> **Done when both print:** `ACCEPTANCE: all checks passed` / all green

The largest challenge in the course by file count and the least difficult per file. Most of these
classes are under twenty lines. The work is in section 1 and section 5; the rest is typing.

---

## Read `checks.php` first.

---

## 1 — The four structural classes

Build these before any leaf. Every leaf depends on getting them right, and getting one wrong means
rewriting eleven files.

### `domain/workshop/CarriesContext.php`

```php
namespace Workshop;

interface CarriesContext extends WorkshopFailure
{
    public function failureCode(): string;

    /** @return array<string, string|int|float|bool|null|list<string|int|float|bool|null>> */
    public function context(): array;
}
```

**It extends `WorkshopFailure` and does not replace it.** 4.1's checks assert that interface is still
empty, and they run in the regression sweep. That constraint is the lesson, not an obstacle — see
README section 7.

### `domain/workshop/WorkshopException.php`

```php
abstract class WorkshopException extends \DomainException implements CarriesContext
{
    protected function __construct(string $message, array $context = []);

    public function context(): array;
}
```

**`abstract`** so it cannot be thrown. **`protected` constructor** so only the tree can build one.
**Do not implement `failureCode()`** — leaving it undefined keeps it abstract, and PHP then refuses
to load any leaf that forgets one. That refusal is the enforcement.

### `domain/workshop/InvalidWorkshopInput.php`

The same again, under `\InvalidArgumentException`. Yes, the context storage is duplicated. It cannot
be shared — see README section 4.

### `domain/workshop/JobCardException.php`

```php
abstract class JobCardException extends WorkshopException
{
    protected function __construct(
        public private(set) JobCardId $jobCardId,
        string $message,
        array $context = [],
    );
}
```

It must put `jobCardId` into the context for every subclass, and it must do it so a subclass cannot
overwrite it. `['jobCardId' => $id->value()] + $context` — with `+`, the **left** operand wins.

---

## 2 — Eight leaves under `JobCardException`

| Class | Named constructor | Context beyond `jobCardId` |
|-------|-------------------|----------------------------|
| `JobCardNotFound` | `withId(JobCardId)` | — |
| `JobCardAlreadyExists` | `withId(JobCardId)` | — |
| `IllegalStatusTransition` | `from(JobCardId, JobCardStatus $current, JobCardStatus $attempted)` | `from`, `to`, `allowed`, `terminal` |
| `JobCardIsTerminal` | `cannot(JobCardId, string $operation, JobCardStatus)` | `operation`, `status` |
| `LineNotFound` | `on(JobCardId, LineId)` | `lineId` |
| `LineCurrencyMismatch` | `between(JobCardId, Currency, string $description, Currency)` | `jobCardCurrency`, `lineCurrency`, `lineDescription` |
| `NothingAwaitingAuthorisation` | `on(JobCardId)` | — |
| `OutstandingAuthorisations` | `on(JobCardId, list<string> $descriptions)` | `count`, `descriptions` |

All `final`. Each declares its own `failureCode()`, kebab-case, matching the class in meaning but
not derived from its name.

`LineNotFound` also gets `public private(set) LineId $lineId` — declared on the leaf, not hoisted
into a node, because it is the only failure that has one.

---

## 3 — One leaf on the other branch

`MissingWorkDescription extends InvalidWorkshopInput`, with
`forJobCard(JobCardId): self`.

It sets `jobCardId` in its own context, because its branch has no node holding an identity. **Spell
the key exactly as `JobCardException` does.** A client must not have to know which branch a failure
came from to read its identity, and `job_card_id` on one branch and `jobCardId` on the other is a
bug nobody notices until a consumer does.

---

## 4 — Re-parent the two from 4.1

`JobCardNotFound` and `JobCardAlreadyExists` become `extends JobCardException`. Delete their
`public private(set) ?JobCardId $jobCardId = null` — the parent has it now, non-nullable.

The checks assert both that `$e->jobCardId` still reads the same and that it no longer allows null.

---

## 5 — Rewrite eleven throw sites

Seven in `JobCard`:

| Method | Was | Becomes |
|--------|-----|---------|
| `addLine()` | `\DomainException` (currency) | `LineCurrencyMismatch::between(...)` |
| `removeLine()` | `\DomainException` (no such line) | `LineNotFound::on(...)` |
| `discoverAdditionalWork()` | `\InvalidArgumentException` | `MissingWorkDescription::forJobCard(...)` |
| `authoriseAdditionalWork()` | `\DomainException` | `NothingAwaitingAuthorisation::on(...)` |
| `complete()` | `\DomainException` | `OutstandingAuthorisations::on(...)` |
| `transitionTo()` | `\DomainException` | `IllegalStatusTransition::from(...)` |
| `assertNotTerminal()` | `\DomainException` | `JobCardIsTerminal::cannot(...)` |

One in `infrastructure/SqliteJobCards::add()` — the duplicate guard 4.1 deliberately left broken.
And `InMemoryJobCards` already throws `JobCardAlreadyExists` from 4.1, so it needs nothing.

**Move the message into the exception.** The checks assert `JobCard` contains no `sprintf` at all
afterwards. An aggregate root holding eleven format strings was doing presentation, and that is what
this refactor is really removing — the naming is the visible half.

**Update the `@throws` tags.** Nothing enforces them, and they are the only documentation a caller
gets about which of eleven failures a method can produce.

---

## 6 — What NOT to build

**No `CourtesyVehicleException`.** The fleet is the second aggregate root and symmetry is tempting.
It fails both parts of the test in README section 3: with one subclass there is no field to hoist,
and nobody writes `catch (CourtesyVehicleException)` when catching the child is more precise.

**No status codes on the exceptions.** `public const HTTP_STATUS = 409` is the single most common
way a domain exception tree acquires the web. It is Lesson 4.4's job, in `infrastructure/`. A domain
exception that knows it is a 409 has an opinion about HTTP.

**No `previous`.** Domain failures are not caused by other exceptions; they are caused by somebody
trying to do something. Cause chains belong on `Infrastructure\StorageFailure`.

**No logging in a constructor.** Every test that asserts a refusal would become a test that writes
to a log.

---

## 7 — Left as your own work

`CourtesyFleet`, `CourtesyVehicle`, `TaxRate` and `CourtesyVehicleEligibility` still throw
anonymously — eleven more sites:

```
CourtesyFleet.php               4 x \DomainException
CourtesyVehicle.php             2 x \InvalidArgumentException, 2 x \DomainException
CourtesyVehicleEligibility.php  1 x \DomainException
TaxRate.php                     2 x \InvalidArgumentException
```

The checks do not require these, deliberately: with the tree built, they are mechanical, and the
second time through is where the design either holds or does not. **Do them, and note in
`PROGRESS.md` whether you needed a `CourtesyVehicleException` node by the end.** If you did, that is
the second class arriving and the node has now earned its place — which is exactly how section 6 is
supposed to be resolved.

---

## Acceptance criteria

- [ ] `CarriesContext extends WorkshopFailure`, declaring exactly two methods
- [ ] `WorkshopFailure` is **still empty** — the regression sweep checks this
- [ ] Three internal nodes, all `abstract`; nine leaves, all `final`
- [ ] Two roots, on `\DomainException` and `\InvalidArgumentException`, sharing no class ancestor
- [ ] Every leaf declares a unique kebab-case `failureCode()` containing no class name
- [ ] Every `context()` is JSON-safe and survives `json_encode`
- [ ] `IllegalStatusTransition` derives `allowed` from the enum, not from a hand-written list
- [ ] `JobCard` contains no `new \DomainException`, no `new \InvalidArgumentException`, no `sprintf`
- [ ] Every earlier assertion still passes: **`php regression.php` is green**

---

## Hints

<details>
<summary>Hint 1 — the shape of a leaf</summary>

```php
final class NothingAwaitingAuthorisation extends JobCardException
{
    public static function on(JobCardId $id): self
    {
        return new self($id, sprintf(
            'Job card %s has no additional work awaiting authorisation.',
            $id
        ));
    }

    public function failureCode(): string
    {
        return 'nothing-awaiting-authorisation';
    }
}
```

Eleven lines, and eight of the nine are this with more context. `new self(...)` reaches the
protected parent constructor because it is called from inside the hierarchy.

</details>

<details>
<summary>Hint 2 — deriving `allowed` from the enum</summary>

```php
$allowed = array_map(
    static fn(JobCardStatus $s): string => $s->value,
    $current->allowedNext()
);
```

`array_values()` it before it goes into the context. `allowedNext()` returns a list today, and the
checks compare with `===`, which is sensitive to keys.

Do not write the list out by hand. The checks compare your context against the enum's own answer, so
a copy that drifts fails — which is the point of asserting it.

</details>

<details>
<summary>Hint 3 — a leaf with its own typed property</summary>

`LineNotFound` needs `lineId` as a property as well as in the context, and the parent constructor
does not know about it:

```php
public private(set) LineId $lineId;

public static function on(JobCardId $jobCardId, LineId $lineId): self
{
    $exception = new self(
        $jobCardId,
        sprintf('Job card %s has no line %s.', $jobCardId, $lineId),
        ['lineId' => $lineId->value()]
    );

    $exception->lineId = $lineId;

    return $exception;
}
```

Assigning from a static method of the same class is allowed — `private(set)` is per-class, not
per-instance. Same pattern the old `JobCardNotFound` used before it had a parent to hold things.

</details>

<details>
<summary>Hint 4 — if regression fails on Lesson 2.3 or 3.2</summary>

Check what your new class extends. Every leaf must still be a `\DomainException` or a
`\InvalidArgumentException` by inheritance:

```
JobCardNotFound -> JobCardException -> WorkshopException -> \DomainException
```

Break that chain — by extending `\RuntimeException` "because it seems more correct", which it
arguably is — and forty assertions in Modules 1 to 3 fail at once. The failure is loud and the cause
is one word.

</details>

---

## When it passes

Compare with `solution/`. Worth looking at:

- The doc block on `CarriesContext` arguing why it is a second interface rather than two methods on
  `WorkshopFailure`
- `JobCardException`'s note on why the identity is both a typed property and a context key
- `OutstandingAuthorisations` on why `descriptions` stays a list
- `MissingWorkDescription`'s admission that it should probably have been a value object, and why it
  is not

---

## Then, without running anything

**Q1.** `IllegalStatusTransition` and `JobCardIsTerminal` both mean "the status forbids this."
Write the argument for merging them into one class, then the argument against. Which did the
reference implementation take, and does the reason survive contact with your own answer?

**Q2.** The tree has nine leaves and one of them — `OutstandingAuthorisations` — guards a rule the
state machine already enforces. Lesson 2.3 called it redundant and kept it for the message. Now that
it also has its own failure code, has the argument got stronger or weaker? Say which and why.

**Q3.** `failureCode()` is declared per class rather than derived from the class name. Write the
five-line derivation that would generate all nine automatically, then say precisely what you would
lose. (One of the two things is in a code review; the other is in a client's release notes.)

**Q4.** A colleague proposes adding `public function httpStatus(): int` to `CarriesContext`, arguing
that the domain knows better than the boundary whether a failure is a 404 or a 409. Give the
strongest version of their case — it is not a weak one — and then say what you would do instead and
why. (Lesson 4.4 takes a position. Form yours first.)
