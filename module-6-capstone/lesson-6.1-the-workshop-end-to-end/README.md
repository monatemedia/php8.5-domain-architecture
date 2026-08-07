# Capstone — The Workshop, End to End

> **Module 6 · the last lesson**
> Prerequisites: Modules 0–5. This lesson writes no new domain rules. It assembles what is already
> there and proves one thing about the result.

---

## 1. What is left to do

Nothing, and that is the point.

Five modules built a domain model, aggregates with a lifecycle, repositories, a mapper, an exception
tree and a boundary translator. The capstone puts an HTTP API in front of all of it and adds **no
business rules at all** — four use cases, a controller, a middleware, a container, and one class that
reads files.

If assembling it required changing the domain, the domain was wrong. That is the real test, and it
is the one you can only run at the end.

---

## 2. The result

```
87 files scanned in the domain layer.
Every name they refer to, in full:

    DomainException
    InvalidArgumentException
    NoDiscard
    Throwable
```

That is the whole external vocabulary of the domain. Not "no framework imports" — **no imports.**

The scan was written to answer a weaker question: does `domain/` name anything infrastructural? It
found something sharper, and it found it by two checks failing. Domain classes all live in
`Workshop\`, so they refer to each other **unqualified** — there is not one `use` statement anywhere
in the layer. The only names that survive the tokeniser are the SPL exception classes the tree
extends and the attribute on a wither.

Delete Slim, delete PDO, delete PHP-DI, delete the web server. There is nothing left to remove.

**That is the course's thesis, and it is now a build failure rather than a claim.**

---

## 3. The scan

```php
$domain = ImportScan::of('domain/workshop');

if (!$domain->isClean()) {
    throw new RuntimeException($domain->report());
}
```

### Why a scan and not a convention

*"The domain must not import infrastructure"* is a rule with no enforcement, and rules with no
enforcement decay in a predictable way. Not in a big violation somebody would notice — in one
`use Infrastructure\Clock;` added at 5pm because the alternative was an interface and a container
binding.

Nobody reviews that line. It is one line. Six months later the domain does not compile without the
framework, and nobody can say which change did it.

**A build failure is the only form of architectural rule that survives contact with a deadline.**

### It reads tokens, not text

`token_get_all()`, not a regular expression, and this project has the scars to justify it. From
`RUN-THIS-FIRST.md`:

- **Bug 3** — a check that could not tell a dependency from a doc block explaining the dependency
  rule. It failed its own reference solution on the sentence *"Infrastructure supplies it."*
- **Bug 7** — a check that could not tell `PartStock` from `PartStockRow`.

The rule those two produced:

> **A check that reads source is a parser with the parsing left out.** If it is going to make a
> decision, give it enough context to make it correctly, or one day it will fail something that is
> right.

The tokeniser has already done that work. It knows a doc block from a `use` statement without being
told, and it knows `PartStockRow` is one identifier.

### The scanner is tested in both directions

Four probes, each a throwaway file differing from the next by one line:

| Probe | Must be |
|-------|---------|
| `use Slim\App;` and a `\PDO` type hint | **caught** |
| `use Infrastructure\SqliteJobCards;` | **caught, and named in the report** |
| a doc block mentioning `\PDO`, `Slim\App` and `Infrastructure\SqliteJobCards` | **clean** |
| `use Slim\App as Harmless;` | **caught** — an alias hides nothing |
| a closure's `use ($slim)` | **clean** — not an import at all |

A detector that never fires passes forever. A detector that always fires gets deleted on a Friday.

### Where its limits are

It does not catch a class named as a string: `$container->get('Slim\App')`. Nothing short of running
the code finds that, and a domain class building class names from strings has a larger problem than
this scan.

**Worth knowing where the edge is.** A check whose limits you cannot state is a check you are
trusting rather than using.

---

## 4. Use cases: the layer the course did not need until now

```php
final readonly class OpenJobCard
{
    public function __construct(private JobCards $jobCards) {}

    public function __invoke(string $vehicleId, string $accountHolderId, string $currency): JobCardId
    {
        $id = JobCardId::generate();

        $this->jobCards->add(JobCard::open(
            $id,
            VehicleId::fromString($vehicleId),
            AccountHolderId::fromString($accountHolderId),
            Currency::from($currency),
        ));

        return $id;
    }
}
```

Three things: turn primitives into domain types, call the domain, put the result where it goes.

**There is not one `if` in any of the four use cases, and the checks assert it.** Every refusal comes
from a domain type — `JobCardId::fromString()` rejects an empty id, `JobCards::add()` rejects a
duplicate — and the use case simply lets it through.

The moment an `if` appears in one, ask what it is deciding. If it is a business rule it belongs on an
aggregate, and the reason it ended up here is almost always that the aggregate was inconvenient to
change.

### The honest compromise

A use case is properly an **application** concern, not a domain one, and a three-layer project would
give it `Application\` of its own. This course has two namespace roots, and adding a third for four
classes would be ceremony — Rule 6.

So they share the domain's root, and **submit to the domain's scan**, which is a harder standard than
they would face in `Application\`. Nothing is being smuggled past.

---

## 5. The composition root

One file knows what everything really is:

```php
JobCards::class => static fn(ContainerInterface $c): JobCards => new GuardedJobCards(
    new SqliteJobCards(
        $c->get(\PDO::class),
        $c->get(JobCardMapper::class),
    )
),
```

Read that as a summary of Modules 3 and 4.1:

```
JobCards            the interface the DOMAIN declared           (3.1)
  GuardedJobCards   translates driver failures, passes ours     (4.1)
    SqliteJobCards  the real implementation                     (3.4)
      PDO           the driver
      JobCardMapper aggregate <-> rows                          (3.2)
```

**The decorator order is a decision.** `GuardedJobCards` is outside, so a `PDOException` becomes a
`StorageFailure` before it reaches the application — and a `JobCardNotFound` passes through as the
same object, so the boundary maps it to 404 rather than 500. Put the guard inside and a driver
exception escapes untranslated. One line, two very different applications.

The use cases and the controller are **not** defined in the container. PHP-DI reads their constructor
type hints and builds them, which is only possible because those constructors ask for **types**. A
class reaching for a global, a static or a service locator could not be wired this way.

---

## 6. The middleware, and what Module 4 bought

```php
try {
    return $handler->handle($request);
} catch (HttpException $routing) {
    return $this->respond(ProblemDetails::of('about:blank', $routing->getTitle(), $routing->getCode(), null, $path));
} catch (\Throwable $failure) {
    $problem = $this->mapper->map($failure, $path);

    if ($problem->status >= 500) {
        $this->unexpected->record($failure);
    }

    return $this->respond($problem);
}
```

**That is the entire error-handling strategy of the application.** No `match` on exception classes, no
table of status codes, no `instanceof` chain — because every one of those decisions was made earlier
and somewhere better:

| Decision | Made in |
|----------|---------|
| which failures are safe to show | 4.1's `WorkshopFailure` marker |
| what to call this failure | 4.2's `failureCode()` |
| what fields to include | 4.2's `context()` |
| which status code | 4.4's catalogue |
| what a 500 may say | 4.4's `opaque()` |

**This is the test of whether the previous four modules were worth it.** If this class needed a
hundred lines of mapping, the exceptions were not carrying their weight.

### The `catch (\Throwable)` that Lesson 4.1 forbade

4.1 was emphatic: widening a catch to `\Throwable` is the most expensive two-character change in the
module, because category (c) — programmer error — should be **loud**.

The outermost middleware of a web application catches `\Throwable` anyway. It has to; the alternative
is a stack trace served to a customer.

Both are right, and what reconciles them is **who gets told**:

```
the user      an opaque 500. No message, no class, no trace.
the operator  everything, with the exception intact.
```

"Loud" never meant loud to the person who typed the wrong registration number. It meant somebody
whose job it is finds out, with enough detail to fix it. The line that matters is the one deciding
*which* failures get reported: anything the mapper turned into a 5xx. **A 4xx is the system working,
and reporting those is how an error tracker becomes noise nobody reads.**

The checks assert both halves: a dead database is recorded once; a 409 and a 404 are recorded not at
all.

---

## 7. What it does over HTTP

| | | |
|---|---|---|
| `POST /job-cards` | 201 | with a `Location` |
| `GET /job-cards/{id}` | 200 | from SQLite |
| `POST /job-cards/{id}/work` | 204 | |
| `POST /job-cards/{id}/completion` | 204 | |
| `POST /job-cards/{id}/invoice` | 204 | |

Nouns, not verbs. `/completion` is a thing that can be created; `POST /complete` reads as RPC over
HTTP and tells a client nothing about what it can retry.

An illegal transition:

```
HTTP/1.1 409 Conflict
Content-Type: application/problem+json

{
  "type": "https://workshop.example/problems/illegal-status-transition",
  "title": "Illegal status transition",
  "status": 409,
  "detail": "Job card JC-... is Booked and cannot become Invoiced. Legal from here: In Progress, Cancelled.",
  "instance": "/job-cards/JC-.../invoice",
  "jobCardId": "JC-...",
  "from": "booked",
  "to": "invoiced",
  "allowed": ["in_progress", "cancelled"],
  "kind": "state"
}
```

Every field in that body was decided in a different module, and the middleware assembled none of it.

### What the capstone leaves out, and why

**The read model.** Lesson 3.3 built `JobCardSummaryQuery`, and a dashboard route would use it. It
wants a registration number, which lives on `Vehicle`, which this course never persisted — Module 3
persisted one aggregate. Inventing a vehicles table at the end of the capstone would be scope with no
lesson in it.

**Domain event dispatch.** The use cases release events and drop them. A real application dispatches
them after the write — Lesson 2.3 — and a dispatcher with no subscribers is a class that exists to be
pointed at.

**Authentication, pagination, versioning, rate limiting.** All real, none of them about domain
architecture.

---

## 8. Two things assembling it exposed

Neither was visible from inside any single lesson. That is what a capstone is for.

### `remove()` then `add()`

```php
$jobCard->complete();

$this->jobCards->remove($jobCard->id());
$this->jobCards->add($jobCard);
```

Lesson 3.1 chose a **collection-oriented** repository — `add()`/`remove()`, no `save()` — warned it
had a cost, and refused to design around it. It said *watch for it*. Lesson 3.4's contract test was
written not to depend on it and said so.

This is the bill, and it is worse than ugly: `remove()` commits, then `add()` commits, so **between
them the job card does not exist**. A concurrent read gets a `JobCardNotFound` for a job card that is
merely being saved, and a crash between the two loses it.

Single-threaded against `:memory:`, nothing will ever show it. That does not make it fine; it makes it
the kind of bug that ships.

The two real fixes — a unit of work, or an explicit `save()` — are exactly the trade 3.1 described.
Having now paid the other side of it, you are in a position to say which you would choose. That is
why the course built neither.

### Module 1's value objects are not in Module 4's tree

`POST /job-cards` with an empty `vehicleId` returns **500**.

`VehicleId::fromString('')` throws a bare `\InvalidArgumentException` — no marker, no failure code —
so the mapper has nothing to key on and correctly refuses to guess. The right answer is 422 and the
application cannot give it.

Module 4 named every throw site in `JobCard` and in the repositories, and explicitly left the value
objects to the reader. Nothing noticed for two modules, because nothing had asked a value object to
refuse something on the far side of an HTTP boundary.

**The checks assert the gap rather than working around it**, and it is the capstone's most useful
finding. The fix is mechanical — the tree already has the branch these belong on — and it is the last
exercise in the course.

---

## 9. What you can now say you can do

Take a set of business rules people argue about in meetings and express them in code that refuses to
represent an invalid state; persist it without the persistence leaking into it; give every failure a
name, a code and a place in a hierarchy; render those failures at an HTTP boundary without leaking
anything internal; and **prove**, mechanically, that the result does not depend on the framework.

And, from Modules 0 and 5: say when not to do any of it.

That last one is the part almost every course omits, and it is the half that stops this becoming
another codebase with four layers of indirection around an `UPDATE` statement.

---

## 10. Examples

```bash
php module-6-capstone/lesson-6.1-the-workshop-end-to-end/examples/01-a-request-end-to-end.php
php module-6-capstone/lesson-6.1-the-workshop-end-to-end/examples/02-the-scan.php
```

Example 01 traces one request through every layer and names the lesson each hop came from. Example 02
runs the scan against a clean domain and then against four deliberately broken ones.

---

## 11. Then

- `challenge/CHALLENGE.md` — nine files, no new rules
- `quiz/QUIZ.md` — 20 questions, threshold 15
- `GATES.md` — **Gate 6**, which reaches back to every module

That is the course.
