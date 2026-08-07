# Lesson 3.1 — Repository Interfaces Belong to the Domain
> **Module 3: Persistence Without Leakage** · Advanced Domain Architecture

| | |
|---|---|
| **Time** | ~30 min reading · ~25 min running the examples. Challenge on top. |
| **Comes after** | Module 2, and Gate 2 |
| **Covers** | The inversion · Domain vocabulary in method names · Aggregates, not rows · Collection-oriented vs persistence-oriented · Not-found · The first infrastructure directory |
| **You write** | `domain/workshop/JobCards.php`, `JobCardNotFound.php` — and `infrastructure/InMemoryJobCards.php` |
| **Needs** | PHP 8.5+, and `composer install` |

---

## 📁 Lesson folder

```
lesson-3.1-repository-interfaces/
├── README.md
├── examples/
│   ├── 01-the-inversion.php          ← which way the arrow points, and why
│   ├── 02-vocabulary.php             ← the same repository, twice, in two languages
│   └── 03-rows-or-aggregates.php     ← what leaks when it returns data
├── challenge/
│   ├── CHALLENGE.md
│   ├── checks.php
│   ├── starter.php
│   └── solution/
└── quiz/QUIZ.md
```

---

## 1 — Everything you have built is now the problem

Two modules of work has produced a domain layer that cannot be saved.

`JobCard` has a private constructor, `readonly` lines, no setters, a `LineId` on every line that
must survive a round trip, and an `open()` that records an event. None of that maps onto a table,
and all of it exists on purpose.

Module 3 is where you pay for it. This lesson is the easy half — deciding *where the interface
lives*. Lesson 3.2 is the hard half, and it is the one most DDD material skips.

---

## 2 — The inversion

The usual arrangement, and the one every framework tutorial teaches:

```
   Domain  ──────depends on─────►  Repository  ──────►  Database
```

Your business objects call a repository, which the framework supplies. Reasonable, and it means the
domain depends on a class that cannot exist without a database.

The inversion:

```
   Domain  ────declares────►  JobCards (interface)
                                    ▲
                                    │ implements
                                    │
   Infrastructure  ─────────  InMemoryJobCards, SqliteJobCards
```

**The interface lives in `domain/workshop/`. Every implementation lives outside it.**

The domain declares what it needs, in its own words. Infrastructure supplies it. The arrow points
*inward*, and `domain/workshop/` imports nothing from `infrastructure/` — which the capstone proves
by scanning imports.

This is the Dependency Inversion Principle from the previous course, finally with something at
stake. There it was an exercise; here it is the difference between a domain layer you can test in
milliseconds and one that needs a database to construct a `JobCard`.

> **`infrastructure/` is new in this lesson**, because this is the first thing the course has
> written that unambiguously belongs outside the domain. It has its own README.

---

## 3 — The interface is domain vocabulary

Read these out loud:

```php
$jobCards->awaitingAuthorisation();
$jobCards->forVehicle($vehicleId);
```

*"Job cards awaiting authorisation."* *"Job cards for this vehicle."* Those are sentences the
service manager would recognise, and that is the test.

Now read the alternative out loud:

```php
$jobCards->findBy(['status' => 'awaiting_authorisation']);
$jobCards->findWhereVehicleIdEquals($id);
$jobCards->query()->where('status', '=', 3)->get();
```

| What leaks | Why it matters |
|-----------|----------------|
| `findBy(array $criteria)` | A query language wearing a method name. Every caller now writes queries, so the storage model is in the caller's head. |
| `findWhereStatusEquals()` | Names a column. Rename the column and the domain's vocabulary changes. |
| `query(): QueryBuilder` | Hands the database to the caller. The boundary is now decorative. |
| `getConnection()` | No comment. |

**One of these is enough.** The moment a caller can express a query, the interface has stopped being
a domain concept and become a thin wrapper over the database — and nobody will ever put it back.

The discipline is uncomfortable and that is the point: *every query the domain needs must be a named
method somebody can read aloud.* If you cannot name it in the business's words, you may not have
understood what is being asked for.

---

## 4 — It deals in aggregates, not rows

```php
✅  public function get(JobCardId $id): JobCard;
❌  public function get(string $id): array;
❌  public function get(int $id): ?stdClass;
```

A repository that returns rows has not inverted anything. It is a data access object with a better
name, and it pushes the work of turning rows into objects out to every caller — which means every
caller does it slightly differently, and one of them forgets an invariant.

**The repository is the last place that knows about rows.** Everything above it speaks in
aggregates. That single rule is most of what "without leakage" means in this module's title.

---

## 5 — Collection-oriented, and the problem it causes

Two shapes, and the difference is not cosmetic.

```php
// COLLECTION-oriented — pretends to be a set of job cards that outlives the request
$jobCards->add($jobCard);
$jobCards->remove($id);

// PERSISTENCE-oriented — admits there is a database
$jobCards->save($jobCard);
$jobCards->delete($jobCard);
```

The first keeps storage out of the caller's vocabulary entirely: you do not "save" something into a
collection, you put it in. This challenge uses that shape.

**And it has a cost you should meet now rather than in 3.2.** An in-memory implementation stores
*references*, so changing an aggregate you already added is visible immediately and there is nothing
to write. A SQLite implementation cannot do that — it has no way to know you changed anything, and
the interface gives it nowhere to find out.

The classical answer is a **unit of work**: something tracking which aggregates were loaded,
comparing them at the end of the request, writing what changed. That is real machinery. Most PHP
codebases decide it is not worth it and add an explicit `save()` — which is honest, and which quietly
moves one word of storage vocabulary back into the caller.

> This course takes the collection shape here and **confronts the consequence in 3.2** rather than
> designing around it in advance. Watch for the moment it bites; noticing it yourself is worth more
> than being warned twice.

---

## 6 — Not found: exception or null?

`get()` promises a `JobCard`. Returning `null` instead means every caller writes the same guard, and
the day one forgets, the null travels — Lesson 1.1's second caller, in a new costume.

But "not found" is genuinely not always exceptional. Looking up a job card by a reference somebody
typed into a search box, a miss is the *ordinary* case, and an exception for the ordinary case is
control flow with a stack trace attached.

So the interface has **both shapes, meaning different things**:

| Method | Means | On a miss |
|--------|-------|-----------|
| `get($id)` | "I know this exists" | throws `JobCardNotFound` |
| `contains($id)` | "does this exist?" | returns `false` |

That is not indecision. A caller invoicing a job card named in a URL generated from a list *knows*
it exists — a miss there is a bug or a race, and an exception is right. A caller checking a typed-in
reference does not, and should ask.

Lesson 4.3 reopens this properly as exceptions versus result types.

---

## 7 — What the exception is, and is not

`JobCardNotFound extends \DomainException` — not `RuntimeException`, and emphatically not
`PDOException`.

A missing job card is a fact about the **workshop**, not about a database. The same exception is
right whether the repository is SQLite, an array, or a filing cabinet, and that is exactly the test
for whether something belongs in `domain/workshop/`.

It also carries the identity, not just a message:

```php
$exception->jobCardId;   // a JobCardId, not a substring
```

A boundary rendering this as JSON needs the id, not a sentence with the id inside it. Lesson 4.2 is
about carrying structured context rather than a formatted string, and starting now costs nothing.

---

## 8 — Common mistakes

| Mistake | Why it is wrong | Instead |
|---------|----------------|---------|
| The interface in `infrastructure/` | The arrow now points outward; the domain depends on a package it should not know exists | Interface in the domain |
| `findBy(array $criteria)` | A query language leaking through a name | Named methods in the business's words |
| Returning arrays or DTOs | A DAO with a better name; every caller re-implements hydration | Aggregates |
| `getConnection()`, `query()` | The database, handed to the caller | Nothing of the sort |
| Returning `null` from `get()` | Every caller guards; one forgets | Throw, and offer `contains()` |
| `JobCardNotFound extends RuntimeException` | Says "crash" when it means "business fact" | `DomainException` |
| One repository per entity | Lines and statuses are not separately loadable | **One per aggregate root** |
| Treating the in-memory one as a throwaway | It drifts from the real one, and the tests go green anyway | It is a real implementation — 3.4 proves it |

> **One repository per aggregate root** is worth its own line. There is no `LinesRepository`,
> because a line has no independent existence — it is reached through the job card, changed through
> the job card, and saved with the job card. If you find yourself wanting a repository for something
> inside a boundary, the boundary is wrong or the repository is.

---

## 9 — Where else this shows up

The inversion is the same move as:

- **Ports and adapters / hexagonal architecture** — this *is* a port, and the implementations are
  adapters. Different vocabulary, identical idea.
- **An interface in your code for a vendor's SDK** — the anticorruption layer from Lesson 0.2.
- **`iterable` instead of `array`** in a function signature: declare what you need, not what you
  will be given.

If you have ever written an interface so you could swap a mail driver in a test, you have done this.
The difference here is that the interface is written in the *business's* words rather than the mail
library's.

---

## ✅ Lesson checklist

- [ ] `composer install` has been run
- [ ] Read this README fully — especially sections 3 and 5
- [ ] Read `infrastructure/README.md`
- [ ] Run and study `examples/01-the-inversion.php`
- [ ] Run and study `examples/02-vocabulary.php`
- [ ] Run and study `examples/03-rows-or-aggregates.php`
- [ ] Read `challenge/checks.php`
- [ ] Complete the challenge — three files, in two directories
- [ ] `php regression.php` is green
- [ ] Compare against `challenge/solution/`, log disagreements in `PROGRESS.md`
- [ ] Take `quiz/QUIZ.md` closed-book

---

*Next lesson: **3.2 — The Mapping Problem**. The lesson most DDD material skips, and the one that
stops people in practice. Everything you built in Modules 1 and 2 is about to be the obstacle.*
