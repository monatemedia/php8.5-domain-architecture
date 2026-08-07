# Module 2 — Aggregates & Consistency
> **Complete.** All four lessons are built and verified.

| Lesson | Subject | |
|--------|---------|---|
| [2.1](lesson-2.1-aggregate-roots-and-boundaries/) ⭐ | Aggregate roots and boundaries — what must be consistent *together, right now* | ✅ built |
| [2.2](lesson-2.2-transactions-and-consistency/) ⭐ | Transactions: what a boundary actually wraps; eventual consistency as a business decision | ✅ built |
| [2.3](lesson-2.3-domain-events/) | Domain events — how aggregates tell the system what happened without knowing who listens | ✅ built |
| [2.4](lesson-2.4-domain-services/) | Domain services — the narrow case, and how to tell one from an anemic model | ✅ built |

Turns `JobCard` into an aggregate root over the classes Module 1 builds, and takes on invariant
**I10**, which nothing in Module 1 can enforce.

---

## The thread through this module

Module 1 asked *where does this rule live?* and answered it one object at a time. Module 2 asks the
question that has no answer at that scale:

> **What has to be true across several objects at the same instant — and what happens when
> "the same instant" is too expensive?**

- **2.1** draws a boundary and puts a rule inside it.
- **2.2** finds a rule that will not fit inside any boundary you can afford, and decides what to do.
- **2.3** lets aggregates tell each other what happened without any of them knowing who is listening.
- **2.4** covers the operations that belong to no single object, and how to tell those from an
  anemic model with more folders.

Invariant **I10** — a courtesy vehicle is on at most one open job card at any time — is the spine.
It is raised in Lesson 1.1's follow-up questions, left explicitly unenforceable through Module 1,
declared out of scope in 2.1, and finally answered in 2.2. The answer is not a technique. It is a
conversation with the workshop manager, and that is the lesson.

---

## What you build

| Lesson | New in `domain/workshop/` |
|--------|---------------------------|
| 2.1 | `Line`, `LineId`, `PartNumber`, `PartLine`, `JobCardStatus`, `JobCardId`, `AccountHolderId`, `JobCard` — plus one addition to `LabourLine` |
| 2.2 | `CourtesyVehicleId`, `CourtesyFleet` — plus one addition to `CourtesyVehicle` |
| 2.3 | `DomainEvent`, `RecordsDomainEvents`, `JobCardOpened`, `JobCardCompleted`, `JobCardInvoiced` — plus one addition to `JobCard` |
| 2.4 | `TaxRate`, `CourtesyVehicleEligibility` — one value object and one domain service |

Three of the four challenges end with the same unusual acceptance criterion: **an earlier lesson's checks
must still pass afterwards.** 2.1 turns `LabourLine` from a value object into an entity; 2.2 gives
`CourtesyVehicle` an identity; 2.3 makes `JobCard` record events. All three are purely additive —
no existing signature moves. A
course where finishing one lesson un-finishes an earlier one is a course nobody trusts.

That constraint is not only pedagogical hygiene. Lesson 2.2 §8 is about the price it exacts:
`allocateTo()` still takes a `string` where it obviously wants a `JobCardId`, so the fleet converts
in both directions on every call. The wart is left in, and the quiz asks you to argue that leaving
it is the wrong call. Module 5 is that situation at a scale where the fix takes months.
