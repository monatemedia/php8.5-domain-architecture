# Quiz — Lesson 5.1: When the Framework Owns Your Models
> Closed book. **20 questions. Threshold: 15.**

---

## Section A — Multiple choice

**Q1.** An Active Record model is hard to put rules on because:

- A) It is slow.
- B) It is a row, with a public setter for every column, that knows how to save itself — so it can be
  built in any state, any rule can be routed around, and none of it works without a database.
- C) It uses inheritance.
- D) It cannot be typed.

---

**Q2.** The ladder's rungs, in increasing cost, are:

- A) Repository, mapper, aggregate, events.
- B) Named methods → invariants in the model → value objects over columns → typed exceptions →
  a separate domain layer.
- C) Tests, types, interfaces, containers.
- D) Value objects, entities, aggregates, bounded contexts.

---

**Q3.** Rung 1 — named methods instead of public setters — is worth doing first because:

- A) It is the most impressive.
- B) It costs nothing, gives the operation a name the business uses, creates a place for the rule to
  live, and makes the call sites greppable.
- C) It removes the ORM.
- D) It is required by rung 2.

---

**Q4.** Taking over `__set` to guard columns gives you:

- A) A compile-time guarantee.
- B) A runtime check — a `LogicException` at 3am rather than an error at build time, which is the
  cost of the framework owning the model.
- C) Nothing useful.
- D) Type safety.

---

**Q5.** Rung 3 — value objects over existing columns — requires:

- A) A schema migration.
- B) No schema change at all: the columns stay as they are, and an accessor or cast builds the object.
- C) Dropping the ORM.
- D) A separate table.

---

**Q6.** Module 4 — the exception tree and the boundary mapper — transfers into a legacy application:

- A) Only after rung 5.
- B) Wholesale, with no persistence changes, because it has no opinion about persistence.
- C) Not at all.
- D) Only for Doctrine.

---

**Q7.** `ProblemDetailsMapper` needs no new entry for a legacy record because:

- A) It catches `\Throwable`.
- B) It keys on `failureCode()` and knows nothing about where the exception was thrown.
- C) Legacy records are exempt.
- D) It uses reflection.

---

**Q8.** Rung 5 — a separate domain layer with a mapper — buys:

- A) Faster queries.
- B) Rules testable without a database, and a domain that survives replacing the ORM.
- C) Better types.
- D) Smaller classes.

---

**Q9.** Rung 5's costs include all of the following EXCEPT:

- A) Two models of the same thing, maintained together.
- B) A mapper that every new column has to be added to.
- C) A schema migration.
- D) The framework fighting you — lazy loading, model events, mass assignment.

---

**Q10.** A signal that rung 5 is worth paying for is:

- A) The codebase is large.
- B) A mapper already exists, written by hand, spread across services as `toArray()` and
  `fromRequest()`.
- C) The application handles money.
- D) The team wants to learn DDD.

---

**Q11.** "It handles money" is misleading as a signal because:

- A) Money is easy.
- B) It is a reason for value objects — rung 3, which costs almost nothing — not by itself a reason
  for a separate domain layer.
- C) Money should be a float.
- D) It only matters for reporting.

---

**Q12.** In a Doctrine codebase, rung 5 is often unnecessary because:

- A) Doctrine is slower.
- B) The entity is a plain object with private properties and no `save()` — already most of an
  aggregate.
- C) Doctrine has no repositories.
- D) Attributes prevent it.

---

**Q13.** The cost that remains in the Doctrine case is:

- A) A second model.
- B) ORM attributes on domain classes, and an ORM `Collection` type inside the aggregate.
- C) Magic attribute access.
- D) Static finders.

---

**Q14.** Module 2's aggregate boundaries transfer badly because:

- A) They are wrong.
- B) `$jobCard->vehicle->registration` is one property access the whole team already writes, and
  forbidding it buys guarantees the application was not making anyway.
- C) ORMs cannot express relations.
- D) They need events.

---

**Q15.** The capstone's "zero infrastructural imports in `domain/`" test is:

- A) A standard every application should meet.
- B) An ideal for a codebase built that way from the start — worth reasoning with, not a bar to hold
  an existing application to.
- C) Impossible.
- D) Only about Slim.

---

## Section B — Short answer

**Q16.** Storing the status as the enum's **backed value** rather than as an integer or a case name
has a property that matters more in a legacy system than in a new one. Name it and say why.

---

**Q17.** The lesson says to put the rule on the model and leave the three copies in the services
where they are, deleting them as you touch them. Defend that against the objection that you now have
four copies of one rule.

---

**Q18.** A colleague proposes going straight to rung 5 in an application with no test suite. Give
your answer and the alternative.

---

**Q19.** `JobCardRecord` is a second model of the job card, in a course that spent Module 1 arguing
against exactly that. Reconcile the two positions.

---

**Q20.** Name the one thing you would take from this course back to a legacy application on Monday
morning, and justify the choice in terms of cost and value.

---
---

# Answer key

**Q1 — B.** Three things wearing one class, causing three separate problems.

**Q2 — B.**

**Q3 — B.** The greppability point is the underrated one: `grep -rn '->complete()'` finds every place
work is finished; `grep -rn "status = "` finds nothing useful, ever.

**Q4 — B.** PHP will not give you the compile error, because a magic setter is magic. That cost does
not go away until rung 5, and pretending otherwise is how these lessons get oversold.

**Q5 — B.** Which is why it has the best ratio of bugs-prevented to diff-size in the ladder.

**Q6 — B.**

**Q7 — B.** Lesson 4.2 argued for a declared failure code before there was anything reading it. This
is what reads it, and the payoff arrives two modules later in a place nobody was designing for.

**Q8 — B.**

**Q9 — C.** Rung 5 needs no schema change — that is not one of its costs. The other three are real
and the third is the one people underestimate.

**Q10 — B.** At that point rung 5 is not new cost; it is consolidating cost you are already paying
badly.

**Q11 — B.**

**Q12 — B.**

**Q13 — B.** Both are infrastructural things in a domain file, and both are small enough to be worth
it — which is why the advice differs by stack even though the decision does not.

**Q14 — B.** You can hold the line on new code. You will not retrofit it, and a lesson that tells you
to is sending you to pick a fight you cannot win.

**Q15 — B.**

**Q16.** It is **reversible**. A row written today is readable by every piece of code that never
heard of the enum — an old report, a scheduled job, a query somebody runs by hand — and a row written
last year loads without a migration.

That matters more in a legacy system because a change you cannot roll back is a change nobody will
approve. In a new codebase you can deploy an irreversible representation and fix the fallout; in a
90k-line application with three teams touching it, "we can turn this off on Friday if it goes wrong"
is often the only thing that gets the change merged.

**Q17.** Because a duplicated *correct* rule is not a bug, and an *un-enforced* rule is.

The alternative is finding all three copies first, which means the change does not land until you
have read every service — and it will be five copies, not three, and one of them will be in a console
command nobody remembers. That is how these efforts stall.

Put the rule where it cannot be skipped, and the service copies become dead weight that is safe to
remove opportunistically. The intermediate state is strictly better than the one you started in,
which is the property that lets you stop at any point.

**Q18.** Do not. Rung 5 without characterisation tests is a rewrite wearing a plan: you will change
behaviour and not know it, and the first person to notice will be a customer.

The alternative is rungs 1–3, which are small enough to review by eye and each of which forces you to
write down what the current behaviour actually is. Those tests are what make rung 5 possible later.
Lesson 5.2 is entirely about that path.

**Q19.** They are answers to different questions.

Module 1 argued against an **anemic** model — a class of getters and setters with the logic in a
service — and `JobCardRecord` is not that. It has named operations, enforces its own invariant, and
refuses invalid state. It is a rich model that happens to live in `infrastructure/` because it also
knows how to save itself.

What Module 1 warned about is having *two anemic* models, or a rich domain model and a rich ORM model
that disagree. This challenge deliberately builds only one.

In a real codebase where both existed, the answer is to pick which is authoritative and make the
other a shell — usually the domain one, with the record demoted to a row — and to do it one aggregate
at a time rather than declaring it.

**Q20.** The expected answer is **Module 4**: an exception tree and a boundary mapper.

Value: 500s become 409s and 422s, clients can act on failures, and the operator gets a stack trace
the user never sees. Cost: no schema change, no persistence change, no rewrite — it works from an
Active Record model, a Data Mapper entity or a raw array, because it has no opinion about any of
them.

Rung 3 — value objects for money — is a defensible alternative answer and gets full credit if the
cost/value argument is made. "Rung 5" gets no credit without a Module 0 signal named explicitly.

---

## Scoring

| Score | Verdict |
|-------|---------|
| 18–20 | Go to 5.2. |
| 15–17 | Pass. Re-read section 3 — the ladder is the transferable part. |
| 11–14 | Run examples 01 and 03, then retake. |
| 0–10 | Re-read the lesson, starting at section 3. |
