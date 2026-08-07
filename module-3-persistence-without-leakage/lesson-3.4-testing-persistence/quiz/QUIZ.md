# Quiz — Lesson 3.4: Testing Persistence
> Closed book. **20 questions. Threshold: 15.**
> The last quiz in Module 3. Then Gate 3.

---

## Section A — Multiple choice

**Q1.** A contract test is:

- A) An integration test.
- B) A suite that belongs to an interface rather than to any class, run against every implementation.
- C) A test written before the code.
- D) A test that uses a real database.

---

**Q2.** It is the executable form of:

- A) The single responsibility principle.
- B) The Liskov Substitution Principle.
- C) Dependency inversion.
- D) Interface segregation.

---

**Q3.** "`get()` returns the same instance you added" was asserted in 3.1 and is absent here because:

- A) It is slow to check.
- B) It is a property of one mechanism, not a promise of the interface — a database cannot satisfy it.
- C) Instances are not comparable.
- D) It duplicates the equality check.

---

**Q4.** The drift most likely to pass unnoticed is:

- A) Uniqueness.
- B) Ordering — SQLite usually returns insertion order, so both pass until an index is added.
- C) Types.
- D) Transactions.

---

**Q5.** `InMemoryJobCards::add()` guards against duplicates even though an array would just replace.
That is because:

- A) Arrays are slow.
- B) A fake more permissive than the real thing gives a green suite and a broken system.
- C) The interface requires it.
- D) It makes the code symmetrical.

---

**Q6.** The `position` column exists because:

- A) SQLite requires a sort key.
- B) `JobCard::lines()` promises insertion order, which an array gives free and a table does not.
- C) Lines need identity.
- D) It speeds up the query.

---

**Q7.** The domain does not know about `position` because:

- A) It is a performance detail.
- B) "Third on the list" is not a business concept — it is a consequence of the aggregate's promise,
  and keeping it is storage's problem.
- C) `JobCard` uses an associative array.
- D) It would break the mapper.

---

**Q8.** `sqlite::memory:` per repository buys:

- A) Speed only.
- B) Isolation — no fixtures, no cleanup, no ordering dependency between tests.
- C) Compatibility with production.
- D) Transaction support.

---

**Q9.** `add()` should refuse a duplicate before touching the database because:

- A) It is faster.
- B) A `PRIMARY KEY` violation surfaces as a `PDOException` — an infrastructure failure where the
  interface promised a domain one.
- C) SQLite does not enforce primary keys.
- D) Transactions cannot be nested.

---

**Q10.** The contract test is written not to depend on the change-tracking divergence. That is:

- A) A cheat that invalidates the suite.
- B) Correct, as long as the divergence is named rather than hidden.
- C) Unnecessary — the divergence does not exist.
- D) A sign the interface is wrong and must be changed immediately.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 11 | Each implementation should have its own test suite, tailored to how it works. | |
| 12 | A green SQLite suite guarantees the same behaviour on PostgreSQL. | |
| 13 | The contract may assert anything both implementations happen to do. | |
| 14 | `SqliteJobCards` should contain the row-to-aggregate mapping, since it owns the SQL. | |
| 15 | A test double you wrote by hand and never checked against the real thing is a known quantity. | |

---

## Section C — Short answer

**Q16.** Explain why "assert what the interface promises, never how a class keeps the promise" rules
out the same-instance assertion but requires the ordering one.

*Your answer:*

---

**Q17.** Change a job card after `add()`, then `get()` it. The implementations disagree. Whose bug is
it, and what are the three ways to resolve it?

*Your answer:*

---

**Q18.** `awaitingAuthorisation()` queries `status = 'awaiting_authorisation'`, duplicating
`JobCardStatus`'s backing value. Is that a leak? Argue both ways.

*Your answer:*

---

**Q19.** Your suite is green on SQLite; production is PostgreSQL. Name two things that could still
fail, and what it would cost to find out.

*Your answer:*

---

**Q20.** Module 3 is finished. Looking back at Modules 1 and 2 — name the single decision that cost
the most to persist, and say whether you would make it again.

*Your answer:*

---
---

<details>
<summary><strong>Answer key</strong></summary>

**Q1 — B.** It belongs to the interface. Where it runs is incidental.

**Q2 — B.** LSP says a subtype must be usable anywhere the supertype is without the caller knowing
which it has. Until you run the same assertions against both, that is a hope.

**Q3 — B.** A property of one mechanism. A database builds a new object from rows every time, so the
assertion was never part of the promise.

**Q4 — B.** Ordering, because both pass today. The failure arrives later, from an index or an
in-place update, and it reorders somebody's invoice.

**Q5 — B.** And note which direction the permissiveness runs: the fake is almost always the looser
one, so tests written against it pass and tests written against the real thing find the gap.

**Q6 — B.**

**Q7 — B.** The domain promises insertion order; how a table keeps that promise is storage's problem.

**Q8 — B.** Speed is real and secondary. The isolation is what stops order-dependent failures, which
teams usually "fix" by freezing the test order forever.

**Q9 — B.** The interface promises `\DomainException`. A `PDOException` escaping is a layer leak, and
Lesson 4.1 is about why that matters at the API boundary.

**Q10 — B.** Writing around a known divergence is legitimate; pretending it does not exist is not. A
contract test that quietly avoids the one place two implementations differ has stopped testing the
interesting thing.

**Q11 — FALSE.** They drift, and the fake is always the permissive one.

**Q12 — FALSE.** Different type affinity, collation, locking and strictness. What SQLite buys is that
mapping, ordering, constraints and transactions are exercised for real in milliseconds — a large
fraction of what goes wrong, not all of it.

**Q13 — FALSE.** Only what the interface promises. Two implementations coinciding by accident is
exactly how a mechanism-specific assertion gets in.

**Q14 — FALSE.** 3.2's split: the mapper is the part worth testing exhaustively, and it can only stay
testable without a database if the SQL lives elsewhere.

**Q15 — FALSE.** You do not know what it is more permissive about, which is the only thing that
matters about a fake.

**Q16** The rule asks what a caller is entitled to rely on.

A caller may rely on `get()` giving back a job card **equal** to the one that was added — same
identity, same status, same total, same lines in the same order. That is the promise, and both
implementations must keep it.

A caller may **not** rely on getting the same PHP object. Nothing in `JobCards` says so, and a
database physically cannot: rows come back and a new aggregate is built. Asserting it would fail
`SqliteJobCards` for doing exactly what a repository is supposed to do.

Ordering goes the other way. `JobCard::lines()` returns a list in insertion order — that *is* part of
what a caller relies on, whatever is underneath. An array satisfies it for free and a table does not,
which is precisely why it must be asserted: the promise is real and only one implementation gets it
by accident.

**Q17** **Nobody's bug — the interface's.** `JobCards` is collection-oriented, so there is no moment
at which a database-backed implementation is told to write. In-memory holds a reference and agrees by
accident; SQLite wrote rows at `add()` time and has heard nothing since.

*Three resolutions:*

1. **A unit of work.** Track loaded aggregates, compare them at the end of the request, write what
   changed. Correct, and real machinery — it has to know how to diff an aggregate.
2. **An explicit `save()`.** One method, one word of storage vocabulary back in the caller's
   vocabulary. What most PHP codebases do, and defensible.
3. **Treat aggregates as immutable per request** — load, change, `remove()` then `add()`. Ugly, no
   new concepts, and it works.

What is not a resolution is writing a contract test that avoids the question without saying so.

**Q18** *It is a leak:* the string is `JobCardStatus::AwaitingAuthorisation->value`, and now two
places know it. Rename the case value and this query silently returns nothing — no error, just an
empty morning list, which is the worst kind of failure because it looks like good news.

*It is not a leak:* the storage representation of an enum is exactly what an infrastructure class is
supposed to know, and it is the only file that knows it. Importing `JobCardStatus` to write
`JobCardStatus::AwaitingAuthorisation->value` is arguably better — it is still infrastructure
importing the domain, which is the permitted direction — and it removes the duplication entirely.

*The resolution:* use the enum. `SqliteJobCards` already imports from `Workshop`, the arrow points
the right way, and there is no reason to hand-write a string the domain can supply. The reference
implementation uses the literal to keep the SQL readable, and that is a defensible small choice
rather than a principle — the check would be a rename-safety one, not a layering one.

**Q19** *Type affinity.* SQLite will happily store a string in an INTEGER column; PostgreSQL will
not. A mapper bug that SQLite absorbs becomes an error in production.

*Collation and case sensitivity.* `WHERE status = 'Awaiting_Authorisation'` may match in one and not
the other, and text comparison rules differ. Anything comparing registrations or part numbers is
exposed.

Also fair: transaction isolation levels, `NULL` ordering, and how each handles a failed constraint
inside a transaction.

*What it costs to find out:* almost nothing, and that is the point of having a contract test. Add a
third entry to `implementations()` pointing at a real PostgreSQL connection, run it in CI where a
database is available, and skip it locally. The assertions are already written; only the factory is
new.

**Q20** No key — this is yours. Two things to check in your own answer.

Did you name a specific decision rather than "rich models"? The strongest candidates are the private
constructors, the minted `LineId`s, and the event in `open()` — and note that the third one turned
out to cost *nothing* to persist, because 2.3 put it in the factory rather than the constructor.

And did you separate cost from regret? `reconstitute()` is a genuine hole in three classes, and it
exists because the alternative was reflection or public setters. Saying "I would do it again, and
here is what I would watch for" is a better answer than either "it was worth it" or "I would use an
ORM".

</details>

---

**Score:** ____ / 20   ·   **Threshold: 15**

Then **Gate 3** in `GATES.md`, with every file shut. That is Module 3 finished.
