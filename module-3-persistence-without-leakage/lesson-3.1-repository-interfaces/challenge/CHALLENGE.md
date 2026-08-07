# Challenge — Lesson 3.1: The `JobCards` Repository

> **You write:**
> `domain/workshop/JobCards.php` · `domain/workshop/JobCardNotFound.php` ·
> `infrastructure/InMemoryJobCards.php`
> **You run:** `php module-3-persistence-without-leakage/lesson-3.1-repository-interfaces/challenge/starter.php`
> **Done when it prints:** `ACCEPTANCE: all checks passed`

Three files in **two directories**, and which file goes where is the lesson. The behaviour is
straightforward; the placement is the exam.

---

## Read `checks.php` first.

---

## 0 — `infrastructure/` is new

This lesson creates it, because this is the first thing the course has written that unambiguously
belongs outside the domain. It has its own README — read it.

- Namespace `Infrastructure`, mapped to `infrastructure/`
- It is git-ignored exactly like `domain/workshop/`: your answers, not course material
- `domain/workshop/` may not import from it. Ever. The capstone proves it by scanning imports.

---

## 1 — `domain/workshop/JobCards.php` — the interface

```php
namespace Workshop;

interface JobCards { /* ... */ }
```

| Method | Behaviour |
|--------|-----------|
| `add(JobCard $jobCard): void` | Throws `\DomainException` if that identity is already present |
| `get(JobCardId $id): JobCard` | Throws `JobCardNotFound` |
| `contains(JobCardId $id): bool` | Answers; never throws |
| `remove(JobCardId $id): void` | Throws `JobCardNotFound` |
| `forVehicle(VehicleId $id): array` | `list<JobCard>` — a vehicle's service history |
| `awaitingAuthorisation(): array` | `list<JobCard>` — waiting on an account holder |
| `count(): int` | |

**Exactly those seven.** The checks assert the method list, because an eighth that leaks storage
vocabulary is the failure this lesson is about.

### It must import nothing

The checks assert `JobCards.php` contains **no `use` statement at all**. Every type it names —
`JobCard`, `JobCardId`, `VehicleId`, `JobCardNotFound` — is in its own namespace. A domain interface
that has to import something is worth a second look at what it is importing.

### On the Rule 1 check

It strips comments before looking. The doc block on `JobCards` explains the inversion — *"the domain
declares what it needs; Infrastructure supplies it"* — and that is describing the rule, not breaking
it. It is the **dependency** that is banned, not the word.

The first version of that check did not strip comments and failed its own reference solution on
exactly that sentence. It cost one `verify.php` run to find, which is what a verify run is for.

### Banned words

The checks reject any method whose name contains `findBy`, `query`, `connection`, `sql`,
`criteria`, `hydrate`, `persist` or `flush`. Read section 3 of the README for why one of those is
enough to make the boundary decorative.

### Read the names out loud

*"Job cards awaiting authorisation."* *"Job cards for this vehicle."* If a method name is not a
sentence the service manager would recognise, it is wrong — and that test is more reliable than the
banned-word list, which only catches the obvious cases.

---

## 2 — `domain/workshop/JobCardNotFound.php`

```php
final class JobCardNotFound extends \DomainException
{
    public static function withId(JobCardId $id): self;

    public private(set) ?JobCardId $jobCardId;
}
```

**`\DomainException`, not `RuntimeException`.** A missing job card is a fact about the workshop, not
a crash. The same exception is right whether the repository is SQLite, an array or a filing cabinet
— which is exactly the test for belonging in the domain.

**It carries the identity**, not only a message. A boundary rendering this as JSON needs the id, not
a sentence with the id inside it. Lesson 4.2 is about that properly; starting now costs nothing.

---

## 3 — `infrastructure/InMemoryJobCards.php`

```php
namespace Infrastructure;

use Workshop\JobCard;
// ... and several more, all pointing INTO the domain

final class InMemoryJobCards implements JobCards { /* ... */ }
```

An array, keyed by the string form of the identity.

**The `use` statements at the top are the point.** Several, all pointing into `Workshop`. The
interface has none. That asymmetry *is* the dependency inversion: **infrastructure knows about the
domain; the domain knows nothing.** The checks assert both directions.

### It is not a throwaway

Lesson 3.4 runs the same contract test against this and against SQLite, to prove they are
substitutable — Liskov, cashed in as something executable. A fake that drifts from the real thing
gives you a green suite and a broken production system, so write it as a real implementation.

### Two details the checks test

- `add()` twice with the same identity throws. A collection does not silently replace, and swapping
  in a stale copy would discard whatever was there.
- The query methods return **lists**. `array_filter` on an associative array keeps the original
  keys, so `$results[0]` then fails on a non-empty result set — a confusing way to find out.

---

## Acceptance criteria

- [ ] The interface is in `domain/workshop/` and imports nothing
- [ ] No domain file **references** `Infrastructure` in code (a doc block may mention it — see below)
- [ ] The implementation is **not** in `domain/workshop/`, and its namespace is `Infrastructure`
- [ ] The implementation imports from `Workshop`
- [ ] Every method takes or returns aggregates and identities — never strings, arrays of columns or DTOs
- [ ] No storage vocabulary in any method name
- [ ] `get()` and `remove()` throw `JobCardNotFound`; `contains()` answers
- [ ] `php regression.php` is green

---

## Hints

<details>
<summary>Hint 1 — throw expressions make get() one line</summary>

```php
public function get(JobCardId $id): JobCard
{
    return $this->jobCards[$id->value()] ?? throw JobCardNotFound::withId($id);
}
```

`throw` as an expression is PHP 8.0. It combines with `??` exactly where you want it.

</details>

<details>
<summary>Hint 2 — one private filter</summary>

Both query methods are the same shape:

```php
private function filter(callable $matches): array
{
    return array_values(array_filter($this->jobCards, $matches));
}
```

`array_values()` is not optional — see above.

</details>

<details>
<summary>Hint 3 — the exception carrying its identity</summary>

```php
public static function withId(JobCardId $id): self
{
    $exception = new self(sprintf('No job card with id %s.', $id));
    $exception->jobCardId = $id;

    return $exception;
}

public private(set) ?JobCardId $jobCardId = null;
```

`private(set)` lets the named constructor assign it while nothing outside can. Assigning from a
static method of the same class is allowed — PHP's visibility is per-class, not per-instance.

</details>

---

## When it passes

Compare with `solution/`. Worth looking at:

- Whether their `add()` guard message tells you what to do about it
- Whether they wrote one `filter()` helper or two loops, and which reads better
- The doc comment on `JobCards` about collection-oriented repositories — it predicts a problem you
  are about to have

---

## Then, without running anything

**Q1.** `InMemoryJobCards` stores references, so a job card you changed after `add()`-ing it is
already up to date and there is nothing to save. Write down, in one paragraph, what a SQLite
implementation would have to do instead — and what it would need that this interface does not give
it.

**Q2.** Somebody asks for "job cards over 500 that are still open, for fleet accounts only". You
cannot use `findBy()`. Name the method, or argue that it does not belong on this interface at all.

**Q3.** There is no `LinesRepository`. Explain why, in terms of Lesson 2.1, and say what it would
mean if somebody added one.

**Q4.** `get()` throws and `contains()` answers. Give a caller for each where the other would be
wrong.

Q1 is Lesson 3.2, and your answer to it is the lesson's opening argument. Write it before you read
that README.
