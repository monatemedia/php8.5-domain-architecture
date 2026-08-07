# Quiz — Lesson 2.3: Domain Events
> Closed book. **20 questions. Threshold: 15.**

---

## Section A — Multiple choice

**Q1.** A domain event is best described as:

- A) A message telling a specific handler to do something.
- B) An aggregate stating what happened, to nobody in particular.
- C) A way of decoupling classes.
- D) An entry in an audit log.

---

**Q2.** Injecting a dispatcher into an aggregate breaks Rule 1 because:

- A) Dispatchers are slow.
- B) The domain object now holds a piece of infrastructure and cannot be constructed without it.
- C) It creates a circular dependency.
- D) Dispatchers cannot be mocked.

---

**Q3.** The *worse* problem with dispatching immediately is:

- A) Performance.
- B) Handlers run before the transaction commits, so they act on things that get rolled back.
- C) The ordering is unpredictable.
- D) It requires an interface.

---

**Q4.** Why is that worse than an inconsistent database row?

- A) It is not — both are equally bad.
- B) You cannot un-send an email. The database rolled back; the outside world did not.
- C) Databases have constraints.
- D) Rows can be repaired by a migration.

---

**Q5.** `releaseEvents()` clears the list because:

- A) It saves memory.
- B) An aggregate saved twice in one request would otherwise dispatch its events twice.
- C) PHP requires it.
- D) The events become invalid after dispatch.

---

**Q6.** `InvoiceJobCard` versus `JobCardInvoiced`:

- A) Stylistic preference.
- B) The first is a command — an instruction that can be refused; the second is a fact that cannot.
- C) The first is for internal use, the second for external.
- D) The second must carry more data.

---

**Q7.** `SendInvoiceEmail` is a poor event name mainly because:

- A) It is too long.
- B) It names a mechanism, so there is nowhere to put a second thing that must also happen.
- C) Emails are infrastructure.
- D) It is not past tense.

---

**Q8.** An event is `final readonly` and its constructor takes a `JobCard`. A handler calls
`$event->jobCard->cancel()`. What happens?

- A) A `TypeError`.
- B) It works — `readonly` protects the reference, not the object it points at.
- C) The event is cloned first.
- D) `Error: Cannot modify readonly property`.

---

**Q9.** Why should `JobCardCompleted` carry only the job card id?

- A) Smaller payloads are faster.
- B) An event is a published contract: adding a field later is easy, removing one is a breaking
  change to code you cannot grep.
- C) The handler can load whatever else it needs.
- D) `JobCardId` is the aggregate root's identity.

---

**Q10.** `JobCard::open()` records the event rather than the constructor because:

- A) Constructors cannot call protected methods.
- B) A mapper reconstituting from storage must not re-announce that a vehicle was booked in.
- C) Static factories are more testable.
- D) The constructor is private.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 11 | Every state transition on an aggregate should record an event. | |
| 12 | `recordThat()` should be public so application code can add context. | |
| 13 | A handler that returns a courtesy vehicle belongs in `domain/workshop/`. | |
| 14 | Recording events makes an aggregate easier to test than dispatching them. | |
| 15 | Domain events remove the eventual-consistency problems from Lesson 2.2. | |

---

## Section C — Short answer

**Q16.** Where should an event's timestamp come from? Give the three options and when each is right.

*Your answer:*

---

**Q17.** `JobCardInvoiced` carries the total. Argue it should carry only the id and let the handler
load the job card. Then say which you would ship.

*Your answer:*

---

**Q18.** Two handlers subscribe to `JobCardInvoiced`: one emails the account holder, one posts to
accounting. The email handler throws. What happens, what should happen, and who decides?

*Your answer:*

---

**Q19.** Lesson 2.2 said a service that completes the job card *and* returns the vehicle is a
legitimate option. When is that better than an event, and when does it stop being better?

*Your answer:*

---

**Q20.** `RecordsDomainEvents` is a trait. Make the strongest case for an abstract `AggregateRoot`
base class instead, then say what you would do.

*Your answer:*

---
---

<details>
<summary><strong>Answer key</strong></summary>

**Q1 — B.** To nobody in particular. That is what distinguishes it from a command and from a method
call: the aggregate does not know, and must not know, who cares.

**Q2 — B.** It cannot be constructed without infrastructure, so every test, mapper and fixture needs
one.

**Q3 — B.** Handlers fire inside the operation, before the commit.

**Q4 — B.** Asymmetry of reversibility. The transaction rolled back and the email did not. The
database is fine, and the customer has an invoice for work that was never billed.

**Q5 — B.** Saving twice is ordinary; dispatching twice is a support ticket. The name matters too:
`getEvents()` invites a second call.

**Q6 — B.** Command versus fact. A command can be refused; a fact has already happened, and if you
want to record that it was undone, that is a *new* event.

**Q7 — B.** It names a mechanism. The day somebody also wants an accounting posting, there is
nowhere to put it — you either add a second dispatch or overload the handler. `JobCardInvoiced`
names what happened, and any number of things can subscribe. (D is wrong for a subtler reason: it
*is* imperative, but naming the mechanism is the deeper error — `InvoiceEmailSent` is past tense and
still a bad event.)

**Q8 — B.** It works, and that is the problem. `readonly` protects the property, not the object
graph behind it. Example 03 runs it.

**Q9 — B.** Published contract, asymmetric cost. (C is a real consideration but points the other
way — it argues for carrying *more*. The answer is about what it costs to be wrong in each
direction.)

**Q10 — B.** Opening is a business event; loading is not. Module 3's mapper rebuilds the object
without calling `open()`, and if the constructor recorded it, every load would announce a booking
that happened months ago.

**Q11 — FALSE.** An event is a published contract. `startWork()` and `awaitParts()` record nothing
here because nobody needs them, and publishing one nobody asked for is a commitment with no benefit.
Add them when somebody needs them.

**Q12 — FALSE.** A public `recordThat()` lets any caller announce that a job card was invoiced
without invoicing it — a claim about the past that never happened, dispatched to handlers that act
on it.

**Q13 — FALSE.** It knows about two aggregates, which is exactly what neither of them may do. It is
application code and lives outside the domain directory. The capstone's import-scanning test would
catch it if you put it there.

**Q14 — TRUE.** Call the method, look at the returned array. No mock, no spy, no container — and
Rule 2 of the previous course says to test behaviour rather than the mechanism it is delivered
through.

**Q15 — FALSE.** They are how it is *implemented*, not a solution to it. The window is still there,
handlers still fail, ordering is still a fact about your wiring. Lesson 2.2 was about whether the
window is acceptable; this lesson is about building it. Deciding in that order is what stops the
machinery being a solution in search of a problem.

**Q16** *No timestamp; infrastructure stamps it when the event is released or stored.* What this
course does. The recorded time is when it was saved rather than when it happened — fine for audit
ordering and for "what happened first", wrong if a rule depends on the exact moment.

*Inject a `Clock` into the method that records the event.* Correct, and it puts a parameter on
methods that would not otherwise need one. Right as soon as a business rule depends on time.

*Call `new \DateTimeImmutable()` inside the event.* Cheapest, most common, and the one to avoid —
the domain now depends on the system clock and no signature admits it, so nobody can substitute it.

*The rule of thumb:* if the time is **evidence**, infrastructure can supply it. If it is **input to
a decision** — is this overdue, does it fall in this tax year — it must be a parameter.

**Q17** *The case for id-only:* it is one fewer field in a published contract, and a handler that
loads the aggregate always sees the current truth rather than a stale copy. If the total is later
corrected, an event carrying the old figure is now a lie sitting in a queue.

*Why I would still ship the total:* what was **billed** is a fact about a moment, and it is exactly
the fact an accounting handler needs. A handler that loads the job card and asks for its total gets
whatever the calculation produces *today* — and Lesson 2.1 §6 was explicit that deriving is safe only
while the inputs are frozen. The day a tax treatment or rounding rule changes, the id-only version
silently re-prices history in the accounts, and nothing fails.

The stale-copy objection is real but points at a different fix: if invoices can be corrected, that is
a `JobCardInvoiceCorrected` event, not a reason to make the original vague.

**Q18** *What happens:* it depends entirely on the dispatcher, which is the honest and unsatisfying
answer. A naive `foreach` that does not catch means the exception propagates from the first handler
and the accounting posting never runs — and which handler that is depends on subscription order,
which is a fact about your wiring rather than about the business.

*What should happen:* handlers should be independent, so one failing must not prevent another
running. That means catching per handler, logging, and retrying the failed one — which in turn means
handlers must be idempotent, because a retry may re-run one that partly succeeded.

*Who decides:* the business decides what happens when the email genuinely cannot be sent —
retry forever, alert someone, print it and post it. The team decides the mechanism. What is not
acceptable is the default: an unexamined `foreach` where the answer is "whichever one was subscribed
first", discovered during an incident.

**Q19** *Better when:* there is one caller and one thing that must happen. A service is a plain
function call — no dispatcher, no subscriptions, no wiring at the composition root, and the whole
flow is readable top to bottom in one file. Rule 6: an event system for one handler is ceremony.

*It stops being better when* there is a second caller who must not forget, or a second thing that
must happen. Both are Lesson 1.1's second-caller problem: the service protects the callers who
remember it exists. Once completing a job card must return a courtesy vehicle *and* notify the
account holder *and* update a dashboard, the service becomes a place where unrelated concerns
accumulate, and every new one is another edit to a function that had nothing to do with it.

The honest signal is the second reason to react, not the first.

**Q20** *The strongest case for a base class:* it can enforce things a trait cannot. An abstract
`AggregateRoot` could declare `abstract public function id(): mixed`, give every aggregate a
consistent equality method, and let repositories type-hint against a real type rather than against
nothing at all. A trait is copy-and-paste with tooling — there is no type, so nothing can require
that a thing being saved is an aggregate root. That is a genuine loss and it shows up in Module 3.

*What I would do:* keep the trait, and add an `AggregateRoot` **interface** if and when a repository
needs the type. That gets the type without spending the inheritance slot, which is the composition
answer to a problem people reflexively solve with inheritance — Rule 4, and the decision table from
Lesson 1.2 of the previous course. The interface would declare only what callers genuinely need,
which today is `releaseEvents()`.

If you argued for the base class and named what the trait costs, mark yourself correct. The failure
is answering without noticing that a trait gives you no type.

</details>

---

**Score:** ____ / 20   ·   **Threshold: 15**
