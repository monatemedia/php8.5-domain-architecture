# The Running Domain — A Vehicle Workshop
> The one business every lesson in this course models. Read it once, then keep it open.

---

## Why one domain, and why this one

**Why one domain at all.** The main weakness of *PHP 8.5 OOP Mastery* is that nothing accumulates:
you complete seventeen standalone exercises and never build anything. Here, every challenge from
Module 1 onwards writes into `domain/workshop/`, and by the capstone you have a codebase you have
built yourself rather than a folder of unrelated scripts.

**Why a workshop.** Three criteria, in order of importance:

1. **The rules are genuinely interesting.** A job card cannot be invoiced before completion.
   Parts cannot be added after invoicing. Labour bills in fixed time units. A courtesy vehicle
   cannot be on two jobs at once. Additional work needs authorisation before it proceeds. Those
   are real invariants spanning multiple objects — the exact conditions that make an aggregate
   worth the ceremony. A blog or a to-do list has no rule worth modelling, which is why tutorials
   built on them make DDD look like an elaborate way to save a row.
2. **It needs no prior knowledge.** Everyone understands *car goes in, work happens, bill comes
   out*. Compare a course built on insurance underwriting or double-entry bookkeeping, where you
   spend half your attention learning the business instead of the modelling.
3. **It is concrete enough to argue about.** You can hold an opinion on whether a line item
   belongs inside the job card. That argument is the lesson.

**This is a teaching vehicle, not a product plan.** Nobody finishing this course is expected to
want workshop software. Every module ends with a *Where else this shows up* note mapping the
pattern onto other domains, so the transfer is explicit rather than assumed.

---

## The language

Use these words. Do not invent synonyms — Rule 4 of `COURSE_PHILOSOPHY.md` is not optional here.

| Word | What the business means by it |
|------|-------------------------------|
| **Account holder** | The person or company who pays. Note: the business says *account holder*, never *customer*. Lesson 0.2 is about why that difference matters. |
| **Vehicle** | The thing being worked on. Identified in conversation by its registration. |
| **Book in** | Accept a vehicle and open a job card for it. |
| **Job card** | The record of one visit: what was asked for, what was done, what it costs. The central object of the whole course. |
| **Line** | One chargeable item on a job card. Either a **part** or **labour** — never both. |
| **Labour unit** | The billing increment for time. This workshop bills in **tenths of an hour**, always rounded up, with a half-hour minimum per line. |
| **Additional work** | Work discovered after the vehicle was booked in, which was not on the original request. |
| **Authorisation** | The account holder's explicit approval for additional work, obtained before the work is done. |
| **Complete** | All agreed work is finished. The vehicle is ready. Nothing has been billed yet. |
| **Invoice** | Turn a completed job card into a bill. This is the point of no return. |
| **Courtesy vehicle** | A vehicle lent to an account holder while theirs is in. |

---

## The job card lifecycle

```
                        ┌──────────────────────┐
                        ▼                      │
   Booked ────────► InProgress ──────► AwaitingAuthorisation
      │                 │  ▲                   │
      │                 │  └───────────────────┘
      │                 │
      │                 ├──────────► AwaitingParts ──┐
      │                 │  ▲                          │
      │                 │  └──────────────────────────┘
      │                 │
      │                 ▼
      │             Completed ────────► Invoiced
      │                                    │
      ▼                                    ▼
  Cancelled                          (terminal)
      ▲                                
      └───────── from InProgress only ──────
```

**Legal transitions, exhaustively:**

| From | To | Condition |
|------|----|-----------|
| Booked | InProgress | work starts |
| Booked | Cancelled | always allowed |
| InProgress | AwaitingAuthorisation | additional work is discovered above the auto-approve threshold |
| InProgress | AwaitingParts | a required part is not in stock |
| InProgress | Completed | no work is awaiting authorisation |
| InProgress | Cancelled | always allowed |
| AwaitingAuthorisation | InProgress | the account holder authorises, **or** declines and the work is dropped |
| AwaitingParts | InProgress | the part arrives |
| Completed | Invoiced | always allowed |
| Invoiced | — | terminal. Nothing may follow. |
| Cancelled | — | terminal. Nothing may follow. |

Everything not in that table is illegal and must throw. **`Completed → Cancelled` is illegal and
`Invoiced → Cancelled` is illegal** — a bill that has been issued is reversed by a credit note,
which is a different operation with different accounting consequences. That distinction is a real
one, and Lesson 4.1 uses it.

---

## The invariants

These are the rules the model must make impossible to break. The numbers are referred to by
lesson, so they are stable.

| # | Invariant | First enforced in |
|---|-----------|-------------------|
| **I1** | Money is never negative, and two Money values of different currencies never combine. | 1.2 |
| **I2** | A registration number is stored normalised: uppercase, separators stripped. Two registrations that normalise the same are the same registration. | 1.2 |
| **I3** | Labour is billed in whole tenths of an hour, rounded **up**, with a minimum of five tenths per line. | 1.4 |
| **I4** | An odometer reading may never decrease for a given vehicle. | 1.3 |
| **I5** | A job card's total always equals the sum of its lines. There is no moment at which it does not. | 2.1 |
| **I6** | No line may be added to, or removed from, an invoiced job card. | 2.1 |
| **I7** | A job card cannot be invoiced unless it is Completed. | 2.1 |
| **I8** | A job card cannot be completed while any additional work awaits authorisation. | 2.1 |
| **I9** | Only the transitions in the table above are legal. | 2.1 |
| **I10** | A courtesy vehicle is on at most one open job card at any time. | 2.2 |

**I10 is the interesting one** and it is deliberately last. It spans two aggregates, so it cannot
be enforced inside either of them without making one of them enormous. Lesson 2.2 is entirely
about what you do instead, and about why "make it eventually consistent" is a decision for the
workshop manager rather than for you.

---

## What is *not* an invariant

Worth as much attention as the list above, and it is the distinction most material skips.

- **"Additional work under a threshold proceeds without authorisation."** That is a **policy**.
  The threshold changes; someone in the business sets it; it may differ per account holder. A
  policy is passed *in* to the model, not hard-coded inside it.
- **"Tax is 20%."** Also a policy, and a volatile one. A rate baked into `JobCard` is a bug with
  a commencement date.
- **"A job card should usually be invoiced within 30 days."** Not a rule at all — a report. The
  word *usually* is the tell. Nothing in the domain should enforce it, and Lesson 3.3 is about
  where reports like this actually live.

If you take one thing from this section: **an invariant is something the system must never allow;
a policy is something the business currently prefers.** Model the first as a constructor guard.
Pass the second in as an argument.

---

## The objects, and which module builds them

| Object | Kind | Built in |
|--------|------|----------|
| `CourtesyVehicle` | entity | **1.1** |
| `Currency` | backed enum with behaviour | **1.2** |
| `Money` | value object | **1.2** |
| `RegistrationNumber` | value object | **1.2** |
| `VehicleId` | identity value object | **1.3** |
| `OdometerReading` | value object | **1.3** |
| `Vehicle` | entity | **1.3** |
| `LabourUnits` | value object | **1.4** |
| `LabourLine` | value object → entity in 2.1 | **1.4** |
| `PartNumber`, `PartLine` | value object, entity | 2.1 |
| `JobCardId`, `AccountHolderId` | identity value objects | 2.1 |
| `JobCard` | aggregate root | 2.1 |
| `JobCardStatus` | backed enum with transition rules | 2.1 |
| `JobCardOpened`, `JobCardCompleted`, `JobCardInvoiced` | domain events | 2.3 |
| `JobCards` (repository interface) | domain interface | 3.1 |
| `InMemoryJobCards`, `SqliteJobCards` | infrastructure | 3.1, 3.2 |
| `WorkshopException` tree | domain exceptions | 4.2 |

`LabourLine` is a **value object in 1.4 and an entity in 2.1**, and that change is deliberate. Two
labour lines with the same description, duration and rate are interchangeable — until a job card
needs to remove *one particular* line, at which point it needs identity. Watching a concept cross
that line is a better demonstration of the entity/value distinction than any definition of it.

Everything lands in `domain/workshop/` under the `Workshop\` namespace. Nothing in that directory
may import anything outside it — the capstone proves it with a test that reads the imports.

---

## Where else this shows up

The whole point of a teaching domain is that you leave it behind. The shape you are learning is
**one root object owning a collection of lines, with a lifecycle and a point of no return**, and
it is everywhere:

| This course | E-commerce | Insurance | Travel | Accounting | Healthcare |
|-------------|-----------|-----------|--------|-----------|------------|
| Job card | Order | Policy | Booking | Invoice | Episode of care |
| Line | Order line | Claim | Leg / segment | Journal entry | Procedure |
| Complete → Invoice | Fulfil → Charge | Assess → Settle | Confirm → Ticket | Post → Close period | Discharge → Bill |
| Cannot invoice incomplete | Cannot charge unfulfilled | Cannot settle unassessed | Cannot ticket unconfirmed | Cannot close an unbalanced period | Cannot bill before discharge |
| Courtesy vehicle on one job | Stock reserved for one order | One adjuster per claim | One seat, one passenger | One asset, one depreciation schedule | One bed, one patient |

If a pattern in this course does not have a row in that table, be suspicious of it. A pattern that
only works for workshops is not a pattern.
