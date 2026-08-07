# Quiz — Capstone: The Workshop, End to End
> Closed book. **20 questions. Threshold: 15.**

---

## Section A — Multiple choice

**Q1.** The capstone adds no business rules. That is:

- A) An oversight.
- B) The test — if assembling the application required changing the domain, the domain was wrong.
- C) Because the rules are in the framework.
- D) A simplification for teaching.

---

**Q2.** The import scan found that the domain layer's external vocabulary is:

- A) Slim and PDO, minimally used.
- B) Four SPL names — `DomainException`, `InvalidArgumentException`, `NoDiscard`, `Throwable` —
  because domain classes share a namespace and refer to each other unqualified.
- C) Nothing at all, including SPL.
- D) About thirty types.

---

**Q3.** The scan is a build failure rather than a convention because:

- A) Conventions are hard to document.
- B) A rule with no enforcement decays through one `use Infrastructure\Clock;` added at 5pm, which
  nobody reviews.
- C) Build failures are faster.
- D) The RFC requires it.

---

**Q4.** It uses `token_get_all()` rather than a regex because:

- A) Speed.
- B) A grep cannot tell a dependency from a doc block explaining one, or `PartStock` from
  `PartStockRow` — both of which happened in this project.
- C) Regexes cannot match namespaces.
- D) Tokens are easier to read.

---

**Q5.** The scanner records fully-qualified names as well as `use` statements because:

- A) Completeness.
- B) A file with no imports can still type-hint `\PDO $connection`, and a scan reading only imports
  would call it clean.
- C) Attributes are not imports.
- D) PHP requires it.

---

**Q6.** `use Slim\App as Harmless;` must be caught because:

- A) Aliases are bad style.
- B) The alias is a local nickname; the dependency is the original name.
- C) Slim forbids aliasing.
- D) It would break autoloading.

---

**Q7.** A use case containing an `if` is a problem because:

- A) It is harder to read.
- B) It is probably a business rule that belongs on an aggregate, and it ended up there because the
  aggregate was inconvenient to change.
- C) `if` cannot be tested.
- D) Use cases must be single-expression.

---

**Q8.** The use cases live in `domain/workshop/` rather than `Application\` because:

- A) They are domain logic.
- B) A third namespace root for four classes is ceremony — and sharing the domain's root means they
  submit to the domain's scan, which is a harder standard.
- C) PHP-DI requires it.
- D) There is no difference.

---

**Q9.** `GuardedJobCards` wraps `SqliteJobCards` rather than the other way round because:

- A) Alphabetical order.
- B) The order decides whether a `PDOException` reaches the application untranslated.
- C) Slim requires the outermost decorator first.
- D) It is faster.

---

**Q10.** The use cases and controller are not defined in the container because:

- A) They are not services.
- B) PHP-DI reads their constructor type hints — which works only because those constructors ask for
  types rather than reaching for globals or a service locator.
- C) They are created by Slim.
- D) Definitions are only for interfaces.

---

**Q11.** `ProblemDetailsMiddleware` is added **last** because:

- A) It is the most important.
- B) Slim's middleware is LIFO, so last added runs outermost — where it can catch failures from
  routing and body parsing too.
- C) Order does not matter.
- D) It depends on the router.

---

**Q12.** The middleware's error-handling logic is about twenty lines because:

- A) The application is small.
- B) Every decision it would otherwise make was made in Module 4 — which failures are safe to show,
  what to call them, what fields to include, which status code.
- C) Slim handles most of it.
- D) It only handles two cases.

---

**Q13.** The middleware catches `\Throwable`, which Lesson 4.1 argued against. The reconciliation is:

- A) 4.1 was wrong.
- B) "Loud" means loud to the **operator**, not to the user — the user gets an opaque 500 and the
  operator gets the exception.
- C) The outermost layer is exempt from the rule.
- D) `\Throwable` is safe once the tree exists.

---

**Q14.** Only failures the mapper turned into a **5xx** are reported to the operator because:

- A) 4xx are less serious.
- B) A 4xx is the system working, and reporting those is how an error tracker becomes noise nobody
  reads.
- C) 4xx cannot be logged.
- D) The mapper discards 4xx.

---

**Q15.** `POST /job-cards/{id}/completion` rather than `/complete` because:

- A) It is shorter.
- B) A noun is a thing that can be created; a verb reads as RPC over HTTP and tells a client nothing
  about what it can retry.
- C) Slim cannot route verbs.
- D) REST forbids verbs.

---

## Section B — Short answer

**Q16.** The capstone's `CompleteJobCard` does `remove()` then `add()`. Explain what is wrong with it
beyond being ugly, name the lesson that made this inevitable, and give the two real fixes.

---

**Q17.** `POST /job-cards` with an empty `vehicleId` returns 500. Explain why, and say why the
capstone asserts this rather than fixing it.

---

**Q18.** The scanner is tested with four probe files that differ by one line each. Say what each
probe is for, and why a detector needs testing in both directions.

---

**Q19.** Name one thing the import scan cannot catch, and say whether you would try to close it.

---

**Q20.** The course is finished. Name the first thing you would do in a real codebase, and the two
lessons you would check it against before starting.

---
---

# Answer key

**Q1 — B.**

**Q2 — B.** And it was found by two checks *failing* — they had been written expecting to see
`Workshop\JobCards` among the references, which is not there because same-namespace classes need no
import. The result is stronger than the one that was being looked for.

**Q3 — B.** Not a big violation somebody would notice. One line, at 5pm, because the alternative was
an interface and a container binding.

**Q4 — B.** Bugs 3 and 7 in `RUN-THIS-FIRST.md`, and the rule they produced: a check that reads source
is a parser with the parsing left out.

**Q5 — B.**

**Q6 — B.**

**Q7 — B.**

**Q8 — B.**

**Q9 — B.** One line, two very different applications.

**Q10 — B.** Worth noticing rather than taking for granted: autowiring is a *consequence* of asking
for types, not a feature you turn on.

**Q11 — B.** Add it first and an unmatched route returns Slim's HTML error page to a client that
asked for JSON.

**Q12 — B.** This is the test of whether the previous four modules were worth it. If the middleware
needed a hundred lines of mapping, the exceptions were not carrying their weight.

**Q13 — B.**

**Q14 — B.**

**Q15 — B.**

**Q16.** Beyond ugly: `remove()` commits, then `add()` commits, so **between them the job card does
not exist**. A concurrent read gets a `JobCardNotFound` for a job card that is merely being saved, and
a crash between the two loses it entirely. Single-threaded against `:memory:` nothing will ever show
it, which makes it the kind of bug that ships.

It was made inevitable by **Lesson 3.1**, which chose a collection-oriented repository —
`add()`/`remove()`, no `save()` — warned that it had a cost, and refused to design around it in
advance.

The two fixes: a **unit of work** (track what was loaded, compare at the end of the request, write
what changed in one transaction — correct, and real machinery most PHP teams decide against), or an
explicit **`save(JobCard $jobCard)`** on the interface (honest, three lines, and it moves one word of
storage vocabulary back into every caller). That is exactly the trade 3.1 described, and the capstone
exists partly so you have now paid the other side of it.

**Q17.** `VehicleId::fromString('')` throws a bare `\InvalidArgumentException` — no `WorkshopFailure`
marker, no `failureCode()` — so `ProblemDetailsMapper` has nothing to key on and correctly refuses to
guess a status. The right answer is 422 and the application cannot give it.

It is asserted rather than fixed because it is the capstone's most useful finding: Module 4 named
every throw site in `JobCard` and the repositories and explicitly left the value objects to the
reader, and **nothing noticed for two modules** because nothing had asked a value object to refuse
something on the far side of an HTTP boundary. Working around it would hide the one thing an
end-to-end assembly is for.

The fix is mechanical — the tree already has the `InvalidWorkshopInput` branch these belong on.

**Q18.** The four probes: an import of Slim (must be **caught**); an import of `Infrastructure\`
(must be caught **and named in the report**); a doc block mentioning `\PDO`, `Slim\App` and
`Infrastructure\SqliteJobCards` (must be **clean**); an aliased import (must be caught); a closure's
`use ($x)` (must be clean).

Both directions matter because the two failure modes are opposite and equally fatal. **A detector
that never fires passes forever** — the check is decorative and nobody finds out. **A detector that
always fires gets deleted on a Friday**, by somebody shipping a release, and it does not come back.

**Q19.** It cannot catch a class named as a **string**: `$container->get('Slim\App')`, or
`new ('Slim\\' . $name)`. It also cannot catch a domain class calling a global function that reaches
for infrastructure — `app()`, `resolve()`, `\DB::table(...)` are function calls, not type references.

Whether to close them: the string case, no — nothing short of running the code finds it, and a domain
class building class names from strings has a larger problem than this scan. The helper-function case
is more interesting and closable with a small list of forbidden function names, and it is worth doing
in a Laravel codebase specifically, where `app()` is everywhere.

The general point: **a check whose limits you cannot state is a check you are trusting rather than
using.**

**Q20.** Any honest answer that names a real invariant. The strongest ones look like:

*"Add an exception tree and a boundary mapper"* — Lesson 5.1's rung 4, which costs nothing because it
has no opinion about persistence, and turns 500s into 409s and 422s across the whole application.

*"Extract one aggregate at the invariant that keeps generating incidents"* — Lesson 5.2, with a
characterisation test first and a data survey before enforcing anything.

The two lessons to check it against: **Module 0** (is there a signal that justifies any of this, or
is the schema the model?) and **Lesson 5.1** (which rung of the ladder is this, and what does it
cost?).

An answer of "rewrite it properly" gets no credit, and the course has spent two modules explaining
why.

---

## Scoring

| Score | Verdict |
|-------|---------|
| 18–20 | Take Gate 6. Then the course is finished. |
| 15–17 | Pass. Re-read sections 3 and 8 before the gate. |
| 11–14 | Run both examples, then retake. |
| 0–10 | Re-read the lesson. Section 2 is the result everything else was for. |
