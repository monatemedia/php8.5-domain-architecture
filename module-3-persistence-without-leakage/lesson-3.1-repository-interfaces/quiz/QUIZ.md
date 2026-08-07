# Quiz — Lesson 3.1: Repository Interfaces Belong to the Domain
> Closed book. **20 questions. Threshold: 15.**

---

## Section A — Multiple choice

**Q1.** The interface lives in the domain and the implementation outside because:

- A) It is a common convention.
- B) The domain declares what it needs in its own words; infrastructure supplies it, so the arrow
  points inward.
- C) Interfaces cannot be in the same directory as classes.
- D) It makes the implementation easier to swap.

---

**Q2.** An interface with `findBy(array $criteria)` has:

- A) A flexible API.
- B) A query language wearing a method name, so the storage model is now in every caller's head.
- C) Poor type safety.
- D) A performance problem.

---

**Q3.** A repository returning `array` rows is:

- A) Efficient.
- B) A data access object with a better name — every caller re-implements hydration, and one gets it
  wrong.
- C) Fine if the arrays are typed with PHPDoc.
- D) Required for reporting.

---

**Q4.** A repository returning **DTOs** instead of aggregates is:

- A) The correct pattern.
- B) Better than rows, and still an anemic model — the rules remain outside.
- C) Identical to returning aggregates.
- D) Only acceptable for read models.

---

**Q5.** `add()`/`remove()` rather than `save()`/`delete()` is:

- A) A naming preference.
- B) Collection-oriented — it keeps storage out of the caller's vocabulary, at a cost paid in 3.2.
- C) Required by the interface segregation principle.
- D) Slower.

---

**Q6.** The cost of the collection-oriented shape is:

- A) More methods.
- B) An implementation backed by a database has no way to know an aggregate changed.
- C) It cannot express deletion.
- D) It requires an ORM.

---

**Q7.** The classical solution to that is:

- A) An explicit `save()`.
- B) A unit of work — tracking loaded aggregates and writing what changed.
- C) Making aggregates immutable.
- D) Database triggers.

---

**Q8.** `get()` throws and `contains()` answers, because:

- A) `get()` is faster.
- B) They mean different things: "I know this exists" versus "does this exist?".
- C) Exceptions are always preferable to null.
- D) `contains()` is for tests.

---

**Q9.** `JobCardNotFound extends \DomainException` rather than `RuntimeException` because:

- A) `DomainException` is more specific.
- B) A missing job card is a business fact, not a crash — and the same exception is right whatever
  the storage is.
- C) `RuntimeException` cannot carry data.
- D) It will be caught at the API boundary.

---

**Q10.** There is no `LinesRepository` because:

- A) Lines are value objects.
- B) A line has no independent existence — it is reached, changed and saved through the job card.
- C) There are too few of them.
- D) They are stored in the same table.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 11 | `domain/workshop/JobCards.php` should import `Infrastructure\InMemoryJobCards` in a docblock example. | |
| 12 | An in-memory repository is a test double and need not match the real one closely. | |
| 13 | One repository per aggregate root, not per entity. | |
| 14 | Putting the interface in an infrastructure package still counts as dependency inversion. | |
| 15 | Every query the domain needs should be a named method somebody can read aloud. | |

---

## Section C — Short answer

**Q16.** `InMemoryJobCards` stores references, so a job card changed after `add()` needs no saving.
Describe what a SQLite implementation must do instead, and what this interface fails to give it.

*Your answer:*

---

**Q17.** Somebody asks for "job cards over 500 that are still open, for fleet accounts only". You
cannot use `findBy()`. Name the method — or argue it does not belong on this interface.

*Your answer:*

---

**Q18.** Example 03 has three callers deciding "is this invoiceable" three different ways, and the
totals all agree. Why is the agreement the dangerous part?

*Your answer:*

---

**Q19.** A colleague puts the `JobCards` interface in `infrastructure/` alongside the
implementations, arguing that interfaces belong with their implementations. Everything still
compiles and the tests are fast. What exactly has been lost?

*Your answer:*

---

**Q20.** Name one query you would expect a real workshop to ask for that should **not** be on this
interface, and say where it goes instead.

*Your answer:*

---
---

<details>
<summary><strong>Answer key</strong></summary>

**Q1 — B.** The direction of the dependency. D is a consequence rather than the reason — swappability
follows from the inversion, and chasing it directly is how people end up with an interface in the
wrong package.

**Q2 — B.** Every caller now writes queries, so the storage model is in the caller's head. One such
method is enough, and once forty call sites use it nobody will put it back.

**Q3 — B.** A DAO with a better name.

**Q4 — B.** A DTO looks like progress — typed, named, no arrays — and it moves nothing. The rules are
still outside, so it is Lesson 1.1's anemic model with a delivery mechanism.

**Q5 — B.** And note the cost is real and is met in 3.2 rather than designed around.

**Q6 — B.** An in-memory implementation holds references, so changes are free. A database-backed one
has no way to find out, and the interface gives it nowhere to ask.

**Q7 — B.** A unit of work. (A is what most PHP codebases actually do, honestly, and it moves one
word of storage vocabulary back into the caller.)

**Q8 — B.** Different meanings, not indecision. A caller invoicing a job card named in a URL
generated from a list *knows* it exists — a miss there is a bug, and an exception is right. A caller
checking a typed-in reference does not.

**Q9 — B.** A business fact, and storage-independent. That storage-independence is the test for
belonging in the domain.

**Q10 — B.** A line is inside the job card's boundary. Wanting a repository for something inside a
boundary means the boundary is wrong or the repository is.

**Q11 — FALSE.** Even in a comment. The capstone's import scan would not catch a docblock, but the
point is the direction of knowledge, and a domain file that names an infrastructure class has learned
something it should not know.

**Q12 — FALSE.** It is a real implementation, and 3.4 runs the same contract test against both. A
fake that returns results in insertion order while SQL returns them arbitrarily gives you a green
suite and a broken system.

**Q13 — TRUE.**

**Q14 — FALSE.** This is the most common way it is got wrong, because everything *looks* right —
there is an interface, there is a fake, the tests are fast. The domain now needs that package to
compile, so the arrow points outward with an interface in the middle making it look inverted.

**Q15 — TRUE.** And the discomfort is the point: naming it forces the question of what it is for,
which is how two of example 02's four requests turned out not to belong on the interface at all.

**Q16** It must write. `add()` inserts a row for the job card and a row per line; `get()` runs a
query and rebuilds the aggregate. The hard part is that a job card returned by `get()` and then
changed by the caller has to end up in the database, and **nothing tells the repository that
happened** — the collection-oriented interface has no `save()`, and a database cannot notice a
mutation the way an array of references does.

What the interface fails to give it is a moment at which to write. The options are a unit of work
that tracks loaded aggregates and diffs them at the end of the request, or admitting defeat and
adding `save()` — which is what most PHP codebases do, and which puts one storage word back into the
caller's vocabulary.

There is a second problem the question does not ask about and 3.2 will: rebuilding a `JobCard` at all,
given a private constructor, `readonly` lines, `LineId`s that must survive the round trip, and an
`open()` that records an event.

**Q17** It does not belong here. Three tells: it combines a threshold, a status and an account type,
which is a *filter* rather than a concept; nobody in the workshop has a single word for it; and the
numbers in it will be adjusted by whoever is looking at the screen.

That is a **read model** — a query serving one view, returning whatever that view needs, and not an
aggregate. Lesson 3.3 is about where those live and why forcing them through the aggregate is wrong.

If it turned out the workshop *does* have a name for it — "the chase list", say — that changes the
answer, because then it is a concept and `chaseList()` is a fine method. The question to ask is not
about the SQL; it is whether anyone says it out loud.

**Q18** Because agreement makes the divergence invisible to inspection. All three callers produce
the same total, so anyone spot-checking one against another sees matching numbers and concludes the
code is consistent. The disagreement is in `isInvoiceable`, which is a boolean nobody prints beside
the total.

It also means the bug is data-dependent: the three definitions only differ for job cards that are
`invoiced` or `cancelled`, so the discrepancy appears months later on a subset of records, long after
the three callers were written and by then all three look equally authoritative.

**Q19** Compilation and test speed are exactly what is *not* lost, which is why the mistake survives
review.

What is lost is that `domain/workshop/` now requires the infrastructure package to exist. You cannot
ship the domain without it, cannot reason about it without it, and the "delete the framework" question
now has the answer "then the domain does not load". The interface is still an interface, and the
dependency arrow points outward with an interface in the middle making it look inverted.

Concretely: the capstone's import-scanning test fails, and it should. Less concretely, and more
importantly, the next person to add a method to that interface will be looking at the SQL
implementation while they do it, and the vocabulary will drift toward the database one method at a
time.

**Q20** No single key, but the strong candidates are all reporting: "job cards completed last month
by technician", "average turnaround by month", "which parts we fit most". Each serves a screen or a
report, returns numbers rather than aggregates, and would be absurd to answer by loading a thousand
job cards and summing in PHP.

They go in a **read model** or query service — Lesson 3.3. The tell is that the answer is not an
aggregate: if what comes back is a table, forcing it through a repository that promises aggregates is
the wrong shape twice over.

</details>

---

**Score:** ____ / 20   ·   **Threshold: 15**
