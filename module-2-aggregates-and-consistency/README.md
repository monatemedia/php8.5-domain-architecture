# Module 2 — Aggregates & Consistency
> Not built yet. Planned from `FUTURE_COURSE_Domain_Architecture.md`.

| Lesson | Subject |
|--------|---------|
| 2.1 | Aggregate roots and boundaries — what must be consistent *together, right now* |
| 2.2 | Transactions: what a boundary actually wraps; eventual consistency as a business decision |
| 2.3 | Domain events — how aggregates tell the system what happened without knowing who listens |
| 2.4 | Domain services — the narrow case, and how to tell one from an anemic model |

Turns `JobCard` into an aggregate root over the nine classes Module 1 builds, and takes on
invariant **I10**, which nothing in Module 1 can enforce.
