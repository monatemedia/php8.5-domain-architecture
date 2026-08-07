# Quiz — Lesson 3.3: Querying Without Leaking
> Closed book. **20 questions. Threshold: 15.**

---

## Section A — Multiple choice

**Q1.** Building a dashboard by loading every aggregate is:

- A) A performance bug to optimise.
- B) The wrong shape — the repository promises aggregates and the screen wants a table.
- C) Correct, if the repository is fast enough.
- D) Only a problem above 10,000 rows.

---

**Q2.** CQRS, stripped of everything it is usually bundled with, says:

- A) Reads and writes must use separate databases.
- B) The model you change things with and the model you look at things with need not be the same.
- C) All state changes must be events.
- D) Queries must be eventually consistent.

---

**Q3.** Which of these does CQRS actually require?

- A) Event sourcing.
- B) A read database.
- C) A message bus.
- D) None of them.

---

**Q4.** The repository interface goes in the domain; the query service interface does not. The
difference is:

- A) Queries are read-only.
- B) One is a business concept that would exist without software; the other describes a page.
- C) Query services return arrays.
- D) The domain cannot depend on infrastructure.

---

**Q5.** `JobCardSummary` has public properties and no rules. That is:

- A) A violation of Lesson 1.1.
- B) The one place Lesson 1.1 §7 says an anemic shape is correct — a DTO whose job is to be rendered.
- C) Acceptable only because it is readonly.
- D) A temporary compromise.

---

**Q6.** The read model imports nothing from the domain because:

- A) Circular dependencies.
- B) A read model that can reach the domain will eventually contain a rule.
- C) Value objects are slow to construct.
- D) The namespaces are separate.

---

**Q7.** `JobCardSummary` carries `registration`, which `JobCard` does not have. This shows:

- A) The aggregate is missing a field.
- B) The shape a screen needs and the shape a consistency boundary needs are different shapes.
- C) The query is doing too much.
- D) `VehicleId` was the wrong choice.

---

**Q8.** `isOpen()` on the read model duplicates `JobCardStatus::isTerminal()`. This is accepted
because:

- A) Duplication is fine in infrastructure.
- B) They are not the same question — one is about successors, one is about what to show this
  morning.
- C) The enum is not importable.
- D) It is only two lines.

---

**Q9.** `totalOpenValueMinor()` sums across currencies without refusing. That is acceptable because:

- A) The workshop only uses one currency.
- B) It is evidence on a page, not an input to a decision — and it stops being acceptable the moment
  it becomes one.
- C) `Money` is too strict.
- D) Dashboards do not need to be correct.

---

**Q10.** A second screen wants an incompatible shape. You should:

- A) Add parameters to the existing query.
- B) Write a second read model — they are cheap, which is the point.
- C) Return a superset and let the screen ignore what it does not need.
- D) Put the shaping in the template.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 11 | Adopting read models commits you to eventual consistency. | |
| 12 | A read model may contain formatting. | |
| 13 | If a query returns a table rather than aggregates, it does not belong on a repository. | |
| 14 | A read model growing a decision method is a sign the design is maturing. | |
| 15 | Duplication between a domain rule and a display rule is always a mistake. | |

---

## Section C — Short answer

**Q16.** Give the test that decides whether an interface belongs in the domain, and apply it to both
`JobCards` and `JobCardSummaryQuery`.

*Your answer:*

---

**Q17.** Somebody proposes `JobCardSummary::canBeInvoiced()` so a button can be hidden. What is wrong
with it, and what would you do instead?

*Your answer:*

---

**Q18.** The dashboard join gets slow at 50,000 job cards. Name two options that do not touch the
domain, and say which you would try first.

*Your answer:*

---

**Q19.** Argue that the duplication in this lesson is a mistake and the read model should import the
domain. Then say which you believe and where your line is.

*Your answer:*

---

**Q20.** Module 3 is nearly done. You now have two implementations of `JobCards` — in-memory and,
after 3.4, SQLite. Name the specific way a fake drifts from a real implementation, and what that
costs.

*Your answer:*

---
---

<details>
<summary><strong>Answer key</strong></summary>

**Q1 — B.** Optimising it — a status filter, batched vehicle loads, cached totals — helps, and each
step pushes the repository towards being a query builder, which 3.1 refused. The shape is wrong, and
it stays wrong after every optimisation.

**Q2 — B.** That is the whole pattern. Two classes.

**Q3 — D.** None. Each of the others is a separate decision with its own Rule 6 argument. Somebody
telling you CQRS requires a read database is describing a system they worked on.

**Q4 — B.** Would it exist if the software did not? "Job cards awaiting authorisation" would, on a
clipboard. "The dashboard, sorted by total descending" would not.

**Q5 — B.** Lesson 1.1 §7 named this case explicitly. Nothing here is changed, saved or reasoned
about, so there is no rule to protect.

**Q6 — B.** The slope, not purity. `Money` invites `withDiscount()`; `JobCardStatus` invites
`canBeInvoiced()`.

**Q7 — B.** And note the aggregate is *not* missing a field: holding a `Vehicle` would mean loading
and locking a second aggregate on every read, which 2.1 argued at length.

**Q8 — B.** They agree today and answer different questions. If the workshop ever wants cancelled
cards to linger on the morning list, one changes and the other does not.

**Q9 — B.** Both halves. The second is the one people forget.

**Q10 — B.** Read models are cheap by design. C is the tempting wrong answer — a superset is how one
read model becomes a shared model, which is Lesson 0.2's failure in a new place.

**Q11 — FALSE.** The read model here queries the same database in the same transaction. Eventual
consistency is a separate decision you may or may not take.

**Q12 — TRUE.** Formatting is presentation, which is what a read model is for.

**Q13 — TRUE.**

**Q14 — FALSE.** It is the mechanism by which a read model stops being one. A decision belongs in the
domain, where it is authoritative and tested.

**Q15 — FALSE.** It is often two rules that happen to agree. Merging them is how a screen preference
ends up governing an invariant.

**Q16** *The test:* delete the framework and the database — does this still mean something to
somebody who runs the business?

`JobCards::awaitingAuthorisation()` survives. The service manager asks it every morning and would ask
it with a paper diary. It is a domain concept, so the interface belongs in `domain/workshop/` and the
SQL never does.

`JobCardSummaryQuery::openByValueDescending()` does not survive. It describes a page: its columns are
whatever that screen shows this month, its sort order is somebody's preference, and it changes when
the screen does. An interface that changes when a screen changes is not a domain interface, so the
whole thing lives in infrastructure and the domain does not know it exists.

**Q17** It puts a business rule in a display object. The real rule is `JobCardStatus`'s transition
table — `Completed` is the only status with `Invoiced` as a successor — and a second copy on the read
model will drift, silently, because nobody regards a view object as authoritative. The day the
workshop allows invoicing from somewhere else, one of the two gets updated.

*Instead:* let the query carry the **fact**, not the judgement. The row already has `status`; the
template can show the button when `status === 'completed'`, or the summary can expose
`isInvoiceable` as a **column computed by the same query**, sourced from the domain's own definition
rather than re-derived. The distinction is that the read model reports what is true, and does not
decide what is allowed.

And if hiding the button is the only concern: a hidden button is presentation. The rule that matters
is the one `JobCard::invoice()` enforces when somebody clicks it anyway.

**Q18** *Option 1: a summary table*, maintained from domain events — `JobCardInvoiced` and friends
from 2.3 — so the dashboard reads one denormalised row per job card with no join at all.

*Option 2: a materialised view*, if the database supports one, refreshed on a schedule. Less code,
and it accepts staleness measured in minutes.

*First:* the materialised view, because it is configuration rather than code and it can be undone.
The event-driven summary table is more powerful and introduces a second thing to keep in step —
which is Lesson 2.2's eventual consistency, with all of its questions, taken on for a dashboard.
Reach for it when the staleness of a refresh is genuinely unacceptable, and not before.

Note both are infrastructure. The domain does not change either way, which is the return on all of
this.

**Q19** *The case for importing:* the duplication is real and it will drift. `Currency::format()`
already knows about JPY and KWD, and the read model's `match` will be forgotten when a sixth currency
is added — producing wrongly formatted money on a screen, which is exactly the class of bug value
objects exist to prevent. A typed `JobCardId` on the summary would also stop it being passed to the
wrong route.

*What I believe:* the strict line, with the boundary stated. The failure the strict line prevents — a
business rule quietly living in a view object, treated as non-authoritative and drifting — is
expensive and hard to spot. The failure it causes — a currency formatted wrongly on a dashboard — is
visible immediately and cheap to fix.

*Where the line is:* no domain type in a read model, and no domain *rule* duplicated. Formatting may
be duplicated when it is small, and the moment it is not small it goes into a shared formatter in the
**presentation layer** — still not the domain enum.

**Q20** The classic drift is **ordering**. An in-memory repository returns results in insertion
order because that is what an array does; SQL returns them in whatever order the planner chooses
unless you say otherwise. A test written against the fake asserts `$results[0]` is the oldest job
card, passes forever, and the production query returns them in a different order the day an index
changes.

Others in the same family: an in-memory `add()` that silently replaces where a unique constraint
would throw; a fake that is case-sensitive where the collation is not; a fake that holds references
so a mutation is visible without saving, where SQLite needs an explicit write — which is 3.1's
collection-oriented problem exactly.

*What it costs:* a green test suite and a broken system, which is worse than no test suite, because
the suite is why nobody looked. The answer is Lesson 3.4: one contract test, run against both
implementations, so a behaviour that differs cannot pass.

</details>

---

**Score:** ____ / 20   ·   **Threshold: 15**
