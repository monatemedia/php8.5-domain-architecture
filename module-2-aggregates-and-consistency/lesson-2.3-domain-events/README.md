# Lesson 2.3 — Domain Events
> **Module 2: Aggregates & Consistency** · Advanced Domain Architecture

| | |
|---|---|
| **Time** | ~30 min reading · ~25 min running the examples. Challenge on top. |
| **Comes after** | Lesson 2.2 — Transactions and Consistency |
| **Covers** | Record, don't dispatch · Events are facts about the past · What goes in an event · Where the timestamp went · Handlers, and why neither side knows the other |
| **You write** | `DomainEvent.php`, `RecordsDomainEvents.php`, three event classes — plus one addition to `JobCard.php` |
| **Needs PHP** | 8.5+ |

---

## 📁 Lesson folder

```
lesson-2.3-domain-events/
├── README.md
├── examples/
│   ├── 01-record-dont-dispatch.php        ← the bug immediate dispatch produces
│   ├── 02-neither-side-knows.php          ← 2.2's loose end, tied off
│   └── 03-facts-about-the-past.php        ← naming, immutability, what goes in
├── challenge/
│   ├── CHALLENGE.md
│   ├── checks.php
│   ├── starter.php
│   └── solution/
└── quiz/QUIZ.md
```

---

## 1 — The problem this solves

Lesson 2.2 ended on an unanswered question, and it is worth restating because it is the whole
reason this lesson exists.

`CourtesyFleet` does not know whether a job card is still open. A completed job card could hold a
courtesy vehicle forever. Something has to return it — and every obvious option is wrong:

| Option | Why not |
|--------|---------|
| The fleet asks the job card | A second aggregate inside the fleet's boundary. Two aggregates, one transaction, example 01 of 2.2. |
| The job card calls the fleet | `JobCard` now knows courtesy vehicles exist, which is a concern it does not otherwise have. Coupled, just pointing the other way. |
| A service calls both | Better, and it works — but now every caller who completes a job card must remember to call that service. Lesson 1.1's second-caller problem. |

The answer is that `JobCard` **records that it was completed** and says nothing about who cares.
Something else, subscribed to that fact, tells the fleet. Neither aggregate knows the other exists.

> **A domain event is an aggregate saying what happened, to nobody in particular.**

---

## 2 — Record, don't dispatch

The single most important decision in this lesson, and the one most libraries get wrong by default.

### Dispatching immediately

```php
public function invoice(): void
{
    $this->transitionTo(JobCardStatus::Invoiced);
    $this->dispatcher->dispatch(new JobCardInvoiced($this->id));   // ❌
}
```

Two things are now broken and one is subtle.

**The obvious one:** `JobCard` has a dependency. It cannot be constructed without a dispatcher, so
every test needs one, every mapper needs one, and Rule 1 is broken — the domain is holding a piece
of infrastructure.

**The subtle one, which is worse.** The handlers run *inside* the operation, before the transaction
commits. If anything later in that transaction fails and it rolls back, the job card is not
invoiced — and the accounting system has already been told it was, and the customer has already been
emailed a bill.

**You cannot un-send an email.** The database rolled back; the outside world did not. This is
example 01 of Lesson 2.2 with the consequences pointed outward instead of inward.

### Recording

```php
public function invoice(): void
{
    $this->transitionTo(JobCardStatus::Invoiced);
    $this->recordThat(new JobCardInvoiced($this->id, $this->total()));   // ✅
}
```

The event goes into a list on the aggregate. Nothing happens. Later — after the transaction has
committed — whoever saved the aggregate releases the events and dispatches them:

```php
$jobCard->invoice();
$jobCards->save($jobCard);              // commits

foreach ($jobCard->releaseEvents() as $event) {
    $dispatcher->dispatch($event);      // only now, and only if the commit worked
}
```

**What this buys:**

- The aggregate has no dependencies. It still constructs with nothing but its own data.
- Handlers run only for things that actually happened.
- The events are inspectable in a test: call the method, look at the list. No mock dispatcher, no
  spy, no framework.

That last point is worth more than it sounds. `releaseEvents()` makes "did completing this job card
announce that it was completed?" a one-line assertion against a plain array.

---

## 3 — `releaseEvents()` clears the list

```php
public function releaseEvents(): array
{
    $events = $this->recordedEvents;
    $this->recordedEvents = [];
    return $events;
}
```

The clearing is not tidiness. An aggregate that is saved twice in one request — entirely ordinary —
would otherwise dispatch every event a second time, and "the customer was emailed their invoice
twice" is a support ticket rather than a curiosity.

**Naming matters here.** `releaseEvents()` says something is being handed over and given up.
`getEvents()` implies a harmless read and invites somebody to call it twice.

---

## 4 — Events are facts about the past

Three consequences, and each one is a rule you can check.

### They are named in the past tense

`JobCardInvoiced`, not `InvoiceJobCard`. The second is a **command** — an instruction that can be
refused. An event has already happened and cannot be refused by anyone.

If you find yourself wanting to "cancel" an event, you have written a command. If you want to record
that something was undone, that is a *new* event: `JobCardInvoiceReversed`.

### They are immutable

`final readonly class`. A fact about the past does not change. This is the least controversial
`readonly` in the entire course.

### They carry data, not references

```php
✅  new JobCardInvoiced($this->id, $this->total());
❌  new JobCardInvoiced($this);
```

Passing the aggregate hands every handler a live, mutable reference to it — Lesson 2.1's leaky
collection, arriving by post. A handler could complete the job card, add lines, or cancel it, and
the aggregate that recorded the event would have no idea.

There is a second reason and it outlasts the first: **an event may be handled later.** If it is
queued, or stored, or replayed next Tuesday, the aggregate it refers to has moved on. An event
carrying `$this` describes the aggregate *now*; an event carrying values describes the world *when
it happened*, which is what a fact about the past means.

### What to put in one

The test: **what does a handler need in order to act, without loading anything?**

| Event | Carries | Why |
|-------|---------|-----|
| `JobCardOpened` | job card id, vehicle id, account holder id | Enough to notify or index |
| `JobCardCompleted` | job card id | A handler returning the courtesy vehicle needs only this |
| `JobCardInvoiced` | job card id, total | The total *at the moment of invoicing* — it may be recalculated later |

Do not put everything in "just in case". An event is a published contract: every field is something
a handler may come to depend on, and removing one later is a breaking change to code you may not
own.

---

## 5 — Where did the timestamp go?

Every real event system stamps events with a time, and the events in this challenge have none. That
is deliberate, and it is a trade-off rather than an obvious win.

**The problem.** The time an event occurred is *now*, and `now` is the outside world. A domain object
calling `new \DateTimeImmutable()` has reached for the clock, which is Rule 1 broken — and a test
for anything time-dependent immediately requires controlling the universe.

**The three honest options:**

| Option | Cost |
|--------|------|
| **No timestamp; infrastructure stamps on release** — what this course does | The recorded time is when it was *saved*, not when it *happened*. Fine for audit ordering; wrong if a rule depends on the exact moment. |
| **Inject a `Clock` interface into the aggregate method** | Correct, and it puts a parameter on every method that records an event. Reach for this the moment a business rule depends on time. |
| **Call `new \DateTimeImmutable()` in the event constructor** | Cheapest to write, and the domain now has a hidden dependency on the system clock that no signature admits to. |

The third is what most codebases do and it is the one to avoid — not because a timestamp is
expensive, but because a dependency nothing declares is one nobody can substitute.

**The rule of thumb:** if the time is *evidence*, infrastructure can supply it. If the time is
*input to a decision* — "is this job card overdue?", "does this fall in the current tax year?" — it
must be a parameter, and Module 3 will make you pass it.

---

## 6 — The handler, and why neither side knows

```
   JobCard                    Dispatcher                CourtesyFleet
      │                                                        │
      │ complete()                                             │
      │   records JobCardCompleted                             │
      │                                                        │
      │ releaseEvents() ────────►                              │
      │                            │                           │
      │                            │ ──► ReturnCourtesyVehicle │
      │                            │        ──► returnFromJobCard()
```

`JobCard` knows nothing about courtesy vehicles. `CourtesyFleet` knows nothing about job card
lifecycles. The handler in the middle knows about both, and it is **application** code, not domain
code — it lives outside `domain/workshop/`, which is why the challenge does not ask you to write one
in there.

The connection is a subscription, made once, at the composition root. That is Rule 1 from the
previous course — wiring belongs at the entry point — arriving in a new form.

**Two aggregates, two transactions.** The job card is completed and committed; then the fleet is
updated. There is a window in which the job card is complete and the vehicle is still out, and if
the handler fails the vehicle stays out until something retries.

That is Lesson 2.2's eventual consistency again, and this time you have the machinery to build it
rather than only the argument for it. Domain events are *how* eventual consistency is implemented;
2.2 was about *whether* it is acceptable.

---

## 7 — Common mistakes

| Mistake | Why it is wrong | Instead |
|---------|----------------|---------|
| Injecting a dispatcher into an aggregate | The domain holds infrastructure; handlers run before commit | Record; dispatch after commit |
| Dispatching inside the domain method | Handlers fire for things that get rolled back | Same |
| `getEvents()` that does not clear | Saved twice, dispatched twice | `releaseEvents()` |
| Present-tense names | That is a command, and commands can be refused | Past tense |
| Putting `$this` in the event | A live mutable reference, handed to everyone | Carry values |
| Everything "just in case" | An event is a published contract; fields are hard to remove | What a handler needs to act |
| `new \DateTimeImmutable()` inside the event | An undeclared dependency on the clock | §5 |
| A handler that throws and is never retried | The eventual consistency never eventuates | Make failure visible; retry |

---

## 8 — Where else this shows up

Events are the most transferable idea in this module:

- **Redux / Elm** — actions are past-tense facts, reducers are handlers, and the store is a
  dispatcher. The same shape, from the other end of the industry.
- **Webhooks** — somebody else's domain events, delivered over HTTP. Every rule in §4 applies, and
  the "published contract" warning applies twice as hard.
- **Audit logs** — usually a worse version of an event stream, reconstructed after somebody asked
  for one.
- **Database triggers** — immediate dispatch, in the one place you cannot test it. Everything in §2
  applies.

If you have ever debugged a trigger that fired inside a transaction that later rolled back, you have
met §2 already.

---

## ✅ Lesson checklist

- [ ] Read this README fully — especially sections 2 and 4
- [ ] Run and study `examples/01-record-dont-dispatch.php`
- [ ] Run and study `examples/02-neither-side-knows.php`
- [ ] Run and study `examples/03-facts-about-the-past.php`
- [ ] Read `challenge/checks.php`
- [ ] Complete the challenge
- [ ] **Re-run Lesson 2.1's starter.** `JobCard` changes, and 2.1 must still pass.
- [ ] `php regression.php` — every earlier lesson, against your newest code
- [ ] Compare against `challenge/solution/`, log disagreements in `PROGRESS.md`
- [ ] Take `quiz/QUIZ.md` closed-book

---

*Next lesson: **2.4 — Domain Services** — the narrow case for an operation that belongs to no single
object, and how to tell one from an anemic model with more folders.*
