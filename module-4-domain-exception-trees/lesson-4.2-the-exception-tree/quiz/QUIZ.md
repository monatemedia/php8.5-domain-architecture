# Quiz — Lesson 4.2: The Exception Tree
> Closed book. **20 questions. Threshold: 15.**

---

## Section A — Multiple choice

**Q1.** One exception class carrying a string code fails because:

- A) String comparison is slow.
- B) The code is data and the type is not, so a caller handling one failure writes a `switch` inside
  a `catch` — unchecked, and silent on a typo.
- C) Codes cannot be namespaced.
- D) It cannot carry structured context.

---

**Q2.** A flat list of thirty classes, all extending `\DomainException` directly, fails because:

- A) Thirty files is too many.
- B) There is no way to catch a *subset* — a job-card use case must name every job-card class, and
  will forget the one added next quarter.
- C) `\DomainException` is deprecated.
- D) PHP limits inheritance depth.

---

**Q3.** The failure mode in Q2 is dangerous mainly because:

- A) It throws a fatal error.
- B) It fails silently — the code compiles, the tests pass, and the new failure falls through to
  whatever catches `\DomainException`.
- C) It is slow.
- D) It breaks autoloading.

---

**Q4.** The test for whether an intermediate node should exist is:

- A) Whether the diagram looks balanced.
- B) Name the `catch` clause that would use it and the field it holds — if you can do neither, you
  are drawing a diagram.
- C) Whether it has at least one subclass.
- D) Whether the domain has a corresponding aggregate root.

---

**Q5.** The course does **not** add a `CourtesyVehicleException` node because:

- A) The fleet is not important.
- B) With one subclass there is no field to hoist, and nobody catches a node when catching the child
  is more precise. A node with one child is a rename with extra steps.
- C) PHP forbids more than three levels.
- D) It would break regression.

---

**Q6.** Internal nodes are `abstract` so that:

- A) They load faster.
- B) `throw new WorkshopException('something went wrong')` will not compile — otherwise somebody
  does exactly that in a hurry, and you are back to a failure with no type.
- C) They can be mocked.
- D) They can hold constants.

---

**Q7.** Leaves are `final` so that:

- A) They cannot be extended by tests.
- B) The set of throwable types is closed and enumerable, which lets the boundary's mapper be
  exhaustive rather than hopeful.
- C) PHP optimises them.
- D) It matches the value objects.

---

**Q8.** The tree has two roots because:

- A) There are two aggregate roots.
- B) `\DomainException` and `\InvalidArgumentException` are siblings, so no class covers both — and
  the two mean genuinely different things.
- C) One is for tests.
- D) Interfaces cannot be extended.

---

**Q9.** The question that decides which branch a failure belongs on is:

- A) Would a retry succeed?
- B) Could the caller have known this was invalid without asking the domain?
- C) Is it a 4xx or a 5xx?
- D) Does it carry context?

---

**Q10.** `discoverAdditionalWork('')` raises `MissingWorkDescription`, on the input branch, because:

- A) Strings are arguments.
- B) An empty string is empty in the controller, in the form and in the caller's own variable —
  nothing about the job card's state was consulted.
- C) It is thrown before the transition.
- D) `\DomainException` cannot carry a string.

---

**Q11.** `authoriseAdditionalWork()` on a job card with nothing pending is a `WorkshopException`, not
input, because:

- A) It happens later.
- B) It depends on what the job card contains, and only the job card knows that.
- C) It has no arguments.
- D) It is thrown from a private method.

---

**Q12.** `context()` must contain scalars and lists of scalars, not domain objects, because:

- A) Objects are large.
- B) It becomes RFC 9457 extension members, which must be JSON — and serialising a `JobCardId` in
  the mapper would put domain types in infrastructure.
- C) PHP cannot serialise objects.
- D) Exceptions cannot hold objects.

---

**Q13.** The reference implementation puts `$status->value` in the context and `$status->label()` in
the message because:

- A) Labels are longer.
- B) Machine values and human labels are different data with different audiences, and mixing them
  means one audience is always served badly.
- C) `value` is faster.
- D) The context has a length limit.

---

**Q14.** `OutstandingAuthorisations` keeps `descriptions` as a list rather than a joined sentence
because:

- A) Arrays are cheaper.
- B) Joining puts a presentation decision inside the domain and takes from the client the ability to
  render bullet points.
- C) `implode` is not available in the domain layer.
- D) Lists sort better.

---

**Q15.** `failureCode()` is declared per class rather than derived from the class name because:

- A) Derivation is slow.
- B) Deriving re-couples the wire format to the class name — the coupling the code exists to prevent
  — and makes it invisible, so a rename shows up in review as nothing at all.
- C) PHP has no `preg_replace`.
- D) Class names contain namespaces.

---

## Section B — Short answer

**Q16.** Explain, in two sentences, why `CarriesContext` is a separate interface rather than two
methods added to `WorkshopFailure`.

---

**Q17.** `failureCode()` is declared on `CarriesContext` and deliberately **not** implemented on
`WorkshopException`. Describe what that achieves and what would go wrong with a default
implementation returning `''`.

---

**Q18.** Every new class in this lesson is still a `\DomainException` or a
`\InvalidArgumentException` by inheritance, so roughly forty assertions in Modules 1 to 3 keep
passing. State why that worked, and name the earlier decision it depended on.

---

**Q19.** `JobCard` now names seven exception classes it did not name before. Argue that this is
acceptable coupling, then state the threshold at which it stops being acceptable and what it would
be telling you.

---

**Q20.** A colleague adds `public function httpStatus(): int` to `CarriesContext`, arguing the
domain knows better than the boundary whether something is a 404 or a 409. Give the strongest version
of their case, then your answer.

---
---

# Answer key

**Q1 — B.**

**Q2 — B.**

**Q3 — B.** Worth dwelling on. Loud failures get fixed on the afternoon they appear. This one is
found by a customer.

**Q4 — B.**

**Q5 — B.** And the resolution is not "never add it" — it is *add it when the second class arrives*,
which is a two-minute change at that point. Rule 6 applied to a hierarchy: ceremony must be paid for.

**Q6 — B.**

**Q7 — B.**

**Q8 — B.** Both halves matter. If the two roots meant the same thing, the SPL constraint would be an
annoyance to work around rather than a distinction to model.

**Q9 — B.** Note this is *not* Lesson 4.1's test. Both branches are category (a) — expected failures
the business has an answer for. 4.1's three categories separate expected from exceptional; this
separates two kinds of expected.

**Q10 — B.**

**Q11 — B.**

**Q12 — B.**

**Q13 — B.** Example 03 makes this concrete: the context survives translation because `in_progress`
was never English.

**Q14 — B.** The *message* joins them, because a message is prose. The context does not, because
context is data.

**Q15 — B.**

**Q16.** Model answer: `WorkshopFailure` answers "may I catch this as a business refusal?", which
every throwable in the domain must answer and whose shape never changes — so it can be frozen.
`CarriesContext` answers "what do you know, in fields?", which not every failure needs to; merging
them would mean every future addition to the data contract is a breaking change for every
implementer, including the ones with no data.

**Q17.** Leaving an interface method undefined in an abstract class keeps it abstract, so PHP refuses
to load any concrete subclass that does not supply one. The compiler enforces the rule.

A default returning `''` would let a new exception ship with no code. Nothing would fail: the class
loads, the tests pass, the exception is thrown and caught correctly. The place you find out is a
client's `switch` falling through to its default branch — in their codebase, on their schedule.

**Q18.** It worked because each new class extends the SPL class the old anonymous throw used, through
two intermediate classes rather than directly, and `is_a`/`instanceof` do not care about depth.

It depended on Lesson 3.1 choosing `\DomainException` for `JobCardNotFound` **on the merits** — its
doc block argued that a missing job card is a business-level mistake rather than a crash, before
there was any tree to fit into. Had it been thrown as a `\RuntimeException` "because repositories are
infrastructure", this lesson would have been a migration rather than a re-parenting.

Re-parenting is cheap when the first parent was right.

**Q19.** It is acceptable because all seven are in `Workshop\` — no new `use` statements, no
infrastructure, and each one describes a rule this aggregate itself enforces. An aggregate naming the
failures of its own invariants is cohesion, not coupling.

The threshold is roughly the point at which the list stops being readable in one screen — call it
twenty. Past that it is not telling you the exception design is wrong; it is telling you **the
aggregate is too big.** The size of an aggregate's exception vocabulary measures the number of rules
it enforces, and Lesson 2.1 warned that big aggregates are the second most common DDD failure. This
is that warning arriving from a direction it did not anticipate.

**Q20.** Their case, at its strongest: the domain already decides that a missing job card and an
illegal transition are different kinds of failure — that is the whole tree — and the boundary's
mapping table is a second place encoding the same distinction. Two places encoding one decision
drift, and when they drift the domain is right and the table is stale. Putting the status next to
the rule keeps them together, and it is one integer.

The answer: it is one integer *today*. The moment a second boundary exists — a gRPC service, a queue
consumer, a CLI — the domain is holding an HTTP-shaped opinion that means nothing to them, and the
class needs `grpcCode()` too. More immediately, the same failure genuinely maps to different statuses
in different contexts: `JobCardNotFound` is a 404 for `GET /job-cards/{id}` and a 422 for a POST body
naming a job card that does not exist. The domain cannot know which, because it does not know what
was asked.

What to do instead: keep `failureCode()` in the domain and put the code-to-status table in
`infrastructure/`, then make the drift they are worried about impossible with a test asserting every
failure code has a mapping. That is Lesson 4.4, and the test is the part that answers their real
objection.

---

## Scoring

| Score | Verdict |
|-------|---------|
| 18–20 | Go to 4.3. |
| 15–17 | Pass. Re-read sections 3 and 4 — 4.4 leans on both. |
| 11–14 | Run example 01, then retake. |
| 0–10 | Re-read the lesson. Section 2 is the argument everything else rests on. |
