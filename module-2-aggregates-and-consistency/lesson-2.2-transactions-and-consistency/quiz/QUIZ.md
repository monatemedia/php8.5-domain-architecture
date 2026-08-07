# Quiz — Lesson 2.2: Transactions and Consistency
> Closed book. **20 questions. Threshold: 15.**

---

## Section A — Multiple choice

**Q1.** "One aggregate per transaction" is primarily a rule about:

- A) Performance.
- B) Correctness — a half-applied write leaves a state the model says cannot exist, and objects do
  not roll back.
- C) Database portability.
- D) Code style.

---

**Q2.** Two aggregates in one transaction risks a deadlock because:

- A) PHP is single-threaded.
- B) Two locks are taken in an order each developer chose independently.
- C) Transactions cannot be nested.
- D) Aggregates cannot be locked.

---

**Q3.** Your `CourtesyVehicle` refuses a second allocation. Why is that not invariant I10?

- A) The guard is in the wrong place.
- B) It only protects one object; load the same record twice and both copies obey it while the
  system ends up wrong.
- C) It should throw a different exception.
- D) It is, and the fleet is unnecessary.

---

**Q4.** What actually closes the two-copies hole?

- A) A cleverer check inside `CourtesyVehicle`.
- B) The fleet owning every vehicle, so there is exactly one instance of each.
- C) Database transactions.
- D) Making `CourtesyVehicle` readonly.

---

**Q5.** `CourtesyFleet` holding every vehicle is:

- A) Always correct.
- B) The large-aggregate failure from 2.1 §2 — chosen deliberately, with a known bill.
- C) A violation of the single responsibility principle.
- D) Only acceptable if the fleet is readonly.

---

**Q6.** What decides whether a fleet-wide aggregate is the right design?

- A) The number of developers on the team.
- B) Whether the ORM supports it.
- C) Cardinality — how many vehicles, and how many allocations per day.
- D) Whether the rule is an invariant or a policy.

---

**Q7.** `addVehicle()` takes a fleet code rather than a `CourtesyVehicle` because:

- A) Strings are cheaper to pass.
- B) A caller who passed an object in would keep a handle to it, which is the leak pointing inward.
- C) The constructor is private.
- D) It makes testing easier.

---

**Q8.** A compensating action differs from a rollback because:

- A) It runs in a transaction.
- B) It is faster.
- C) You cannot un-tell someone they had a car — you can only issue a visible correction.
- D) It is triggered by a domain event.

---

**Q9.** A *silent* compensating action means the system is:

- A) Eventually consistent.
- B) Quietly wrong.
- C) Strongly consistent.
- D) Correctly designed, as long as the data reconciles.

---

**Q10.** Which is the best way to ask a domain expert about eventual consistency?

- A) "Should this be eventually consistent?"
- B) "Can we tolerate a three-second window?"
- C) "If two of your people both booked the same courtesy car within a few seconds, what would
  happen today?"
- D) "Do you need strong consistency here?"

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 11 | The fleet adds a new check to enforce I10. | |
| 12 | A unique index in a database is a spanning rule enforced by a shared lock. | |
| 13 | "A job card has at most one courtesy vehicle" is a cross-aggregate rule. | |
| 14 | A boundary that has grown wrong as the business scaled represents a past mistake. | |
| 15 | Choosing eventual consistency is an engineering decision. | |

---

## Section C — Short answer

**Q16.** Example 01 rolls back the database and shows the in-memory object still changed. Explain
why that matters, and name a situation where it causes a real bug.

*Your answer:*

---

**Q17.** Defend the fleet-wide aggregate. Then say the number at which your defence stops working,
and how you would find that number out.

*Your answer:*

---

**Q18.** `new CourtesyVehicle('CV-01')` still works from anywhere. Name two things that would make
the boundary harder to violate by accident, and say what each costs.

*Your answer:*

---

**Q19.** The fleet does not know whether a job card is still open, so a completed job card could
hold a courtesy vehicle forever. Where should the return be triggered, and why can the fleet not
just ask the job card?

*Your answer:*

---

**Q20.** `allocateTo()` still takes a `string` because changing it would break Lesson 1.1. Argue
that this is the wrong call, then say what you would actually do.

*Your answer:*

---
---

<details>
<summary><strong>Answer key</strong></summary>

**Q1 — B.** Correctness. Contention is the second reason and the one people underrate, but the first
is that there is no rollback for an object already in memory.

**Q2 — B.** Two locks, in an order each developer picked because it read naturally for their own
operation. The bug exists only when both run at once — production, under load, and never in either
one's test.

**Q3 — B.** It protects one object. Two loads, two objects, both obeying the rule, one car promised
twice.

**Q4 — B.** Ownership. Note it is *not* a better check — the check was always fine and had nothing
to run against.

**Q5 — B.** Deliberately, with a known bill: every allocation locks the whole fleet, including cars
nobody touched.

**Q6 — C.** Cardinality, which is a fact about the business you have to go and ask for. If nobody
knows, say so rather than picking the sophisticated option to be safe.

**Q7 — B.** The leak pointing inward. The rule is symmetrical: nothing inside the boundary should
have a reference to it from outside.

**Q8 — C.** It is a new fact, not an undo. The real-world event already happened.

**Q9 — B.** Quietly wrong. Reconciling the data while leaving the customer uninformed is not
eventual consistency; it is a bug with a cleanup job attached.

**Q10 — C.** It uses their words and asks about something that already happens. A and D mean nothing
outside engineering and will get you whatever answer they think you want. B is closer but still
frames it as a technical tolerance rather than a description of their day.

**Q11 — FALSE.** It adds ownership. Lesson 1.1's guard does the work; the fleet guarantees there is
exactly one instance for it to run against.

**Q12 — TRUE.** And it is the right answer far more often than a hand-rolled aggregate. Rule 6
applies to consistency strategies too.

**Q13 — FALSE.** It is a fact about **one** job card. It only *looks* cross-aggregate because in this
design the fleet holds the allocations — so the fleet is where it can be answered. State a rule
precisely and a surprising number of them stop spanning anything.

**Q14 — FALSE.** It is wrong because the business grew, which is a normal and healthy reason to
change a boundary. Treating it as a past failure is how teams learn never to choose the simple thing.

**Q15 — FALSE.** It has a phone call to a real customer attached, and its acceptability depends
entirely on what that call costs. Courtesy car: fine. Operating theatre: no. Same technique,
opposite answers.

**Q16** The database rolled back and the object did not. If that object outlives the transaction —
a request that catches the exception and carries on, a queue worker processing a batch, a long-lived
service — it now believes it is completed while the database says otherwise.

*A real bug:* a worker processes fifty job cards in a loop, sharing an identity map. Number seventeen
fails halfway and is rolled back, but the in-memory object stays `completed`. Number thirty-one
reads it from the map, sees `completed`, and invoices it. Nothing in the logs connects the two, and
the invoice is for work the database says was never finished.

**Q17** *The defence:* twelve vehicles, eight allocations a day. Contention is theoretical — two
allocations colliding requires two people clicking within the same few milliseconds, which at that
volume happens roughly never. The whole aggregate loads in under a millisecond. In exchange, I10 is
instantaneous and provable, there is no reconciliation job, no compensation, no window, and nobody
ever has to ring a customer back. The simple design buys away an entire category of operational
work.

*Where it stops:* somewhere between 200 and a few thousand vehicles, and it depends more on
allocations per hour at peak than on fleet size. The honest signal is not a number I can name from
here — it is contention: when two allocations start waiting on each other during the 9am rush.

*How to find out:* ask how many courtesy vehicles they have and how many go out on a busy morning.
Both are facts the workshop knows and neither requires me to guess. If the answer is "twelve, maybe
eight", stop thinking about it. If it is "four hundred across nine branches", the design is already
wrong and it was wrong before I wrote it.

**Q18** *1. Make the constructor non-public and have the fleet use a factory method it alone can
reach.* PHP has no package-private, so this means either a static factory that only the fleet
sensibly calls (convention again, but a louder one) or moving the vehicle into a namespace whose
purpose is obvious. Cost: `new CourtesyVehicle(...)` in Lesson 1.1's checks breaks, so this cannot be
done additively — it is the deprecation cycle, not a free change.

*2. A test that scans imports and construction sites*, failing if anything outside `CourtesyFleet`
mentions `new CourtesyVehicle`. Cost: it is a lint rule, so it catches accidents and not
determination, and it needs maintaining. The capstone's import-scanning test is exactly this
technique.

The honest summary is that aggregate boundaries in PHP are held by agreement and code review. That
is not a defect to be engineered away; it is worth knowing so that the boundary is documented rather
than assumed.

**Q19** It should be triggered when the job card completes — that is the business event that means
the customer is coming to collect their own vehicle.

The fleet cannot ask the job card because that would put a second aggregate inside this boundary:
`CourtesyFleet` would hold or load `JobCard` objects, every allocation would touch both, and we are
back to two aggregates in one transaction with the deadlock from example 01.

Nor should the job card call the fleet directly — then `JobCard` knows about courtesy vehicles, which
is a concern it does not otherwise have, and the two aggregates are coupled in the other direction.

The answer is that `JobCard` records that it was completed and says nothing about who cares, and
something else — a handler, subscribed to that event — tells the fleet. Neither aggregate knows the
other exists. That is Lesson 2.3, and this is the question that makes domain events necessary rather
than decorative.

**Q20** *The case that it is wrong:* the model now has a typed `JobCardId` everywhere except one
place, and that place converts in both directions on every call. Every conversion is a chance to
pass the wrong string, which is exactly the bug typed identifiers exist to prevent — so the wart is
not cosmetic, it is a hole in the guarantee. Preserving a lesson's checks is a *course* constraint,
not a business one, and shaping a domain model around it is letting the tail wag the dog.

*What I would actually do:* the deprecation cycle. Add `allocateToJobCard(JobCardId)`, have the old
`allocateTo(string)` delegate to it, mark it `#[\Deprecated]`, migrate callers, delete it. Three
commits and no flag day. The reason this course does not is that Lesson 1.1's checks are a stand-in
for *callers you do not control*, and demonstrating what that constraint costs is worth more here
than a clean model. Module 5 is entirely about that situation, at a scale where the deprecation
cycle takes months.

</details>

---

**Score:** ____ / 20   ·   **Threshold: 15**
