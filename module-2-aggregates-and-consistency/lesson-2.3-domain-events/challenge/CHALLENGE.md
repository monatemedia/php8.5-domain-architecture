# Challenge — Lesson 2.3: Domain Events

> **You write:** `DomainEvent.php`, `RecordsDomainEvents.php`, `JobCardOpened.php`,
> `JobCardCompleted.php`, `JobCardInvoiced.php` — plus one addition to `JobCard.php`
> **You run:** `php module-2-aggregates-and-consistency/lesson-2.3-domain-events/challenge/starter.php`
> **Done when it prints:** `ACCEPTANCE: all checks passed`

Smaller than 2.1 and 2.2. Most of it is four-line classes, and the interesting decisions are about
what to leave out.

---

## Read `checks.php` first.

---

## 1 — `DomainEvent`

```php
interface DomainEvent
{
    public function eventName(): string;
}
```

**One method.** `'job_card.invoiced'` — past tense, dotted, snake_cased, because it is going into
logs, queue routing keys and configuration, none of which want a PHP class name in them.

**Do not add `occurredAt()`.** The checks assert it is absent. `now` is the outside world, and an
event that fetches the system clock has a dependency nothing declares and nobody can substitute.
README §5 argues all three options and says when to take each.

Also absent, and worth understanding rather than just obeying:

| Tempting | Why not |
|----------|---------|
| `payload(): array` | Serialisation is infrastructure. Add it in the layer that needs it, where the format can change without touching the domain. |
| `aggregateId()` | Forces every event to have exactly one subject of one type. `JobCardOpened` names three things. |

Each of those is a method somebody will eventually want. Adding one is easy; removing it from an
interface other people implement is not.

---

## 2 — `RecordsDomainEvents` (a trait)

```php
trait RecordsDomainEvents
{
    private array $recordedEvents = [];

    protected function recordThat(DomainEvent $event): void;
    public function releaseEvents(): array;        // returns AND clears
    public function hasRecordedEvents(): bool;     // does NOT clear
}
```

**A trait, not a base class.** This is horizontal sharing between classes with no "is-a"
relationship — a `JobCard` is not a kind of `CourtesyFleet`, and neither is a kind of
`AggregateRoot` in any sense that means anything. An abstract parent would spend the one inheritance
slot every aggregate has, forever, on four lines of array handling. Lesson 1.2 of the previous
course, and Rule 4.

**`recordThat()` is `protected`.** The checks assert it. A public one would let any caller announce
that a job card had been invoiced without invoicing it — a claim about the past that never happened,
dispatched to handlers that act on it.

**`releaseEvents()` clears.** Not tidiness: an aggregate saved twice in one request is entirely
ordinary, and dispatching its events twice is a support ticket about a customer receiving two
invoices. The name says so — `getEvents()` implies a harmless read and invites a second call.

---

## 3 — The three events

All `final readonly`, all implementing `DomainEvent`.

| Class | Carries | `eventName()` |
|-------|---------|---------------|
| `JobCardOpened` | `JobCardId`, `VehicleId`, `AccountHolderId` | `job_card.opened` |
| `JobCardCompleted` | `JobCardId` | `job_card.completed` |
| `JobCardInvoiced` | `JobCardId`, `Money $total` | `job_card.invoiced` |

**None of them takes a `JobCard`.** The checks assert this by reflection. Passing the aggregate
hands every handler a live mutable reference to it — and `readonly` on the event does not help,
because it protects the *reference*, not the object it points at. Example 03 runs that failure.

The second reason outlasts the first: an event may be handled later, from a queue, next Tuesday. By
then the aggregate has moved on. An event carrying `$this` describes the aggregate *now*; a fact
about the past is supposed to describe the world *when it happened*.

**`JobCardCompleted` carries only the id, and that is not laziness.** The courtesy-vehicle handler
from Lesson 2.2 needs nothing else. Every extra field is something a handler may come to depend on,
and handlers are frequently written by people you will never meet. Adding a field later takes five
minutes; removing one is a breaking change to code you cannot grep.

**`JobCardInvoiced` carries the total.** `JobCard::total()` is derived, and Lesson 2.1 §6 explains
why that is safe *today*. The amount that was **billed** is a fact about a moment; the amount the
aggregate would compute is a fact about now. An accounting handler must record the first.

---

## 4 — `JobCard` — one addition, nothing removed

`use RecordsDomainEvents;` and record in three places:

| Operation | Records |
|-----------|---------|
| `open()` | `JobCardOpened` |
| `complete()` | `JobCardCompleted` |
| `invoice()` | `JobCardInvoiced`, with `$this->total()` |

**Nothing else records.** `startWork()`, `awaitParts()`, `partsArrived()` and `cancel()` record
nothing, and the checks assert it. Not every state transition is news — an event is a *published
contract*, and publishing one nobody asked for is a commitment with no benefit. Add them when
somebody needs them.

**`open()` records, not the constructor.** This matters more than it looks. In Module 3 a mapper
will reconstitute a job card from storage without calling `open()` — and it must not announce that a
vehicle was booked in for the second time. **Opening is a business event; loading is not.**

**Record after the guards and after the transition, never before.** The checks test this directly: a
refused `complete()` and a refused `invoice()` must record nothing. Get it wrong and the
courtesy-vehicle handler returns a car for a job card that is still in progress — and unlike a
database write, that cannot be rolled back.

**No dispatcher.** `open()` still takes exactly four arguments and the checks assert the types.
`JobCard` still constructs from nothing but its own data.

---

## Acceptance criteria

- [ ] Five new files in `domain/workshop/`, namespace `Workshop`
- [ ] `DomainEvent` declares exactly one method, and it is not `occurredAt()`
- [ ] `recordThat()` is `protected`; `releaseEvents()` is public and clears
- [ ] All three events are `final readonly`
- [ ] No event constructor takes a `JobCard`
- [ ] A refused operation records nothing
- [ ] `JobCard::open()` takes the same four arguments as in 2.1
- [ ] No file imports anything outside `Workshop`
- [ ] **`php regression.php` is green** — 2.1's spec is what is at risk here

---

## Hints

<details>
<summary>Hint 1 — the trait, in full</summary>

```php
public function releaseEvents(): array
{
    $events               = $this->recordedEvents;
    $this->recordedEvents = [];

    return $events;
}
```

Three lines, and the middle one is the whole point.

</details>

<details>
<summary>Hint 2 — where recordThat() goes</summary>

```php
public function complete(): void
{
    if ($this->pendingAuthorisations !== []) {
        throw new \DomainException(/* ... */);
    }

    $this->transitionTo(JobCardStatus::Completed);

    $this->recordThat(new JobCardCompleted($this->id));   // last
}
```

Same rule as Lesson 1.4 §6 — validate first, mutate last — with recording as the final mutation. If
`transitionTo()` throws, the `recordThat()` line is never reached.

</details>

<details>
<summary>Hint 3 — open() records on the object, not on $this</summary>

`open()` is static, so there is no `$this` yet:

```php
$jobCard = new self($id, $vehicleId, $accountHolderId, $currency);
$jobCard->recordThat(new JobCardOpened($id, $vehicleId, $accountHolderId));

return $jobCard;
```

`recordThat()` is `protected`, and this call is inside the class, so it is allowed. PHP's visibility
is per-class, not per-instance.

</details>

---

## When it passes

Compare with `solution/`. Worth looking at:

- Whether they put `hasRecordedEvents()` in the trait or left it out
- Where exactly each `recordThat()` sits relative to the guards
- What their doc comment on `open()` says about mappers, and whether you had thought of that

---

## Then, without running anything

**Q1.** Write the handler that returns a courtesy vehicle when a job card completes. Where does it
live, and why not in `domain/workshop/`?

**Q2.** `JobCardInvoiced` carries the total. Argue that it should carry only the id, and the handler
should load the job card and ask. Then say which you would ship and why.

**Q3.** The `open()` event is recorded in the factory so that a mapper reconstituting from storage
does not re-announce it. Name one other operation in this model that will need the same care in
Module 3, and say what would go wrong.

**Q4.** Two handlers subscribe to `JobCardInvoiced`: one emails the account holder, one posts to
accounting. The email handler throws. What happens to the accounting posting, what *should* happen,
and who decides?

Q4 has no clean answer, and finding out that it has no clean answer is the point. Lesson 2.4 is the
last piece of Module 2.
