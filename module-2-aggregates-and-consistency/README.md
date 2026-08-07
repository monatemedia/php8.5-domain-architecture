# Module 2 — Aggregates & Consistency
> Lesson 2.1 is built. 2.2 to 2.4 are planned.

| Lesson | Subject | |
|--------|---------|---|
| [2.1](lesson-2.1-aggregate-roots-and-boundaries/) ⭐ | Aggregate roots and boundaries — what must be consistent *together, right now* | ✅ built |
| 2.2 | Transactions: what a boundary actually wraps; eventual consistency as a business decision | — |
| 2.3 | Domain events — how aggregates tell the system what happened without knowing who listens | — |
| 2.4 | Domain services — the narrow case, and how to tell one from an anemic model | — |

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
| 2.2 | planned |
| 2.3 | planned |
| 2.4 | planned |

Lesson 2.1's challenge has an unusual final acceptance criterion: **Lesson 1.4's checks must still
pass afterwards.** `LabourLine` changes from a value object into an entity, and it does so purely
additively — its `create()` signature does not move. A course where finishing one lesson
un-finishes an earlier one is a course nobody trusts.
