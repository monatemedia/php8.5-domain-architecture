# Module 3 — Persistence Without Leakage
> **Complete.** All four lessons are built and verified.

| Lesson | Subject | |
|--------|---------|---|
| [3.1](lesson-3.1-repository-interfaces/) | Repository interfaces belong to the domain; the SQL never does | ✅ built |
| [3.2](lesson-3.2-the-mapping-problem/) ⭐ | The mapping problem — reconstituting a rich aggregate from flat storage | ✅ built |
| [3.3](lesson-3.3-querying-without-leaking/) | Querying without leaking — read models, and a first honest look at CQRS | ✅ built |
| [3.4](lesson-3.4-testing-persistence/) | Testing persistence — the contract test, run against both implementations | ✅ built |

**First module that needs Composer.** Run `composer install` before starting. 3.4 also needs the
`pdo_sqlite` extension; `php verify.php` checks for it.

---

## The thread through this module

Two modules of work has produced a domain layer that **cannot be saved**. `JobCard` has a private
constructor, `readonly` lines, no setters, a `LineId` on every line that must survive a round trip,
and an `open()` that records an event. None of that maps onto a table, and all of it exists on
purpose.

> Everything you built in Modules 1 and 2 is now the obstacle. That is not a design failure — it is
> the bill arriving.

- **3.1** decides *where the interface lives*. The easy half.
- **3.2** is the hard half, and the one most DDD material skips: rebuilding a rich aggregate from
  flat rows without public setters, without re-running constructor guards, and without re-announcing
  events that happened months ago.
- **3.3** handles the queries that are not "load one aggregate" — reports, lists, filters — and why
  forcing them through the aggregate is wrong.
- **3.4** proves two implementations are substitutable with one test suite. Liskov, cashed in.

---

## What you build

| Lesson | New |
|--------|-----|
| 3.1 | `domain/workshop/JobCards.php`, `JobCardNotFound.php` · `infrastructure/InMemoryJobCards.php` |
| 3.2 | `reconstitute()` on `LabourLine`, `PartLine`, `JobCard` · `infrastructure/JobCardMapper.php` |
| 3.3 | `infrastructure/JobCardSummary.php`, `infrastructure/JobCardSummaryQuery.php` — **nothing in the domain** |
| 3.4 | `infrastructure/SqliteJobCards.php` — and the contract, written once and run twice |

**`infrastructure/` is created in 3.1** — the first directory in this course that is deliberately
not the domain. It is git-ignored like `domain/workshop/`, and it has its own README.

The rule it exists to make visible: `domain/workshop/` imports nothing from `infrastructure/`, ever.
Infrastructure knows about the domain; the domain knows nothing.
