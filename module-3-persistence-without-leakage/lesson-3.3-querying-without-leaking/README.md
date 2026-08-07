# Lesson 3.3 — Querying Without Leaking
> **Module 3: Persistence Without Leakage** · Advanced Domain Architecture

| | |
|---|---|
| **Time** | ~25 min reading · ~20 min running the examples. Challenge is short. |
| **Comes after** | Lesson 3.2 — The Mapping Problem |
| **Covers** | Reads that are not "load one aggregate" · Read models · Why this interface is *not* in the domain · CQRS without the ceremony · Anemic on purpose |
| **You write** | `infrastructure/JobCardSummary.php`, `infrastructure/JobCardSummaryQuery.php` |
| **Needs** | PHP 8.5+ |

---

## 📁 Lesson folder

```
lesson-3.3-querying-without-leaking/
├── README.md
├── examples/
│   ├── 01-the-dashboard-through-aggregates.php   ← what it costs, counted
│   └── 02-the-read-model.php                     ← the same answer, a different shape
├── challenge/
│   ├── CHALLENGE.md
│   ├── checks.php
│   ├── starter.php
│   └── solution/
└── quiz/QUIZ.md
```

---

## 1 — The question 3.1 could not answer

The service manager wants a screen: **every open job card, with its registration and total, largest
first.**

Everything so far says to go through the repository. So:

```php
foreach ($jobCards->all() as $jobCard) {          // load every aggregate
    $vehicle = $vehicles->get($jobCard->vehicleId());   // and a second one, per row
    $rows[] = [
        $jobCard->id(), $vehicle->registration(), $jobCard->total(), ...
    ];
}
usort($rows, ...);
```

Four hundred job cards means four hundred aggregates, each with its lines, plus four hundred vehicle
loads — to produce a table of six columns, which is then thrown away.

**That is not a performance bug to optimise. It is the wrong shape.** The repository promises
aggregates; what this screen needs is a table.

> Lesson 3.1's quiz put it this way: *if what comes back is not an aggregate, forcing it through an
> interface that promises aggregates is the wrong shape twice over.*

---

## 2 — Two shapes, and neither is wrong

| | Write side | Read side |
|---|-----------|-----------|
| Returns | aggregates | rows / view objects |
| Purpose | enforce rules while changing state | show somebody something |
| Consistency | must be exact | usually may be slightly stale |
| Shape decided by | the business's invariants | this screen |
| Changes when | the rules change | the screen changes |
| Lives in | `domain/` (interface) | infrastructure, entirely |

**They change for different reasons and at different rates.** A model serving both compromises the
boundary to fit a table, and the compromise always goes the same way — the aggregate gets a getter
it did not need, then a field, then a denormalised total.

---

## 3 — This is CQRS, and it costs nothing

Command Query Responsibility Segregation has a reputation, and most of it belongs to something else.

**What CQRS actually says:** the model you use to change things and the model you use to look at
things do not have to be the same model.

That is all. It is two classes in this lesson, and neither is exotic.

**What people think it says**, because they met it alongside these:

| Often bundled with | Actually required? |
|--------------------|:------------------:|
| Event sourcing | no |
| A separate read database | no |
| Eventual consistency between them | no |
| Message buses, projections, replay | no |

Every one of those is a *separate* decision with its own Rule 6 argument. You can adopt the useful
part — a query that returns a table rather than aggregates — on a Tuesday afternoon, with no
infrastructure, and stop there.

> **Take the read model. Leave the ceremony.** If somebody tells you CQRS requires a second
> database, they are describing a system they worked on, not the pattern.

---

## 4 — Why *this* interface is not in the domain

The surprising part, because 3.1 was emphatic that the repository interface belongs in
`domain/workshop/`.

The difference is what the thing **is**.

| | `JobCards::awaitingAuthorisation()` | `JobCardSummaryQuery::openByValueDescending()` |
|---|---|---|
| Is it a business concept? | Yes — the manager asks it every morning | No — it describes a page |
| Would it exist without software? | Yes, on a clipboard | No |
| Changes when | the business changes | the screen changes |
| Returns | aggregates | whatever the table shows this month |

**A domain interface that changes when a screen changes is not a domain interface.** So the whole
thing — read model, query, and any interface over it — lives in infrastructure, and the domain does
not know it exists.

The test is the one the whole course is built on: delete the database and the framework. *"Job cards
awaiting authorisation"* still means something. *"The dashboard, sorted by total descending, showing
the registration"* does not.

---

## 5 — The read model is anemic, and that is correct

Public readonly properties, formatting, and nothing else. Lesson 1.1 argued against this shape for a
whole lesson — and said, in section 7, that a DTO whose only job is to be rendered is the one place
it is right.

This is that place. Nothing here is changed, saved or reasoned about, so there is no rule to protect.

### It imports nothing from the domain

No `Money`, no `JobCardId`, no `JobCardStatus`. Scalars only, and the checks assert it.

> **A read model that can reach the domain will eventually contain a rule.**

Give it a `Money` and somebody adds `withDiscount()`. Give it a `JobCardStatus` and somebody adds
`canBeInvoiced()` — and now the invoicing rule lives in two places, one of which is a display object
nobody regards as authoritative. That is Lesson 1.4's scattered guards, arriving through a door
marked *presentation*.

**The honest counter-argument:** plenty of good codebases put value objects in read models and gain
real things — `Money` formats correctly per currency without duplicating the logic, and a typed id
cannot be passed to the wrong route. The cost is the coupling above, and it is a slope rather than a
cliff. This course takes the strict line because the failure it prevents is expensive and hard to
spot. Take the other line if you like, but **put the boundary somewhere and say where.**

### It carries something no aggregate has

`registration`. `JobCard` holds a `VehicleId`, not a `Vehicle` — Lesson 2.1 was emphatic. But a list
on a screen has to show the registration, so the query joins for it.

That one field is the whole argument for read models: **the shape a screen needs and the shape a
consistency boundary needs are different shapes.**

---

## 6 — Where the duplication is, and why it is accepted

`JobCardSummary::formattedTotal()` repeats a little of `Currency::format()`. `isOpen()` repeats
something `JobCardStatus::isTerminal()` already knows.

That is real duplication and it is accepted on purpose:

- **Importing the enum** would put a domain type in a view object — §5.
- **The duplicated logic is small** and will not change. If it were twenty lines, the answer would be
  a shared formatter *in the presentation layer* — still not the domain enum.
- **`isOpen()` is not the same question** as `isTerminal()`. The domain says which statuses have no
  successors; the dashboard says which job cards to show this morning. They coincide today, and if
  the workshop ever wants cancelled job cards to stay on the list for a week, one changes and the
  other does not.

> Duplication between a domain rule and a display rule is often two rules that happen to agree.
> Merging them is how a screen preference ends up governing an invariant.

---

## 7 — Where this stops being enough

Honest limits, so you know what you have and have not bought.

| Symptom | What it means |
|---------|--------------|
| The join gets slow at scale | A materialised view or a summary table, updated from domain events — 2.3 |
| Two screens want incompatible shapes | Two read models. They are cheap; that is the point. |
| The read must be transactionally exact | You are looking at the wrong side — that is a domain question |
| The read model starts making decisions | It is not a read model any more; move the rule to the domain |

That last one is the one to watch for. **The moment a number from a query becomes an input to a
decision** — a credit limit, a bonus, an automatic action — it stops being a dashboard figure and
belongs in the domain, with `Money` and its refusals. Lesson 1.4's distinction again: evidence
versus input to a decision.

---

## 8 — Common mistakes

| Mistake | Why it is wrong | Instead |
|---------|----------------|---------|
| A report method on the repository | Loads aggregates to build a table | A query service |
| The query interface in `domain/` | It changes when a screen changes | Infrastructure |
| Value objects in the read model | It will grow a rule | Scalars, and say where your line is |
| Behaviour on the read model | Same | Formatting only |
| One model for reads and writes | The boundary loses, every time | Two shapes |
| Adopting CQRS *and* event sourcing *and* a read database | Three decisions taken as one | Take the read model; leave the rest |
| A read model that decides something | It is domain logic in a view object | Move it |

---

## 9 — Where else this shows up

- **GraphQL resolvers** returning shapes that match a query rather than your tables.
- **An API serialiser** that is not your entity. If you have ever written `toArray()` on a model and
  regretted it, this lesson is that regret with a name.
- **Materialised views** — the database's own version of the same idea.
- **`SELECT COUNT(*)`** instead of loading rows to count them. Nobody argues about that one, and it
  is the same argument.

---

## ✅ Lesson checklist

- [ ] Read this README fully — especially sections 4 and 5
- [ ] Run and study `examples/01-the-dashboard-through-aggregates.php`
- [ ] Run and study `examples/02-the-read-model.php`
- [ ] Read `challenge/checks.php`
- [ ] Complete the challenge — two files, both in `infrastructure/`
- [ ] `php regression.php` is green
- [ ] Compare against `challenge/solution/`, log disagreements in `PROGRESS.md`
- [ ] Take `quiz/QUIZ.md` closed-book

---

*Next lesson: **3.4 — Testing Persistence** — one contract test run against two implementations,
which is Liskov cashed in as something you can execute. It closes Module 3.*
