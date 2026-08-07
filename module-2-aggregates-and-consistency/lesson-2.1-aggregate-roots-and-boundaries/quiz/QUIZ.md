# Quiz — Lesson 2.1: Aggregate Roots and Boundaries
> Closed book. **20 questions. Threshold: 15.**

---

## Section A — Multiple choice

**Q1.** An aggregate is:

- A) Everything reachable from a root object.
- B) The set of objects that must be correct together, at the same instant, in one transaction.
- C) A table and its foreign keys.
- D) The objects that belong together conceptually.

---

**Q2.** The question that decides a boundary is:

- A) Is this thing part of that thing?
- B) Do they share a database table?
- C) If these two changed a second apart, would the business be harmed?
- D) Are they always loaded together?

---

**Q3.** `JobCard` holds `VehicleId` rather than `Vehicle` because:

- A) It uses less memory.
- B) Holding the object means loading and locking a second aggregate, and unrelated edits collide.
- C) `Vehicle` is in a different namespace.
- D) Value objects cannot hold entities.

---

**Q4.** Big aggregates are dangerous mainly because:

- A) They are slow to write.
- B) They fail slowly — everything works with forty rows and collapses in production.
- C) They violate the single responsibility principle.
- D) They cannot be serialised.

---

**Q5.** `lines()` returns `array_values($this->lines)`. A caller does
`$lines = $jobCard->lines(); $lines[0]->someSetter(...)`. This is safe only if:

- A) The array was copied.
- B) `lines()` is not public.
- C) The `Line` implementations are immutable.
- D) `$lines` is unset afterwards.

---

**Q6.** In example 02, the array copy did not protect the aggregate because:

- A) PHP passes arrays by reference.
- B) An array of objects copies the handles, not the objects.
- C) `array_values()` preserves references.
- D) The property was public.

---

**Q7.** The transition rules live on `JobCardStatus` rather than in each lifecycle method because:

- A) Enums are faster than conditionals.
- B) One table can be read and corrected by a business person; four scattered conditions cannot.
- C) PHP requires it.
- D) It reduces the number of classes.

---

**Q8.** `Invoiced` and `Cancelled` are terminal. In the reference implementation this is expressed
by:

- A) An `isTerminal` boolean on each case.
- B) A guard in every lifecycle method.
- C) Having no entries in `allowedNext()`.
- D) A separate `TerminalStatus` enum.

---

**Q9.** `Completed → Cancelled` is illegal because:

- A) It is a self-loop.
- B) Work has been done and parts fitted; you undo that with a credit note, which has different
  accounting consequences.
- C) `Completed` is terminal.
- D) It would leave the total wrong.

---

**Q10.** `JobCard::total()` is derived rather than stored. What makes that safe here, specifically?

- A) Deriving is always safer than storing.
- B) `Money` is immutable and each line holds its own copy of the rate it was created with, so the
  answer cannot change.
- C) The lines are private.
- D) There are never enough lines for performance to matter.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 11 | An aggregate boundary is also a transaction boundary. | |
| 12 | `LabourLine` becoming an entity required a breaking change to its public API. | |
| 13 | The `Line` interface should expose `quantity()` so `JobCard` can price things itself. | |
| 14 | A stored total would be the wrong choice in every billing system. | |
| 15 | `JobCard` should enforce invariant I10 — one courtesy vehicle, one open job card. | |

---

## Section C — Short answer

**Q16.** The I8 guard in `complete()` is redundant — the state machine already refuses that
transition. Give the strongest argument for deleting it, then say what you would do.

*Your answer:*

---

**Q17.** You now have four near-identical identity classes. Argue that this is unacceptable
duplication, then argue against. Which do you believe, and what would change your mind?

*Your answer:*

---

**Q18.** `$jobCard->vehicle->registration()` no longer exists. Somebody still has to print the
registration on the invoice. Where does that happen, and what did the boundary buy in exchange?

*Your answer:*

---

**Q19.** `PartNumber` keeps hyphens; `RegistrationNumber` strips them. Both are otherwise
identical. Explain why extracting a shared `NormalisedString` base class would be a poor trade.

*Your answer:*

---

**Q20.** Can `JobCard` enforce I10 — a courtesy vehicle is on at most one open job card at any
time? Say precisely what it would need to see, why giving it that would be a mistake, and what the
two honest alternatives are.

*Your answer:*

---
---

<details>
<summary><strong>Answer key</strong></summary>

**Q1 — B.** Correct together, at the same instant, in one transaction. A and D are the two most
common wrong answers, and each produces a different failure: A gives you an aggregate the size of
your object graph, D gives you one shaped by conversation rather than by consistency.

**Q2 — C.** And note it is a question you ask a *person*. If you cannot find the person, you are
guessing.

**Q3 — B.** Loading one job card would load the account holder's entire history, and two clerks
editing two unrelated job cards for the same fleet customer would collide because the transaction
covers both.

**Q4 — B.** They fail slowly. That is what makes them expensive: by the time the cost is visible in
production, the shape is load-bearing and nothing can safely be made smaller.

**Q5 — C.** Immutable contents. The array copy (A) is real and irrelevant — example 02 has the array
copy and enforces nothing.

**Q6 — B.** The handles are copied, not the objects. PHP protected the shape of the collection and
nothing at all about its contents.

**Q7 — B.** One table, checkable by the business. The scattered version is where the
`Completed → Cancelled` bug lives, and finding it by reading needs the whole table held in your head
while you read four unrelated method bodies.

**Q8 — C.** An empty `allowedNext()`. No flag to keep in step with anything.

**Q9 — B.** Different operation, different accounting consequences, different audit trail. Allowing
the cancel makes work that actually happened disappear. (C is false — `Completed` is not terminal;
it can become `Invoiced`.)

**Q10 — B.** Immutable inputs. Deriving is not inherently safe, and A is the trap: the moment the
*calculation* becomes historical — a new tax treatment, a different rounding rule — a derived total
silently re-prices last year's invoices.

**Q11 — TRUE.** Which is precisely why a rule spanning two aggregates is hard, and why Lesson 2.2
exists.

**Q12 — FALSE.** `create()` kept its exact 1.4 signature and minted the `LineId` internally. Lesson
1.4's checks still pass. That was the constraint, and the point: a concept crossed from value to
entity because a new requirement needed to refer to one in particular, not because the class got
more complicated.

**Q13 — FALSE.** `quantity()` is meaningless for labour, so `LabourLine` would have to return null
or throw. A wide interface forces one implementation to answer questions it has no answer to. Three
methods is not minimalism for its own sake — it is everything `JobCard` legitimately needs.

**Q14 — FALSE.** A stored total is the *right* choice in most billing systems, for the reason in
Q10: an invoice is a statement of what was charged and must not change. This course derives it
because the inputs are frozen, and says so rather than pretending the question is settled.

**Q15 — FALSE.** See Q20.

**Q16** *The strongest case for deleting it:* it is dead code that looks alive. A reader cannot tell
which of the two mechanisms is the rule, so the next person to change the transition table has to
work out whether the guard is load-bearing. Worse, if the table ever legitimately allows
`AwaitingAuthorisation → Completed`, this guard silently becomes the only thing enforcing I8 — a
duplicate that has quietly become the original, with no test naming it as such. Lesson 1.4's own
warning about copies that drift applies here, and the fact that they agree today is not a defence.

*What I would do:* keep it, and only because of the message. "Awaiting Authorisation cannot become
Completed" describes the model; "two items of additional work are still waiting for the account
holder to approve them" describes the workshop, and only one of those tells a service adviser what
to do next. That is Lesson 1.4 §5's one-rule-two-presentations case, and its test is satisfied:
deleting the guard makes nothing newly possible.

But the reasoning is narrow and does not generalise. If the same argument justified a third and
fourth duplicate, it would be wrong.

**Q17** *For extracting:* four files, thirty lines each, character-for-character identical except a
prefix. A reader has to confirm they really are identical. A bug fixed in one — say, rejecting a
value containing a newline — will be fixed in one.

*Against:* they are identical *today* and have no reason to stay that way. A `JobCardId` may acquire
a human-readable sequential form because the workshop quotes it over the phone; an
`AccountHolderId` may arrive from an accounting system in a format nobody here chooses. A shared
base couples four independent concepts through their least interesting property, and the coupling is
discovered on the day one of them has to change — at which point you either fork it anyway or add a
parameter that makes the base meaningless.

*What would change my mind:* a sixth or seventh identifier, or a real shared rule beyond
"non-empty". Four is within the range where duplication is cheaper than the wrong abstraction; a
dozen is not. Note the honest position is not "duplication is fine" — it is that this particular
duplication is cheaper than this particular coupling, and that is a judgement with a threshold.

**Q18** In whatever assembles the invoice — a service or a read model at the application layer,
which loads the job card, reads its `vehicleId()`, loads the vehicle from its own repository, and
puts both on the document. Module 3 builds exactly that.

*What the boundary bought:* loading a job card no longer loads a vehicle, so editing a mileage
reading cannot lock a job card and two clerks working on the same vehicle's two job cards do not
collide. More importantly, the second load is now *visible* — somebody decided to do it, and can see
what it costs. In the object-holding version the same load still happens, silently, on every single
job card read whether anyone needed the registration or not.

**Q19** Because the one character of behaviour that differs is the whole point. A registration
written `AB-12-CDE` is the same registration as `AB12CDE` — the hyphens are punctuation somebody
added for readability. `K9K-1234` and `K9K1234` may well be two different parts, and stripping the
hyphen would quietly merge them.

A shared base would have to be parameterised by exactly that difference — "strip these characters,
keep those" — so the abstraction's only configuration point is the thing that makes the two concepts
different. That is a strong sign you have found a coincidence of shape rather than a shared concept.
The duplication here is twenty lines of normalisation; the coupling is two business rules that have
nothing to do with each other sharing a fate.

**Q20** **No, and it should not try.**

*What it would need to see:* every other job card that is currently open, together with which
courtesy vehicle each has out. That is not a fact about this job card — it is a fact about the whole
set of them at one instant.

*Why giving it that is a mistake:* the boundary would have to grow to include every open job card,
because a boundary is a transaction boundary and the rule must hold within one. Every allocation
would then lock every open job card in the workshop, and the aggregate would be the whole system —
which is the failure from section 2 of the README in its most extreme form.

*The two honest alternatives:*

1. **A different aggregate owns the rule.** Something whose job is allocation — a courtesy fleet —
   holds the set and enforces the rule within its own boundary. The job card then references an
   allocation by identity. This keeps the rule instantaneous, at the cost of a new concept and a
   second thing to keep in step.

2. **Accept that it holds eventually.** Allocate, then detect and compensate if two allocations
   collided. Cheaper, scales better, and it means there is a window — measured in seconds — where
   the rule is violated.

Which is right is **not a technical decision.** Somebody in the workshop has to say whether "two
people booked the same courtesy car for ten seconds and then one of them got a phone call" is
acceptable. For a courtesy car it almost certainly is. For a surgical theatre it is not. That
conversation is Lesson 2.2.

</details>

---

**Score:** ____ / 20   ·   **Threshold: 15**
