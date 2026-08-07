# Quiz — Lesson 5.2: Strangling a Legacy Model
> Closed book. **20 questions. Threshold: 15.**

---

## Section A — Multiple choice

**Q1.** A strangler differs from a rewrite in that:

- A) It is faster.
- B) At every point you can stop and still be better off than when you started.
- C) It needs no tests.
- D) It does not change behaviour.

---

**Q2.** Rewrites typically fail because:

- A) The new code is worse.
- B) The last 20% of features is where all the business rules turned out to live.
- C) The framework changes.
- D) They are too expensive.

---

**Q3.** The right first extraction is:

- A) The largest table.
- B) The invariant that is generating incidents — the one somebody will let you spend a week on.
- C) The most technically interesting aggregate.
- D) Whatever has the best test coverage.

---

**Q4.** A rule about a single field is usually:

- A) An aggregate.
- B) A value object — cheaper, and Lesson 1.2 already covered it.
- C) A domain service.
- D) Not worth modelling.

---

**Q5.** The new service must have the same signature as the one it replaces because:

- A) It is neater.
- B) Otherwise every call site changes in the same pull request, and a two-day change becomes a
  six-week branch.
- C) PHP requires it.
- D) The container cannot resolve it otherwise.

---

**Q6.** `PartStock::of()` does not enforce the invariant because:

- A) It is a performance optimisation.
- B) Legacy data is not clean, and an extraction that cannot load the data is not an extraction —
  the first bad row would take down the screen that was going to count them.
- C) The invariant is enforced elsewhere.
- D) Factories never validate.

---

**Q7.** `of()` **does** refuse a negative count because:

- A) Consistency.
- B) Inconsistent means two fields disagree; impossible means one field is nonsense. A shelf holding
  minus four is a bug to find, not dirt to tolerate.
- C) Negative numbers break the arithmetic.
- D) The column is unsigned.

---

**Q8.** `isConsistent()` is:

- A) A guard called before every operation.
- B) A report — run across the table before touching a call site, to find out how much bad data
  exists.
- C) A test helper.
- D) Required by the aggregate pattern.

---

**Q9.** A characterisation test records:

- A) What the code was supposed to do.
- B) What the old code actually does, over the same inputs, before you replace it.
- C) The new behaviour.
- D) Performance.

---

**Q10.** Naming the divergences explicitly matters because:

- A) It is good documentation.
- B) "We found some bugs" is not a decision; "we changed behaviour in these three cases" is, and it
  is what somebody signs off.
- C) It helps the compiler.
- D) The RFC requires it.

---

**Q11.** Shipping the behaviour fixes in separate commits from the extraction buys you:

- A) A shorter diff.
- B) The ability to tell, in minutes, whether an incident came from the refactor or from a rule
  change.
- C) Faster review.
- D) Nothing; it is style.

---

**Q12.** `PickingService` is left broken because:

- A) It is not important.
- B) A strangler that grows to three services in its first week is a rewrite that has not admitted
  it yet — and it is now trivially convertible later.
- C) It has no tests.
- D) It is used elsewhere.

---

**Q13.** `StockReportService` is left alone for a different reason:

- A) It is faster.
- B) It is a read model wearing a service's name — it decides nothing, and routing it through an
  aggregate would load an object to do arithmetic that is already correct.
- C) It is deprecated.
- D) It does not touch the invariant.

---

**Q14.** `InsufficientStock` extends `WorkshopException` and `InvalidQuantity` extends
`InvalidWorkshopInput` because:

- A) Alphabetical order.
- B) Whether six alternators are enough depends on the shelf; whether minus five is a quantity does
  not.
- C) One is thrown more often.
- D) They map to different classes.

---

**Q15.** No `StockException` node was added because:

- A) The tree is too deep.
- B) With one class below it there is nothing to hoist and no clause anybody writes — 4.2's rule.
- C) `WorkshopException` is already abstract.
- D) It would break the mapper.

---

## Section B — Short answer

**Q16.** The aggregate takes an `int` quantity even though a `Quantity` value object would be
strictly better. Explain the trade-off and say when you would make the change.

---

**Q17.** `isConsistent()` reports 40,000 bad rows out of 480,000. Describe what you would do next, and
what that number is most likely telling you.

---

**Q18.** After this lesson, the invariant can still be broken. Say how, and explain why the extraction
is nonetheless worth having.

---

**Q19.** Lesson 4.4 wrote an exhaustiveness check asserting every leaf of the exception tree maps to
something other than 500. Describe what that check did in *this* lesson, and what would have happened
without it.

---

**Q20.** Somebody asks how long until the whole application is modelled this way. Answer honestly.

---
---

# Answer key

**Q1 — B.** And that is also why it gets approved: "we will be better off in three weeks and again in
six" is a proposal; "we will be better off in eighteen months" is a request for faith.

**Q2 — B.**

**Q3 — B.** An extraction nobody asked for competes with feature work and loses. One that closes a
class of incident has a sponsor.

**Q4 — B.**

**Q5 — B.** The property most often given up first, and giving it up is what kills the extraction.

**Q6 — B.**

**Q7 — B.**

**Q8 — B.** Most failed extractions fail here, and they fail *late* — after the new path is written,
when it starts throwing on data that has been fine for six years.

**Q9 — B.** Note "actually does", not "was supposed to do" and not "what the ticket says".

**Q10 — B.**

**Q11 — B.** A behaviour change shipped inside a refactor is how a refactor gets blamed for an outage.

**Q12 — B.** The reward for stopping: `PartStock::consume()` already exists and already refuses, so
somebody converts it in an afternoon next quarter — or never, and the codebase is still better.

**Q13 — B.**

**Q14 — B.** And it lands as 409 versus 422 at the boundary, which matters to a client that automates
retries.

**Q15 — B.**

**Q16.** The trade-off: a `Quantity` value object makes the invalid case *unrepresentable* rather than
merely refused, which is better — but it changes the signature, which means every legacy call site
must change in the same pull request. That is what turns a two-day extraction into a six-week branch
that gets abandoned.

Make the change once the call sites are yours: after the strangler has replaced the old service
everywhere and the callers are code you are willing to touch. It is then a mechanical change with the
characterisation tests already in place.

It is a real cost of working this way, and worth stating rather than pretending the strangler is free.

**Q17.** First, do not write more code. Second, break the number down — are they negative reserves, or
reserved-exceeding-on-hand, and are they clustered by part type, branch or date? Third, take it to
somebody who knows the parts department.

What 40,000 is most likely telling you: **the rule is not the rule.** Eleven bad rows is six years of
a missing floor check. Forty thousand is the business operating a model you have not been told about
— parts on order counted as reserved, a supplier consignment arrangement, a branch that shares stock.

Enforce your version on top of that and you will be correct, and wrong.

**Q18.** Through `PickingService::fit()`, which is untouched and enforces nothing — it will still
drive `on_hand` negative.

It is worth having anyway because the reservation path — the one generating the incidents — is now
closed; the number of places that can break the rule went from two to one, and the remaining one is
known, named and easy to convert because `PartStock::consume()` already exists. The codebase is
strictly better than it was, and it got there in a change small enough to review.

The alternative was fixing both at once, which is twice the diff, twice the risk, and the point at
which the work stops being reviewable and starts being a branch.

**Q19.** It failed, and that is what it did. Two new leaves arrived from a legacy extraction two
modules after the mapper was written, and the check walks every leaf asserting each maps to something
other than 500 — so it failed the build until `insufficient-stock` and `invalid-quantity` were added
to the catalogue.

Without it: both would have become **opaque 500s**. A client asking to reserve seven alternators when
six are free would have been told the server was broken, and nothing anywhere would have failed. It
would have been found by a support ticket, weeks later, and probably blamed on the deployment rather
than on a missing table entry.

That is precisely the drift the objection in Lesson 4.2's quiz predicted, arriving on schedule and
being caught.

**Q20.** Honestly: probably never, and "done" is the wrong target.

Some parts of the application will never justify it — the CRUD screens, the reporting, the admin
tools. Module 0 said so before any of this was built, and Lesson 5.1 costed it out. A codebase where
30% is modelled properly and 70% is a well-behaved Active Record layer is not a failure; it is the
correct outcome, and the 30% is the part where incorrect state is expensive.

What you can promise is a rate: one aggregate a quarter, each one closing a class of incident, each
one shipped and stopped. Anyone who wants a completion date is asking about a rewrite.

---

## Scoring

| Score | Verdict |
|-------|---------|
| 18–20 | Module 5 complete. Take Gate 5 in `GATES.md`. |
| 15–17 | Pass. Re-read sections 5 and 6 before the gate. |
| 11–14 | Run examples 02 and 03, then retake. |
| 0–10 | Re-read the lesson. Section 6 is the transferable part. |
