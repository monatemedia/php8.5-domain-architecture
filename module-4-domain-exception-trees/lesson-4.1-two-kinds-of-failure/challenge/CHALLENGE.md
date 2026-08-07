# Challenge — Lesson 4.1: Two Kinds of Failure

> **You write:**
> `domain/workshop/WorkshopFailure.php` · `domain/workshop/JobCardAlreadyExists.php` ·
> `infrastructure/StorageFailure.php` · `infrastructure/GuardedJobCards.php`
> **You edit:** `domain/workshop/JobCardNotFound.php` · `infrastructure/InMemoryJobCards.php`
> **You run:** `php module-4-domain-exception-trees/lesson-4.1-two-kinds-of-failure/challenge/starter.php`
> **Done when it prints:** `ACCEPTANCE: all checks passed`

Four new files, two one-line edits, and one decorator that does nothing but decide which of two
things it is looking at.

---

## Read `checks.php` first.

---

## 1 — `domain/workshop/WorkshopFailure.php`

```php
namespace Workshop;

interface WorkshopFailure extends \Throwable
{
}
```

That is the entire file. It is checked for being **empty**: no methods, no constants. If you find
yourself wanting to add `public function context(): array`, you are three-quarters of the way to
Lesson 4.2 and slightly ahead of yourself — put it there instead.

`extends \Throwable` is not decoration. It is what lets a handler call `getMessage()` on what it
caught, and it is what makes PHP refuse, at compile time, to let a non-throwable class implement
this.

---

## 2 — Two edits, one word each

### `JobCardNotFound`

```php
final class JobCardNotFound extends \DomainException implements WorkshopFailure
```

That is the whole edit. Note that it stays a `\DomainException` — the checks assert both, because
Lesson 3.1's checks and Lesson 3.4's contract test are still running and both name that type.

### `InMemoryJobCards::add()`

Replace the bare `throw new \DomainException(...)` with `throw JobCardAlreadyExists::withId(...)`.

**Why:** wrap `InMemoryJobCards` in your decorator, add the same job card twice, and the old version
comes out as a `StorageFailure`. The database was fine. The rule that fired — *a collection does not
silently replace* — is as domain-level as they come, and it was thrown anonymously, so nothing could
tell it apart from a `\DomainException` raised inside a date library.

That is the general lesson and it is worth more than the class: **an anonymous throw is a failure
with no type, and no catch clause can single it out.**

---

## 3 — `domain/workshop/JobCardAlreadyExists.php`

Copy the shape of `JobCardNotFound` exactly:

```php
final class JobCardAlreadyExists extends \DomainException implements WorkshopFailure
{
    public static function withId(JobCardId $id): self;

    public private(set) ?JobCardId $jobCardId;
}
```

Keep the message text from the version you are replacing. The message is fine; it was never the
problem.

---

## 4 — `infrastructure/StorageFailure.php`

```php
namespace Infrastructure;

final class StorageFailure extends \RuntimeException
{
    public static function during(string $operation, \Throwable $cause): self;
}
```

Four things the checks assert, and each is a decision:

| Assertion | Why |
|-----------|-----|
| extends `\RuntimeException`, **not** `\DomainException` | A dead connection is not a business rule |
| **does not** implement `WorkshopFailure` | The one check whose failure means you have misunderstood, not mistyped |
| declared outside `domain/workshop/` | Marcus has no storage. He has a filing cabinet, and it does not refuse connections |
| the message does **not** contain the driver text | The evidence goes on `getPrevious()`, where a logger finds it and a JSON serialiser does not |

Message: `Storage failed during get.` and stop. Anything more either duplicates `previous` or invents
detail.

---

## 5 — `infrastructure/GuardedJobCards.php`

```php
final class GuardedJobCards implements JobCards
{
    public function __construct(private readonly JobCards $inner) {}
}
```

**The constructor parameter is typed to the interface.** The checks assert it. Typed to
`SqliteJobCards`, this is a try/catch with extra steps.

Seven methods, all delegating. The rule they share:

> **A `WorkshopFailure` passes through untouched. Any other `\Exception` becomes a `StorageFailure`.
> `\Error` is not caught at all.**

Three parts, and every one of them is asserted separately.

### "Untouched" means the same object

```php
throw $e;                                        // yes
throw new JobCardNotFound($e->getMessage());     // no
```

The checks compare with `===`. Rebuilding the exception reports *this* file in every stack trace,
flattens the subclass — a 409 arriving at the boundary as a 404 — and throws away the structured
context the exception is carrying. From 4.2 that context is most of the point of the class.

### `catch (\Exception)`, not `catch (\Throwable)`

This is the line to argue with. A `TypeError` from a mapper is a bug in *your* code, and dressing it
up as a storage failure sends whoever is on call to check a database that is fine.

The checks throw a `TypeError` and a `DivisionByZeroError` at all seven methods and assert both come
out unchanged.

### Order the clauses correctly

PHP takes the first match. `WorkshopFailure` first. Swap them and every domain failure — all of
which extend `\DomainException`, which extends `\Exception` — gets translated, and you have built a
machine for turning 409s into 500s.

---

## 6 — One thing this challenge deliberately leaves broken

`SqliteJobCards` from Lesson 3.4 also throws a bare `\DomainException` on a duplicate `add()`, and
this challenge does **not** ask you to fix it.

So this is still true when you finish:

```php
$jobCards = new GuardedJobCards(new SqliteJobCards($pdo, $mapper));
$jobCards->add($jobCard);
$jobCards->add($jobCard);   // -> StorageFailure. The database is fine.
```

That is left in place on purpose. `JobCard` alone has eleven more anonymous throw sites, and fixing
them one at a time as each decorator trips over them is how you end up with a flat list of
twenty-three unrelated exception classes and no way to catch a useful subset of them.

Lesson 4.2 does the whole sweep at once, with a hierarchy. Note the SQLite one in `PROGRESS.md` so
you can check it off there.

---

## Acceptance criteria

- [ ] `WorkshopFailure` is an empty interface extending `\Throwable`, in `domain/workshop/`
- [ ] `JobCardNotFound` and `JobCardAlreadyExists` implement it and remain `\DomainException`s
- [ ] `StorageFailure` is a `\RuntimeException` in `infrastructure/`, and is **not** a `WorkshopFailure`
- [ ] `StorageFailure` keeps the original on `getPrevious()` and out of `getMessage()`
- [ ] `GuardedJobCards implements JobCards`, takes a `JobCards`, adds no public method
- [ ] A `WorkshopFailure` comes out of all seven methods as the **same object**
- [ ] Any other `\Exception` comes out as a `StorageFailure` with `previous` intact
- [ ] An `\Error` comes out unchanged, from all seven
- [ ] The decorator still returns correct values over a real `InMemoryJobCards`
- [ ] `php regression.php` is green

---

## Hints

<details>
<summary>Hint 1 — one private helper instead of seven try/catch blocks</summary>

```php
private function guard(string $operation, \Closure $call): mixed
{
    try {
        return $call();
    } catch (WorkshopFailure $e) {
        throw $e;
    } catch (\Exception $e) {
        throw StorageFailure::during($operation, $e);
    }
}
```

Then each method is one line:

```php
public function get(JobCardId $id): JobCard
{
    return $this->guard('get', fn(): JobCard => $this->inner->get($id));
}
```

Keep it **private** — the checks assert the public surface is exactly the seven interface methods.

</details>

<details>
<summary>Hint 2 — the void methods</summary>

`add()` and `remove()` return nothing, and `guard()` wants a closure that returns something. Return
`null` explicitly:

```php
public function add(JobCard $jobCard): void
{
    $this->guard('add', function () use ($jobCard): null {
        $this->inner->add($jobCard);

        return null;
    });
}
```

`null` as a standalone return type is PHP 8.2. An arrow function will not do here — it has one
expression and you need a statement then a return.

Do not be tempted to make `guard()` return `void` for these; you would need two helpers, and the
duplicated try/catch is the thing you were avoiding.

</details>

<details>
<summary>Hint 3 — why the identity check might be failing</summary>

If `same object? NO` and you are certain you wrote `throw $e`, check the clause **order**. With
`catch (\Exception)` first, `WorkshopFailure` never matches — `JobCardNotFound` is an `\Exception`
too — and every domain failure is being translated. The symptom is that the pass-through checks and
the translation checks fail together, which looks like two bugs and is one.

</details>

---

## When it passes

Compare with `solution/`. Worth looking at:

- The doc block on `guard()`, which argues the `\Exception` vs `\Throwable` line properly
- Whether their `StorageFailure::during()` takes the SQL or the domain operation name, and why
- The note in `GuardedJobCards` on **decorator order** — guarding outside logging versus inside

---

## Then, without running anything

**Q1.** `GuardedJobCards` is one file per interface. You have `JobCards` today and you will have
more. Write down what a general `GuardedRepository` would need in PHP to guard any interface without
writing seven delegating methods each time — and then write down the two things you would lose. (One
of them is a type. The other is what an IDE can tell you.)

**Q2.** Section 6 of the README claims a repository that leaks `\PDOException` "has not finished
inverting the dependency." Argue the other side in a paragraph: what is the case for letting driver
exceptions through untouched, and in what kind of codebase does it win?

**Q3.** The decorator translates `\UnexpectedValueException` into a `StorageFailure`. But
`\UnexpectedValueException` is what `JobCardMapper` from Lesson 3.2 would reasonably throw on a
malformed row. Is a malformed row a storage failure, a programmer error, or a domain failure? Give
your answer, then check it against the retry test in README section 3 — and note whether the two
agree.

**Q4.** You now have a marker interface with two implementers and a decorator that depends on it.
Name the failure mode this creates as the domain grows, and what would catch it. (Hint: nothing
forces a new domain exception to implement `WorkshopFailure`. What happens when somebody forgets,
and how long before anyone finds out?)
