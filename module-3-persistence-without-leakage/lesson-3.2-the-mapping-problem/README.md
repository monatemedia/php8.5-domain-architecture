# Lesson 3.2 — The Mapping Problem ⭐
> **Module 3: Persistence Without Leakage** · Advanced Domain Architecture

| | |
|---|---|
| **Time** | ~40 min reading · ~30 min running the examples. The longest challenge in the course. |
| **Comes after** | Lesson 3.1 — Repository Interfaces |
| **Covers** | Why a rich aggregate resists storage · `reconstitute()` · What it skips and why · Reflection, honestly · Discriminators · Where an ORM helps and where it fights you |
| **You write** | `reconstitute()` on three domain classes · `infrastructure/JobCardMapper.php` |
| **Needs** | PHP 8.5+ |

---

## 📁 Lesson folder

```
lesson-3.2-the-mapping-problem/
├── README.md
├── examples/
│   ├── 01-five-things-in-the-way.php     ← every obstacle, run
│   ├── 02-the-round-trip.php             ← what breaks if you use create()
│   └── 03-reconstitute-or-reflect.php    ← both answers, both costs
├── challenge/
│   ├── CHALLENGE.md
│   ├── checks.php
│   ├── starter.php
│   └── solution/
└── quiz/QUIZ.md
```

---

## 1 — The lesson most DDD material skips

Books get you to a rich aggregate and then say *"and of course you will need a repository"*, as
though the last step were plumbing.

It is not. **Every single thing Modules 1 and 2 did to keep the model honest is now in the way**,
and this is the lesson where people who have followed the advice quietly abandon it — usually by
adding public setters "just for the ORM", which undoes the lot.

The bill arriving is not a design failure. But it *is* a bill, and it is bigger than the books say.

---

## 2 — Five things in the way

Take `JobCard` and try to rebuild one from a database row.

| Obstacle | Why it exists | Why it blocks you |
|----------|--------------|-------------------|
| **Private constructor** | So nothing can build one that skipped `open()` | You cannot `new` it |
| **`open()` records `JobCardOpened`** | Opening is a business event (2.3) | Loading through it announces a booking from months ago |
| **`LabourLine::create()` mints a `LineId`** | Lines need identity (2.1) | Round-tripping gives every line a *new* identity |
| **Status transitions are guarded** | I9, in one table (2.1) | A job card stored as `Invoiced` cannot legally be *transitioned* there now |
| **`readonly` lines, private state** | Nothing changes behind the aggregate's back | Nothing outside can put the values back |

Each was a good decision. Together they mean **the public API cannot express "this object already
exists".**

That is the whole problem, and it has a name: **reconstitution**. Creating and reconstituting are
different operations, and a model that only offers the first cannot be loaded.

---

## 3 — The answer this course takes: a named factory

```php
public static function reconstitute(
    JobCardId $id, VehicleId $vehicleId, AccountHolderId $accountHolderId,
    Currency $currency, JobCardStatus $status,
    array $lines, array $pendingAuthorisations,
): self
```

Four lines of body, each doing something the public API forbids: it builds through the private
constructor, sets the status directly, installs the lines with their existing identities, and
restores the pending list.

### It records nothing — and that comes free

`open()` records; the **constructor** does not. That was decided in Lesson 2.3 with the reason stated
at the time — *"opening is a business event; loading is not"* — and this is where it pays off.

Had the event been recorded in the constructor, there would be no way out that did not involve a
flag, and a flag on an aggregate saying "do not announce things right now" is the kind of thing that
is switched on somewhere it should not be.

### It sets the status directly, bypassing I9

The sharpest form of the argument. **A job card loaded as `Invoiced` did not transition to `Invoiced`
now** — it was invoiced last Tuesday, and I9 governed that transition when it happened. Replaying
the lifecycle to arrive at a stored status would be absurd, and for `Cancelled` it is not even
possible in one step.

> **The invariant is about *changes*. Loading is not a change.** It is the same object arriving from
> a different place. Everything I9 says still applies *from the moment it is loaded*, which the
> challenge tests.

### What it skips, and what it does not

It skips the aggregate's own guards, because **this data already passed them**. Re-running them
would mean the model trusts its own database less than its callers, and it would turn a rule change
into a load failure for every historical row.

It does **not** skip the value objects. The mapper builds `Money::of()` and `LabourUnits::ofTenths()`
through their normal factories, guards and all — those are cheap, total, and true of every value
that has ever existed.

> **Reconstitution relaxes the rules of the aggregate, not the rules of everything it holds.**

### The cost, stated plainly

`reconstitute()` is **public**, and PHP has no friend classes and no package-private. Anyone can
call it:

```php
JobCard::reconstitute($id, ..., JobCardStatus::Invoiced, [], []);   // nothing objects
```

An invoiced job card with no lines and a zero total. The mitigations are a name that says what it is
for, a doc block saying who may call it, and a review that asks why anything but a mapper is calling
it. That is genuinely all there is, and anyone who tells you otherwise is selling something.

---

## 4 — The alternative: reflection

Keep the domain pristine and let the mapper write private properties directly.

```php
$reflection = new \ReflectionClass(JobCard::class);
$jobCard    = $reflection->newInstanceWithoutConstructor();

$property = $reflection->getProperty('status');
$property->setValue($jobCard, $status);
```

**For:** no hole in the domain. No `reconstitute()` for application code to misuse, and the
aggregate's public API is exactly what the business needs and nothing else. This is what Doctrine
does, and it is why Doctrine can hydrate entities with private constructors.

**Against:** the mapper now depends on **property names**, which are the most private thing a class
has. Rename `$status` to `$currentStatus` — a refactor every IDE will do silently — and the mapper
breaks at runtime, not at compile time, in production, on load. It is also slower, and it needs
`newInstanceWithoutConstructor()`, which skips property initialisers too.

**Neither is free.** One puts a hole in the domain; the other couples infrastructure to private
names. `examples/03-reconstitute-or-reflect.php` runs both.

> This course takes `reconstitute()`, because a hole with a name on it is easier to police than a
> coupling nothing declares. If your team has strong review discipline and an ORM already doing the
> reflection, the other answer is defensible. What is not defensible is public setters.

---

## 5 — Why the mapper takes arrays

```php
public function fromRows(array $row, array $lineRows): JobCard
```

No PDO, no SQL, no connection — the checks assert it.

**Because mapping is the part worth testing exhaustively.** Every line type, every status, every
empty case, every round trip. A mapper that took a `PDOStatement` would need a database for each of
those, and tests that need a database get run less often than tests that do not.

The repository fetches rows; the mapper turns rows into aggregates. Lesson 3.4 supplies the SQLite
half, and it is the boring half.

---

## 6 — The discriminator, and where the `instanceof` belongs

A `Line` row has to become the right class again, so the row carries a `kind` column — `'labour'` or
`'part'`.

**The domain has no notion of "kind".** `Line` has three methods and none says which it is, because
`JobCard` does not care — Lesson 2.1 rejected `isLabour()` on the interface for exactly that reason.

Storage does care. So the discriminator lives in the mapper, along with the `instanceof` that
produces it:

```php
if ($line instanceof LabourLine) { return $base + ['kind' => 'labour', ...]; }
```

> **A type check in a mapper is serialisation. The same check in a domain method is logic that
> belongs on the object.** Same construct, opposite verdicts, and the difference is which layer it
> is in.

### The nullable columns

A labour row has no part number; a part row has no tenths. Half of every line row is null — which is
Lesson 0.2's shared-model tell exactly.

It is acceptable here for one reason: **this is not a model.** It is a wire format, it is never
handed to anything, and the aggregate it produces has no nullable fields at all. The tell warns
about a *model* shaped that way; a storage layout may be ugly as long as the ugliness stops at the
mapper.

The alternative is a table per line type, which is cleaner and costs a join or a second query. At two
line types this is the better trade; at six it would not be.

---

## 7 — Where an ORM helps, and where it fights you

Honestly, because this is where most people meet the question.

**Where Doctrine helps:** it does the reflection for you, it handles identity maps and change
tracking, and it can map value objects as embeddables. If you are going to reflect anyway, a mature
library reflects better than your mapper will.

**Where it fights you:**

| | |
|---|---|
| **Collections** | `JobCard`'s lines are a private array behind an aggregate boundary. Doctrine wants its own collection type in there, which leaks a library type into your domain. |
| **Value objects in collections** | Embeddables inside a one-to-many are awkward, and `Money` inside `LabourLine` inside `JobCard` is exactly that shape. |
| **Constructors** | It bypasses yours, which is what you want — and it means the aggregate's own factory is not what created the object, so anything in `open()` never ran. |
| **Lazy loading** | A proxy that loads on access is invisible to the aggregate and can turn one method call into forty queries. |
| **The identity map** | Helpful and a genuine trap: two `find()` calls give the same object, so the two-copies bug from Lesson 2.2 *appears* to be solved, and is not — across requests, it is still there. |

**The judgement:** an ORM is worth it when the mapping is mostly mechanical and the team already
knows the library. A hand-rolled mapper is worth it when the aggregate is genuinely awkward — which,
by the time you have done Modules 1 and 2 properly, it is. Rule 6 applies to persistence libraries
as much as to patterns.

---

## 8 — Common mistakes

| Mistake | Why it is wrong | Instead |
|---------|----------------|---------|
| Public setters "for the ORM" | Undoes every invariant, permanently | `reconstitute()`, or reflection |
| Loading through `create()` / `open()` | New identities and phantom events | A separate reconstitution path |
| Re-running guards on load | The model distrusts its own storage; a rule change breaks history | Skip the aggregate's guards; keep the value objects' |
| Skipping the value objects' guards too | Now nothing is validated anywhere | Build values through their normal factories |
| A mapper that takes a `PDOStatement` | The part most worth testing now needs a database | Arrays in, aggregates out |
| `isLabour()` on the `Line` interface | Storage's problem, in the domain's vocabulary | `instanceof` in the mapper |
| Silently skipping an unknown row kind | A missing line means a wrong invoice | Throw |
| A stored total | Two facts that can disagree — Lesson 2.1 §6 | Derive; the lines are the truth |

---

## 9 — Where else this shows up

Reconstitution is everywhere once you have a name for it:

- **Deserialisation** in any language — `__wakeup`, `unserialize`, JSON to object. Same problem,
  same two answers.
- **Event sourcing** rebuilds an aggregate by replaying events, which is reconstitution taken to its
  logical end — and note it *does* replay the lifecycle, which is why it needs `apply` methods
  separate from the guarded ones.
- **Test factories** that need an object in a state the public API cannot reach quickly. If yours
  uses reflection, you have already met this.

---

## ✅ Lesson checklist

- [ ] Read this README fully — especially sections 3 and 4
- [ ] Run and study `examples/01-five-things-in-the-way.php`
- [ ] Run and study `examples/02-the-round-trip.php`
- [ ] Run and study `examples/03-reconstitute-or-reflect.php`
- [ ] Read `challenge/checks.php` — the round-trip section is the specification
- [ ] Complete the challenge
- [ ] **`php regression.php`** — you edited three classes that four earlier lessons specified
- [ ] Compare against `challenge/solution/`, log disagreements in `PROGRESS.md`
- [ ] Take `quiz/QUIZ.md` closed-book

---

*Next lesson: **3.3 — Querying Without Leaking** — the reads that do not fit "load one aggregate",
and a first honest look at CQRS without the ceremony.*
