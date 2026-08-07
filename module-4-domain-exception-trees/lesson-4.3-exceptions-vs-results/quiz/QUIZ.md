# Quiz — Lesson 4.3: Exceptions vs Result Types
> Closed book. **20 questions. Threshold: 15.**

---

## Section A — Multiple choice

**Q1.** The one thing an exception structurally cannot do is:

- A) Carry structured data.
- B) Report more than one reason, because unwinding carries a single object.
- C) Be caught by type.
- D) Cross a layer boundary.

---

**Q2.** The distinction that decides between the two styles is:

- A) Expected vs exceptional.
- B) Ask vs act — a question wants a return value; a committed action wants an exception.
- C) Domain vs infrastructure.
- D) 4xx vs 5xx.

---

**Q3.** Using results everywhere is a mistake because:

- A) They are slower.
- B) Every caller must handle every failure at every level, including the ninety percent with
  nothing to do but pass it upward — which exceptions do for free.
- C) PHP cannot express them.
- D) They cannot carry context.

---

**Q4.** "Exceptions are ergonomic and invisible; results are explicit and noisy" is:

- A) An argument for results.
- B) An argument for exceptions.
- C) The same property described from two sides, which is why neither wins outright.
- D) A statement about performance.

---

**Q5.** The characteristic bug of result types is:

- A) Forgetting to construct one.
- B) `$jobCard->checkCanComplete();` on its own line — a no-op shaped like a guard.
- C) Returning the wrong type.
- D) Catching them.

---

**Q6.** `#[\NoDiscard]` addresses that bug but does **not** fire when:

- A) The method is static.
- B) The value is assigned to a variable nobody reads.
- C) The class is readonly.
- D) The method returns void.

---

**Q7.** `@throws` is weaker than a return type because:

- A) It is longer.
- B) Nothing enforces it, nothing updates it when a called method starts throwing something new, and
  by the third layer it is fiction.
- C) It cannot name interfaces.
- D) IDEs ignore it.

---

**Q8.** `checkCanInvoice()` can only ever hold one refusal. It exists because:

- A) Symmetry is good design.
- B) The caller is a screen with two buttons, and it should not have to know that one is answered by
  a Decision and the other by catching an exception.
- C) A second rule is already planned.
- D) The checks require it.

---

**Q9.** `addLine()` does **not** get a `checkCanAddLine()` because:

- A) It has too many rules.
- B) Nobody asks whether they may add a line — they add it, and are told if the job card is
  invoiced.
- C) Lines are value objects.
- D) It would break regression.

---

**Q10.** Drift, in this pattern, means:

- A) Two aggregates disagreeing.
- B) The check and the operation encoding the same rules separately, until somebody adds a guard to
  one of them.
- C) The context going stale.
- D) Status codes changing.

---

**Q11.** Drift hurts the *careful* caller more than the careless one because:

- A) Careful callers run more often.
- B) The careful caller has an `if` where the careless one has a `catch`, so the escaping exception
  goes further and becomes a 500.
- C) Careful callers use stricter types.
- D) It does not; it hurts both equally.

---

**Q12.** The fix for drift in the reference implementation is:

- A) A code review checklist.
- B) `complete()` written as `$this->checkCanComplete()->orThrow();` — one list of guards, two ways
  of reading it.
- C) A comment on both methods.
- D) Deleting the check.

---

**Q13.** `Refusal` holds the exception rather than a hand-written code and message because:

- A) Exceptions are cheaper to construct.
- B) Otherwise the code and message are written twice, which is the drift again one level down.
- C) `raise()` requires it.
- D) It makes `Refusal` throwable.

---

**Q14.** The cost of that decision is:

- A) Memory.
- B) One captured stack trace per reason on the refusal path — nothing on the happy path.
- C) A circular dependency.
- D) Losing the failure code.

---

**Q15.** The course does not build a general `Result<T, E>` because:

- A) It is bad practice.
- B) PHP has no generics, and `Decision` answers one question whose "yes" case carries no value —
  which collapses the problem to thirty lines.
- C) Nobody uses them.
- D) It would need a library.

---

## Section B — Short answer

**Q16.** `Decision::refusedBecause()` is declared `(Refusal $first, Refusal ...$rest)` rather than
`(Refusal ...$refusals)`. Explain what the second version would allow, and why that state is the one
this class must not be able to reach.

---

**Q17.** In `checkCanComplete()` the outstanding-work refusal is listed before the illegal-transition
one. State two separate consequences of that ordering, and what would break if it were reversed.

---

**Q18.** The drift test in `checks.php` runs both paths over all seven reachable states. In the
reference implementation it cannot fail. Justify keeping it.

---

**Q19.** A report calls `checkCanComplete()` on ten thousand job cards to count how many are
blocked. Describe the cost, then give the fix — and say why the fix is not a change to `Refusal`.

---

**Q20.** Give one failure in this domain that should be handled by *neither* an exception nor a
`Decision`, and say what should handle it instead.

---
---

# Answer key

**Q1 — B.**

**Q2 — B.** Note this is a third distinction, not a restatement of 4.1's or 4.2's. 4.1 separates
expected from exceptional; 4.2 separates two kinds of expected; this one is about the *caller*
rather than the failure.

**Q3 — B.**

**Q4 — C.**

**Q5 — B.**

**Q6 — B.** It is also a warning rather than an error, so a codebase that already prints warnings has
hidden it among them. Both gaps are closable — an unused-variable rule, and a build that fails on
warnings — and neither is automatic.

**Q7 — B.** Worth being precise about what the return type does buy: the *shape* of the answer, not
which failures are possible. `Decision` does not say which codes it might carry, and getting that
would need a sealed union PHP does not have.

**Q8 — B.**

**Q9 — B.**

**Q10 — B.**

**Q11 — B.** This is what makes drift the failure *mode* of the pattern rather than merely a bug in
it: checking made things worse.

**Q12 — B.**

**Q13 — B.**

**Q14 — B.**

**Q15 — B.** And it cuts both ways: if you genuinely need `Result<T, E>` — a parser, a pipeline of
fallible transformations — reach for a library rather than hand-rolling one for four use cases.

**Q16.** A bare variadic permits `Decision::refusedBecause()` with no arguments, producing a decision
that is nominally refused and holds no reasons.

That state is unreachable by design because `isAllowed()` is implemented as `$this->refusals === []`,
so such an object would report itself as **allowed** — a Decision that says yes when the code that
built it meant no. Requiring a first parameter makes the compiler prevent it, rather than a
constructor guard that throws at runtime.

**Q17.** Two consequences:

1. **It decides what `complete()` throws.** `orThrow()` throws the first refusal, so a job card in
   Awaiting Authorisation with an outstanding item raises `OutstandingAuthorisations` and not
   `IllegalStatusTransition`.
2. **It decides what a client shows first**, since `codes()` and `refusals` are ordered.

Reversing it breaks Lesson 4.2's check that `complete()` raises `OutstandingAuthorisations`, which
runs in the regression sweep — so it fails loudly. It would also silently change the message a user
sees from one describing the workshop to one describing the state machine.

The deeper point: statement order inside a method used to be an implementation detail, and this
pattern makes it observable. That is a real cost, and the kind that bites during a tidy-up.

**Q18.** Because "there is only one list of guards" is a property of *today's implementation*, not of
the design. Someone can add an `if` above the `orThrow()` next week — in response to a bug report,
in a hurry, without reading the check method — and every argument in this lesson evaporates silently.

The test is what notices. It is also documentation: it states the invariant the design depends on in
a form that runs.

**Q19.** Roughly fifteen thousand exceptions constructed and discarded — one per reason, backtrace
included — to produce a single integer.

The fix is a **read model** (Lesson 3.3): ask the database how many rows are in a blocked state and
never load an aggregate at all. Orders of magnitude faster, and it was the correct design before the
exceptions were an issue.

It is not a change to `Refusal` because removing the held exception would reintroduce drift —
trading a correctness property for milliseconds. When a domain pattern is too slow in bulk, the
question to ask is whether the bulk operation should have been touching the domain.

**Q20.** Several good answers:

- **`Money::of(-1)`** — a `PositiveAmount` value object would make the invalid state
  unrepresentable, so the question never arises. Lesson 1.2, and it beats both mechanisms.
- **`JobCards::contains()`** — returns `bool`. One obvious reason, no explanation needed; a
  `Decision` here is ceremony. Rule 6.
- **A `TypeError` in the mapper** — category (c) from Lesson 4.1. Not a `Decision`, not caught, and
  loud.

Credit for naming the mechanism, not just the failure.

---

## Scoring

| Score | Verdict |
|-------|---------|
| 18–20 | Go to 4.4. |
| 15–17 | Pass. Re-read section 5 — the "which operations" judgement is the transferable part. |
| 11–14 | Run example 02, then retake. |
| 0–10 | Re-read the lesson, starting at section 3. |
