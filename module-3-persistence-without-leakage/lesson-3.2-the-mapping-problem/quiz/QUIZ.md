# Quiz — Lesson 3.2: The Mapping Problem
> Closed book. **20 questions. Threshold: 15.**

---

## Section A — Multiple choice

**Q1.** The mapping problem exists because:

- A) ORMs are badly designed.
- B) Every defence a rich aggregate has — private constructor, guarded transitions, minted
  identities — blocks rebuilding one from a row.
- C) SQL cannot represent objects.
- D) Value objects cannot be serialised.

---

**Q2.** Loading a `JobCard` through `open()` is wrong because:

- A) It is slower.
- B) It records `JobCardOpened`, so every read announces a booking that happened months ago.
- C) `open()` is private.
- D) It does not accept a status.

---

**Q3.** Loading a line through `LabourLine::create()` is wrong because:

- A) It validates the description.
- B) It mints a new `LineId`, so `removeLine()` works within a request and never across one.
- C) It is not `readonly`.
- D) It requires a `LabourUnits`.

---

**Q4.** Why is that bug particularly dangerous?

- A) It corrupts the database.
- B) It works within a single request, which is exactly what a unit test exercises.
- C) It only affects part lines.
- D) It throws no exception ever.

---

**Q5.** `JobCard::reconstitute()` sets the status directly rather than replaying transitions because:

- A) Replaying is slow.
- B) I9 governs *changes*, and loading is not a change — the object was invoiced last Tuesday.
- C) The transition table is private.
- D) Some statuses have no transitions.

---

**Q6.** `reconstitute()` records no events. What makes that possible without a flag?

- A) A check inside `recordThat()`.
- B) The constructor does not record; only `open()` does — decided in Lesson 2.3.
- C) Events are cleared afterwards.
- D) `reconstitute()` calls `releaseEvents()`.

---

**Q7.** `reconstitute()` skips the aggregate's guards but still builds `Money` through `Money::of()`.
The principle is:

- A) Value objects are cheaper to validate.
- B) Reconstitution relaxes the rules of the aggregate, not of everything it holds.
- C) `Money` has no private constructor.
- D) Guards on values run at a different layer.

---

**Q8.** The main cost of the `reconstitute()` approach is:

- A) Performance.
- B) It is public, and PHP has no friend classes — anything can call it.
- C) It cannot handle collections.
- D) It duplicates the constructor.

---

**Q9.** The main cost of the reflection approach is:

- A) Speed.
- B) The mapper couples to private property names, and a rename breaks it at runtime in production.
- C) It cannot set readonly properties.
- D) It requires an ORM.

---

**Q10.** The `kind` discriminator and the `instanceof` that produces it belong in the mapper because:

- A) `instanceof` is bad practice in domain code.
- B) Turning an object back into a row legitimately needs to know its class; `JobCard` does not.
- C) The `Line` interface is sealed.
- D) It would be slower in the domain.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 11 | Adding public setters is an acceptable compromise if the ORM requires it. | |
| 12 | A reconstituted `Invoiced` job card should still refuse to be cancelled. | |
| 13 | Half the columns on a line row being null is the same problem as Lesson 0.2's shared model. | |
| 14 | A mapper should take a `PDOStatement` so it can stream large result sets. | |
| 15 | An unknown `kind` in a row should be skipped so one bad row does not break a load. | |

---

## Section C — Short answer

**Q16.** `reconstitute()` is public and anything can call it. Write the code review comment you would
leave on a pull request that called it from a controller.

*Your answer:*

---

**Q17.** Somebody proposes storing the job card's total in its row so reports need not load the
lines. Argue against — then give the one circumstance in which they are right.

*Your answer:*

---

**Q18.** A third line type is added: a sublet charge from an outside specialist. List every file that
must change, and say whether that number is acceptable.

*Your answer:*

---

**Q19.** Event sourcing rebuilds an aggregate by replaying its events — which is exactly what Q5 says
not to do. Reconcile those.

*Your answer:*

---

**Q20.** `pendingAuthorisations` is JSON in a column. Name the request that forces it into its own
table, and say why that request is the one that changes the answer.

*Your answer:*

---
---

<details>
<summary><strong>Answer key</strong></summary>

**Q1 — B.** Not a flaw in ORMs or SQL — the consequence of decisions each of which was right. The
public API cannot say "this object already exists".

**Q2 — B.** Phantom events on every read, and every handler subscribed to them fires.

**Q3 — B.** New identities on every load.

**Q4 — B.** It works within one request, because the ids were minted moments ago. It fails only
*across* requests, which a unit test does not do — so the test suite is green and the feature is
broken.

**Q5 — B.** The invariant is about changes; loading is the same object arriving from a different
place. Note the rules still apply *from the moment it loads*, which the challenge tests.

**Q6 — B.** The constructor does not record. That was a deliberate decision in 2.3 — "opening is a
business event; loading is not" — and this is where it pays off. A flag on an aggregate saying "do
not announce things right now" is the alternative, and it would eventually be left on.

**Q7 — B.** The value objects' guards are cheap, total, and true of every value that has ever
existed. The aggregate's guards are about *creation*, and this object was not created here.

**Q8 — B.** A public hole. The mitigations are a name, a doc block and a review — that is the entire
list.

**Q9 — B.** Coupled to the most private thing a class has, via a string, discovered in production on
load. Note C is wrong on 8.3+ for an uninitialised property, which is why
`newInstanceWithoutConstructor()` is part of the technique.

**Q10 — B.** Serialisation needs the class; the domain does not. Same construct, opposite verdicts,
and the difference is which layer it is in.

**Q11 — FALSE.** It undoes every invariant in the model, permanently, for every caller. It is also
what most codebases actually do, which is why the lesson names it rather than assuming nobody would.

**Q12 — TRUE.** The past is not replayed; the rules apply from now on. `Invoiced` is terminal, and
that is true of a job card however it arrived in memory.

**Q13 — FALSE**, and the reason matters. The tell warns about a *model* whose populated fields depend
on where the object came from. A line row is a wire format — never handed to anything, and the
aggregate it produces has no nullable fields at all. Storage is allowed to be ugly as long as the
ugliness stops at the mapper.

**Q14 — FALSE.** Mapping is the part worth testing exhaustively, and a mapper that takes a statement
needs a database for every one of those tests. Streaming is the repository's problem.

**Q15 — FALSE.** A skipped line is a missing charge, so the job card's total is silently wrong and
the invoice goes out. An unknown `kind` means the schema has moved and the mapper has not — which is
a deployment problem, and deployment problems should be loud.

**Q16** Something like:

> `JobCard::reconstitute()` builds an aggregate with no checks at all — it can produce an invoiced
> job card with no lines. It exists for `JobCardMapper` and nothing else, and there is no language
> feature that can enforce that, so this review is the enforcement.
>
> What are you actually trying to do? If you need a job card in a particular state for a test, add a
> factory in the test suite that builds it through the public API. If you need one loaded, use the
> repository.

The useful part is the last paragraph. A review comment that only says "don't" gets argued with; one
that asks what the person needed usually finds that the public API already does it.

**Q17** *Against:* it is two facts that can disagree — Lesson 2.1 §6. The lines are the truth, and a
stored total is a cache with no invalidation. Every path that adds or removes a line has to update
it, and the day one does not, an invoice is wrong and nothing fails. Deriving it costs one loop over
a handful of lines.

*Where they are right:* when the calculation itself becomes historical. If tax treatment or rounding
changes, a derived total silently re-prices last year's invoices — and an invoice is a statement of
what was charged. At that point the computed figure must be stored **as a separate, clearly named
thing** (`invoiced_total`), not as a cache of the derivation. Note the course already does a version
of this: `JobCardInvoiced` carries the total, captured at the moment of billing.

The distinction is between storing a total *because reads are slow* — a cache, and usually wrong —
and storing one *because it is a different fact*.

**Q18** `SubletLine` (new domain class), `JobCardMapper::toLineRow()` (a third `instanceof` branch),
`JobCardMapper::lineFromRow()` (a third `match` arm), the schema (new nullable columns, or a new
table), and 3.4's contract test.

Five places, three of which are in one file. **Acceptable**, and worth saying why: the two mapper
changes are in adjacent methods, and the `default` arms mean forgetting either one throws loudly
rather than silently dropping charges. The `instanceof` chain is not elegant and it is honest — it
fails at the moment a type is unhandled rather than at the moment somebody reads an invoice.

The counter-argument is the ORM one: Doctrine's single-table inheritance would make this a mapping
annotation and no code. That is genuinely less work, and it buys the coupling and the collection
problems from §7. At three line types, hand-rolled still wins; at fifteen it does not.

**Q19** Event sourcing replays events to rebuild state and does **not** replay the guarded
transitions. The two operations are separate by design: `invoice()` checks the rules and records
`JobCardInvoiced`; `applyJobCardInvoiced()` sets the status and checks nothing.

That separation is the same distinction as `create()` versus `reconstitute()`, taken to its logical
end — and it is why event-sourced aggregates have `apply` methods that look pointless until you
notice they are the reconstitution path. What Q5 rules out is replaying the *guarded* methods, which
would re-run the rules and re-record the events. Replaying facts is not that.

**Q20** *"Show me every outstanding authorisation across the workshop, oldest first."*

That is a query **across** job cards, and JSON in a column cannot answer it without loading every
job card and decoding each one — which is exactly what a read model exists to avoid.

Why this request and not others: JSON is acceptable for something you only ever read back *as a
whole, with the aggregate that owns it*. The moment anything needs to query, sort, filter or join on
the contents, the column has become a table with extra steps. Note the request also has a name a
service manager would use — "the chase list" — which is the tell from Lesson 3.1 that this may be a
concept in its own right rather than a field on something else.

</details>

---

**Score:** ____ / 20   ·   **Threshold: 15**
