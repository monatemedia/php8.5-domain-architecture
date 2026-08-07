# Lesson 3.4 — Testing Persistence
> **Module 3: Persistence Without Leakage** · Advanced Domain Architecture

| | |
|---|---|
| **Time** | ~25 min reading · ~15 min running the example. Challenge is one class. |
| **Comes after** | Lesson 3.3 — Querying Without Leaking |
| **Covers** | The contract test · Liskov, executable · What a fake hides · `:memory:` isolation · The gap a contract cannot close |
| **You write** | `infrastructure/SqliteJobCards.php` |
| **Needs** | PHP 8.5+, `composer install`, `pdo_sqlite` |

---

## 📁 Lesson folder

```
lesson-3.4-testing-persistence/
├── README.md
├── examples/
│   └── 01-what-a-fake-hides.php    ← four drifts, run
├── challenge/
│   ├── CHALLENGE.md
│   ├── checks.php                  ← the contract, written once, run twice
│   ├── starter.php
│   └── solution/
└── quiz/QUIZ.md
```

---

## 1 — Two implementations, one promise

You have `InMemoryJobCards`. This lesson adds `SqliteJobCards`, and the point is not the SQL — it is
that from now on there are **two** things claiming to be a `JobCards`, and something has to hold them
to the same promise.

That something is a **contract test**: a suite that belongs to the *interface* rather than to any
class, and that every implementation must satisfy.

> **This is the Liskov Substitution Principle, cashed in as something you can execute.**
>
> LSP says a subtype must be usable anywhere the supertype is, without the caller knowing which it
> has. That is an assertion about behaviour, and until you run the same assertions against both, it
> is a hope.

`checks.php` in this lesson is written once and run twice. If you find yourself special-casing one
implementation, that is the moment it stops being a contract test.

---

## 2 — What the contract may and may not assert

The hard part is not writing assertions. It is knowing which ones belong.

Lesson 3.1's checks included this:

```php
'add() then get() returns the SAME aggregate, not a copy'
```

True of an in-memory repository. **Impossible for a database**, which builds a new object from rows
every time. So it was never part of the interface's promise — it was a property of one mechanism,
and it is deliberately absent from this lesson's contract.

| Belongs in the contract | Does not |
|-------------------------|----------|
| `get()` returns an **equal** aggregate | it returns the **same instance** |
| Lines come back in insertion order | they come back from an array |
| A duplicate `add()` is refused | it is refused by a `UNIQUE` constraint |
| `get()` on a miss throws `JobCardNotFound` | it throws after a `SELECT` returns no rows |

**The rule:** assert what the interface promises, never how a class keeps the promise. An assertion
that only one implementation could possibly satisfy is a test of that implementation wearing a
contract test's clothes.

---

## 3 — What a fake hides

An in-memory repository satisfies several promises **by accident**, because an array happens to
behave that way. Example 01 runs four of them.

| Drift | In-memory | Real |
|-------|-----------|------|
| **Ordering** | free — an array has insertion order | `SELECT` with no `ORDER BY` is unordered |
| **Uniqueness** | a duplicate key silently replaces | a constraint refuses |
| **Types** | an int is an int | columns can arrive as strings |
| **Change tracking** | holds a reference; mutations are free | wrote rows at `add()` and knows nothing since |

The first is the one to internalise, because it is invisible: SQLite *usually* returns rows in
insertion order, so a contract test that did not assert ordering would pass against both — until an
index was added and somebody's invoice reordered itself.

> **A fake that is more permissive than the real thing gives you a green suite and a broken system,**
> which is worse than no suite, because the suite is why nobody looked.

That is why `InMemoryJobCards::add()` has a duplicate guard it does not strictly need, and why
`SqliteJobCards` stores a `position` column the domain knows nothing about.

---

## 4 — The `position` column

`JobCard::lines()` promises insertion order. An array gives that away free. A table does not.

So `SqliteJobCards` stores a position and orders by it — and **the domain knows nothing about it**.
"Third on the list" is not a business concept; it is a consequence of the aggregate's own promise,
and keeping that promise is the storage layer's problem.

The checks assert both halves: the column exists, and `JobCard` never mentions it.

---

## 5 — Isolation: `sqlite::memory:`

```php
$pdo = new PDO('sqlite::memory:');
SqliteJobCards::createSchema($pdo);
```

Every repository gets its own database, and it disappears when the connection closes.

No fixtures to clean up. No ordering dependency between tests. No chance of one test seeing
another's rows — which is the failure that makes an integration suite mysteriously fail only when
run in a different order, and which people usually fix by making the tests run in one order forever.

**The trade-off, stated honestly:** SQLite is not the database you deploy on. It has different type
affinity, different collation, different locking, and a much more forgiving idea of what a column
type means. A suite that is green here can still fail against PostgreSQL.

What it buys is that the mapping, the ordering, the constraints and the transactions are all
exercised for real, in milliseconds, with no setup. That is a large fraction of what can go wrong,
and the remainder is caught by running the *same contract* against a real database in CI — which
costs nothing extra, because the contract is already written.

> The contract test is what makes "run it against the real one too" a configuration change rather
> than a project.

---

## 6 — The gap a contract test cannot close

The honest end of Module 3.

`JobCards` is collection-oriented — `add()` and `remove()`, no `save()`. Lesson 3.1 chose that
deliberately, to keep storage out of the caller's vocabulary, and warned in the same breath that it
had a cost.

Here is the cost. Change an aggregate after `add()`-ing it:

- **In-memory** holds a reference. The repository already agrees.
- **SQLite** wrote rows at `add()` time and has no idea anything changed.

**The two implementations genuinely diverge, and no assertion written against the interface can hide
it.** That is not a bug in either class — it is the interface having no moment at which to write.

The honest options:

| Option | Cost |
|--------|------|
| **A unit of work** — track loaded aggregates, diff them at the end of the request, write what changed | Real machinery, and it must know how to compare aggregates |
| **An explicit `save()`** | One word of storage vocabulary back in the caller's hands |
| **Treat the aggregate as immutable per request** — load, change, `remove()` then `add()` | Ugly, and it works |

Most PHP codebases take the second, and it is a defensible answer rather than a defeat.

This lesson's contract is written **not to depend on the divergence**, and says so in a comment. What
it must never do is pretend the divergence is not there — a contract test that quietly avoids the
one place two implementations differ has stopped testing the interesting thing.

---

## 7 — Where the PHPUnit version fits

The contract here is expressed in the course's own harness so that `check.php` can judge it. In a
real project it would be PHPUnit, and the shape is the obvious one:

```php
abstract class JobCardsContractTest extends TestCase
{
    abstract protected function repository(): JobCards;

    public function test_get_returns_an_equal_aggregate(): void { /* ... */ }
    // ...every assertion in this lesson's checks.php
}

final class InMemoryJobCardsTest extends JobCardsContractTest
{
    protected function repository(): JobCards { return new InMemoryJobCards(); }
}

final class SqliteJobCardsTest extends JobCardsContractTest
{
    protected function repository(): JobCards { /* :memory: + schema */ }
}
```

One abstract class, one method per promise, one subclass per implementation. `run-tests.php` at the
course root will find and run them, one process per file.

Converting `checks.php` into that is a mechanical half-hour, and it is a genuinely good exercise if
you want it — but it teaches PHPUnit rather than domain architecture, which is why it is not the
challenge.

---

## 8 — Common mistakes

| Mistake | Why it is wrong | Instead |
|---------|----------------|---------|
| A test suite per implementation | They drift, and the fake is always the permissive one | One contract, run twice |
| Asserting `get()` returns the same instance | A database cannot | Assert equality |
| No ordering assertion | Both pass today; one reorders later | Assert it deliberately |
| A fake with no uniqueness guard | More permissive than the constraint | Guard it |
| A shared test database | Order-dependent failures, fixed by freezing the order | `:memory:`, one per test |
| SQL in the mapper | The part worth testing now needs a database | 3.2's split |
| A contract that avoids where they differ | It has stopped testing the interesting thing | Write around it, and say why |

---

## 9 — Where else this shows up

- **PSR interfaces** ship compliance suites — `psr/cache`, `psr/log` — for exactly this reason.
- **A filesystem abstraction** with local and S3 drivers: same contract, and the local one hides
  eventual consistency.
- **Any test double you wrote by hand.** If it has never been checked against the real thing, you do
  not know what it is more permissive about.

---

## ✅ Lesson checklist

- [ ] Read this README fully — especially sections 2 and 6
- [ ] Run and study `examples/01-what-a-fake-hides.php`
- [ ] Read `challenge/checks.php` — note that the contract is written once and run twice
- [ ] Complete the challenge
- [ ] `php regression.php` is green
- [ ] Compare against `challenge/solution/`, log disagreements in `PROGRESS.md`
- [ ] Take `quiz/QUIZ.md` closed-book
- [ ] Take **Gate 3** in `GATES.md`, with every file shut

---

*Module 3 ends here. `domain/workshop/` has a repository interface it declares and does not
implement; `infrastructure/` has two implementations, a mapper and a read model. Module 4 turns the
`\DomainException`s you have been throwing since Lesson 1.1 into a tree, and maps it onto HTTP.*
