# Quiz — Lesson 0.1: The Cost of DDD
> Closed book. Write your answers before opening the key at the bottom.
> **18 questions. Threshold: 14.** Anything you get wrong is a re-read target.

---

## Section A — Multiple choice

**Q1.** Which of these is the *strongest* single signal that tactical DDD is justified?

- A) The system has more than fifty database tables.
- B) An invariant spans more than one object.
- C) The team has read a book about it.
- D) The system uses an ORM.

---

**Q2.** "Architecture theatre" is best described as:

- A) A codebase with too many design patterns.
- B) A codebase with the artefacts of a domain layer and none of its guarantees.
- C) A presentation about architecture given to management.
- D) Any use of DDD in a small project.

---

**Q3.** A colleague says: *"We should use a domain layer because we might need it later."* The best
response is:

- A) Agree — it is cheaper to build it now than to retrofit it.
- B) Agree, but only build the entities and add the rest later.
- C) Point out that the cost starts today and the benefit is speculative, and wait for the second
  place a rule needs enforcing.
- D) Refuse, because tactical DDD is never appropriate for new systems.

---

**Q4.** Which cost of tactical DDD is paid *most often*?

- A) Writing the mapping layer.
- B) Indirection, on every read of the code by every developer.
- C) Onboarding a new developer.
- D) Setting up the test suite.

---

**Q5.** A `status` column with six possible values is:

- A) Sufficient evidence of a lifecycle worth modelling.
- B) Not by itself evidence of anything — the question is whether transitions are *illegal*.
- C) A sign the model is anemic.
- D) A reason to use an enum, and nothing more.

---

**Q6.** Which of these is a legitimate reason to *decline* tactical DDD on a project that otherwise
scores well?

- A) The developers prefer procedural code.
- B) The ORM does not support it.
- C) The team has never done it and the deadline does not permit learning.
- D) The client has not asked for it.

---

**Q7.** The transaction script's characteristic failure mode is:

- A) Objects that are too large.
- B) Rule duplication across scripts, which drifts.
- C) Untestable code.
- D) Circular dependencies.

---

**Q8.** In the README's ledger, which cost first arrives at roughly "week three"?

- A) The mapping layer.
- B) Slower onboarding.
- C) Query awkwardness — the first report that does not fit "load one aggregate".
- D) Team disagreement.

---

**Q9.** Which is the correct scope for the output of the decision procedure?

- A) The whole application — it is an architectural choice and must be uniform.
- B) The individual class.
- C) Usually a bounded context or a single aggregate; rarely the whole system.
- D) The individual developer's preference.

---

**Q10.** A system is a thin client over a vendor's API, and the vendor enforces all the rules. This
argues against a domain layer because:

- A) The vendor's API is already object-oriented.
- B) Re-modelling the rules creates a second source of truth that will disagree with the first.
- C) You cannot test against a vendor API.
- D) Vendor APIs change too often.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 11 | Active Record is a legitimate engineering choice for most software. | |
| 12 | Architecture theatre is better than Active Record, because at least the structure is there. | |
| 13 | Tactical DDD's costs can be largely avoided by an experienced enough team. | |
| 14 | A codebase with one carefully modelled aggregate and forty Active Record classes is inconsistent and should be unified. | |

---

## Section C — Short answer

**Q15.** In two sentences: what is the difference between a rule that belongs in a domain model
and a validation constraint that does not?

*Your answer:*

---

**Q16.** Example 02 models the cost of rule changes over four years. Name one way the model
understates the cost of a domain layer, and one way it understates the cost of the alternatives.

*Your answer:*

---

**Q17.** A team adopts entities, value objects and repositories, but the entities have public
setters and the repositories return arrays. What have they bought, and what have they paid?

*Your answer:*

---

**Q18.** Your CTO mandates a domain layer for all new services, including a link shortener
maintained by two engineers in their spare time. Give the strongest argument *in favour* of the
mandate — steelman it, do not dismiss it — and then say what you would actually propose.

*Your answer:*

---
---

<details>
<summary><strong>Answer key</strong> — open only after you have written every answer</summary>

**Q1 — B.** An invariant spanning more than one object cannot be enforced by any single row, so it
will live in application code regardless; the only question is whether it lives in one place or in
however many places happen to touch it. A is irrelevant — table count measures size, not rule
density. C and D are not signals about the problem at all.

**Q2 — B.** The artefacts without the guarantees. This is the most common unhappy outcome and it is
strictly worse than the Active Record version it replaced, because every cost has been paid and
nothing has been bought.

**Q3 — C.** The cost starts on the first day and recurs forever; the benefit is a forecast. You
will also not build the model you currently imagine, because the second use case will differ from
your guess about it. D overstates: tactical DDD is frequently right for new systems where the rules
are already known to be tangled.

**Q4 — B.** Indirection is paid on every read, by everyone, forever. The mapping layer is written
once and maintained; onboarding recurs per hire. Reads outnumber all of them by orders of
magnitude, which is why the indirection cost is the one people underestimate most.

**Q5 — B.** Six states with free movement between them is a labelled field. The signal is
*missing arrows*: Completed cannot return to InProgress, Invoiced cannot go anywhere. If you can
draw the diagram and point at transitions that are deliberately absent, there is something to
protect. D is not wrong as advice, but it is not what the question asks.

**Q6 — C.** An unfamiliar pattern applied under deadline pressure produces the file count without
the discipline — architecture theatre by a different route. Saying this out loud in week one is
more professional than discovering it in month four. A is a preference, not a reason. B is false —
the ORM's capabilities constrain the mapping, not the decision.

**Q7 — B.** The same check, copy-pasted into nine scripts, drifts. Eight of them get updated and
the ninth does not, and nobody knows which until a customer notices.

**Q8 — C.** Reporting. "Show me all overdue job cards with the account holder's name" does not fit
"load one aggregate", and it is asked for almost immediately. Lesson 3.3 is entirely about this.

**Q9 — C.** Per bounded context, or per aggregate. "Yes for the job card, no for everything else"
is usually the right shape of answer, and being comfortable giving it is what the lesson is for. A
is the mistake that produces aggregates around settings tables.

**Q10 — B.** Two sources of truth for the same rule will diverge, and the divergence is discovered
by a customer rather than by a test. Note this is about *rules*, not data — caching the vendor's
data is fine; re-implementing their validation is not.

**Q11 — TRUE.** Most software has shallow rules and a schema that genuinely is the model. Choosing
Active Record deliberately, and being able to say why, is a mark of judgement rather than a
concession.

**Q12 — FALSE.** It is worse. Every cost has been paid; no guarantee has been bought; and the team
now believes it has protection that it does not have, which is more dangerous than knowing you have
none.

**Q13 — FALSE.** The costs are inherent to the approach, not artefacts of inexperience. Experience
changes how efficiently you pay them and how accurately you predict whether they are worth it. It
does not make indirection free.

**Q14 — FALSE.** That is proportionate, not inconsistent, and it is what mature systems that do
this well actually look like. Unifying it would mean either forty unnecessary domain models or one
unprotected aggregate, and both are worse.

**Q15** A domain rule constrains the *business*: it is true regardless of how the data arrived, it
holds for a console command and a queue worker and a data migration as much as for a form, and
breaking it means the business is in a state it does not permit. A validation constraint is about
*this input*: it produces a good error message for a user at a boundary, it may reasonably differ
between boundaries, and it can be relaxed for an admin import without anything becoming untrue. The
practical test: if you loaded the record straight from the database and the rule were violated,
would that be a data corruption incident? If yes, it is a domain rule.

**Q16** *Understates the domain layer's cost:* the model counts edits only, but indirection is paid
on every read, and the mapping layer is ongoing work that appears in no column. (Also acceptable:
onboarding, reporting paths, the discipline cost.)
*Understates the alternatives' cost:* the model assumes every site is found. Frequently one is not,
and a missed site is not an edit — it is a production bug, discovered by a customer, at a cost
nothing in the table captures.

**Q17** They have bought approximately nothing. Public setters mean the invariants are decorative —
any code can put the object into a state the constructor would have refused. Repositories returning
arrays mean the domain vocabulary stops at the repository boundary, so callers go back to
associative-array access and the type system stops helping. What they have paid is the full bill:
indirection, extra files, extra concepts to explain, slower onboarding, and a mapping layer. This is
architecture theatre exactly.

**Q18** *The steelman:* consistency has real value. A team that uses one shape everywhere can move
between services without re-learning, review each other's code faster, and share tooling and
conventions. A per-project decision costs a meeting every time and tends to be settled by whoever
argues hardest rather than by the merits. There is also a fair point that skill is built by
practice, and a low-stakes service is a cheaper place to learn than a high-stakes one. A mandate is
a blunt instrument aimed at a real problem.

*What to propose:* accept the consistency goal and separate it from the mechanism. Offer a written
default — "domain layer where rules span objects, Active Record otherwise" — plus a short rationale
recorded in the repo for each service, so decisions are consistent and reviewable without being
uniform. Then offer the link shortener as the place to run a deliberate comparison, since the stakes
are low, and bring the result back. That gives the CTO consistency and evidence, which is what the
mandate was reaching for, without spending it on the wrong service.

</details>

---

**Score:** ____ / 18   ·   **Threshold: 14**

Below 14, re-read sections 2, 5 and 6 before starting Lesson 0.2. Record which questions you missed
in `PROGRESS.md`.
