# Lesson 0.1 — The Cost of DDD, and How to Price It
> **Module 0: When, and When Not, to Do This** · Advanced Domain Architecture

| | |
|---|---|
| **Time** | ~25 min reading · ~20 min running the examples. Challenge on top. |
| **Comes after** | `COURSE_PHILOSOPHY.md` and `DOMAIN.md` |
| **Covers** | The ledger · The three options · Signals for and against · A decision procedure · Architecture theatre |
| **Needs PHP** | 8.5+ |

---

## 📁 Lesson folder

```
lesson-0.1-the-cost-of-ddd/
├── README.md                            ← you are here
├── examples/
│   ├── 01-the-same-feature-three-ways.php   ← one feature, three architectures
│   ├── 02-when-the-ceremony-wins.php        ← the same three, when a rule changes
│   └── 03-the-decision-procedure.php        ← the scorer, run against six real briefs
├── challenge/
│   └── CHALLENGE.md                     ← four briefs. Decide, and defend.
└── quiz/
    └── QUIZ.md
```

---

## 1 — The claim that gets people into trouble

> "Domain-Driven Design makes your code express the business."

True, and useless as stated, because it omits the price. The honest version is:

> Tactical DDD moves business rules out of services and controllers and into objects that refuse to
> break them. It costs you indirection, files, a mapping layer, and onboarding time. It is worth it
> when the rules are complicated enough that not having them in one enforceable place is costing
> you more.

Every word of that second sentence is doing work. "Complicated enough" is a threshold, and this
lesson is about locating it.

---

## 2 — The ledger: what it actually costs

Not a warning label. An itemised bill, with who pays and when.

| Cost | Who pays | When |
|------|----------|------|
| **Indirection** — to answer "what happens when a job card is invoiced?" you now open three or four files instead of one | Every developer, on every read | Forever, starting immediately |
| **More files** — a single concept becomes an entity, a value object, a repository interface, an implementation, an exception, and a mapper | Whoever navigates the codebase | Forever |
| **The mapping layer** — a rich aggregate with private state does not map onto tables. Somebody writes and maintains the translation | The team, in code you cannot show a customer | Immediately, and again on every schema change |
| **Onboarding** — a new developer is productive in a day on Active Record and a week here | Whoever hires next | On every hire |
| **Query awkwardness** — "show me all overdue job cards with the account holder's name" does not fit "load one aggregate", so you need a second read path | The team | The first time somebody asks for a report, which is week three |
| **Team agreement** — the patterns only pay off if everyone applies them consistently. One developer who does not is enough to hollow it out | The team, socially | Continuously |

That last one is underrated and it is not a technical cost. Tactical DDD is a set of conventions
enforced by agreement, not by the compiler. A team that half-agrees ends up with the file count of
DDD and the guarantees of a transaction script.

**None of these costs are avoidable by being clever.** They are inherent to the approach. The
question is never how to avoid them; it is whether what you get back is worth more.

---

## 3 — What you get back

| Benefit | Only real when… |
|---------|-----------------|
| A rule is enforced everywhere, including in code not yet written | The rule genuinely applies everywhere — not "usually" |
| Invalid states cannot be constructed, so downstream code stops re-checking | There is a meaningful notion of invalid beyond "field missing" |
| Business logic is testable without a database, so tests run in milliseconds | The logic is complicated enough to be worth testing hard |
| The code can be read back to the business, who can correct it | Someone in the business will actually read it |
| The rules survive the framework being replaced | The system will outlive its framework |

Read the right-hand column carefully. Each benefit has a precondition, and where the precondition
fails the benefit is imaginary while the cost is real. That asymmetry is the whole reason this
lesson exists.

---

## 4 — The three options, described honestly

Most writing on this subject presents two options: the good one, and the one written by people who
have not read the book. There are three, and all three are correct somewhere.

### Option A — Active Record

The ORM model *is* the domain object. Rules live on the model, or in the controller, or in a
service, depending on the day.

```php
$jobCard = JobCard::find($id);
$jobCard->status = 'invoiced';
$jobCard->save();
```

**Correct when:** the schema is the model, the rules are shallow, and the team is small. This is
most software, and it is a legitimate engineering choice rather than a failure of nerve.

**The failure mode:** rules end up in whichever layer the developer was standing in. Three years
later, `status = 'invoiced'` is assigned in eleven places and two of them skip a check that the
other nine perform.

### Option B — Transaction script

Procedures. One function per use case, each doing the whole job from top to bottom, against fairly
dumb data structures.

```php
function invoiceJobCard(int $id, PDO $db): void {
    // load, check the rules, calculate, write, done
}
```

Deeply unfashionable and frequently the right answer. It is honest, it is easy to follow top to
bottom, it has no ceremony at all, and its costs show up only at scale. Most "we did DDD and it was
awful" stories are about a codebase that should have been this.

**Correct when:** the use cases are numerous but individually simple, and there is little rule
overlap between them.

**The failure mode:** rule duplication. The same check, copy-pasted into nine scripts, drifts.

### Option C — Tactical DDD

Rules live in objects that refuse to break them. This course.

**Correct when:** the rules overlap, span multiple objects, and matter. The rest of the lesson is
about recognising that.

**The failure mode:** ceremony without discipline. See section 7.

> **There is no ranking here.** A senior developer choosing Active Record for a CRUD admin panel is
> exercising judgement. A senior developer choosing tactical DDD for the same panel is not.

---

## 5 — Signals that justify the ceremony

Not a checklist to tick off. Signals to weigh, in rough order of strength.

**S1 — An invariant spans more than one object.**
"A courtesy vehicle cannot be on two open job cards." No single row can enforce that, so it will
live in application code somewhere — the only question is whether it lives in *one* place or in
however many places happen to touch courtesy vehicles. This is the strongest signal there is.

**S2 — The lifecycle has illegal transitions, not just states.**
A `status` column is not this signal. *"Completed cannot go back to InProgress, and Invoiced cannot
go anywhere at all"* is. If you can draw a diagram with arrows that are deliberately missing, you
have a lifecycle worth modelling.

**S3 — People who do not write code argue about the rules.**
If a rule has been changed twice after a meeting, it is a business rule with a life of its own and
it deserves a home with a name. If nobody has ever discussed it, it is probably a validation
constraint.

**S4 — Being wrong is expensive.**
Money, law, safety, or an auditor. "The system allowed it" is not an acceptable answer in a
regulated domain, and "we validate in the controller" is the same sentence with more syllables.

**S5 — Calculations with consequences.**
Anything where two parts of the system computing a total slightly differently produces a real-world
discrepancy somebody has to reconcile by hand.

**S6 — The system will outlive its framework.**
Rare, and often claimed on thin evidence. Genuine for systems with a ten-year horizon; imaginary
for the internal tool that will be replaced when the team that asked for it reorganises.

**Rough rule:** S1 or S2, plus one other, is a real case. S6 alone is not.

---

## 6 — Signals that argue against

**A1 — The database schema *is* the model, and everyone is content.**
Fields in, fields out. Adding a layer whose only job is to copy fields between two shapes is a cost
with no matching benefit.

**A2 — The work is reporting, content management, or configuration.**
These have shape but no rules. An aggregate around a settings table is the canonical example of
this going wrong, and Example 02 shows exactly how silly it looks.

**A3 — The only rule anyone can name is "this field is required".**
If asking "what makes one invalid?" produces a list of required fields, there is no interesting
notion of invalid, and a value object is a file with nothing in it.

**A4 — Nobody on the team has done this before, and the deadline does not permit learning.**
An unfamiliar pattern applied under pressure produces the file count without the discipline. This is
a legitimate reason to say no, and saying it out loud is more professional than discovering it in
month four.

**A5 — The system is a thin client over someone else's API.**
If the rules live in the vendor's system, modelling them again in yours is a second source of truth
that will disagree with the first.

---

## 7 — Architecture theatre

The failure mode that is worth naming, because it is the most common outcome and it is not the one
people worry about.

Architecture theatre is when a codebase has all the artefacts of a domain layer — entities, value
objects, repositories, a `Domain\` namespace — and none of the guarantees.

The tells:

- Entities have public setters, so the invariants are decorative.
- The "repository" returns arrays, or ORM models, or `null` for "not found and also for error".
- The domain layer imports the framework in three files, and everyone has agreed not to mention it.
- There are "domain services" containing all the logic, and entities containing none — an anemic
  model with more folders.
- The exception classes exist but everything is caught as `\Exception` at the boundary anyway.

Every cost in section 2 has been paid. Nothing in section 3 has been bought. This is strictly worse
than Active Record, and it is what a team produces when it adopts the patterns without adopting
Rule 6.

**How to avoid it:** apply the patterns to the rules that actually justify them, and leave the rest
alone. A codebase with one carefully modelled aggregate and forty Active Record models is not
inconsistent — it is proportionate, and it is what mature systems that do this well actually look
like.

---

## 8 — The decision procedure

Ten minutes, a whiteboard, and no code.

1. **List the rules.** Not the fields — the rules. Anything of the form "X may not happen when Y".
   If the list is short and every item is "this field is required", stop: Active Record.

2. **For each rule, ask how many objects it touches.** Any rule touching more than one is a point
   towards a domain layer. All rules touching exactly one is a point against.

3. **Draw the lifecycle.** If there is no lifecycle, or if every state can reach every other state,
   there is nothing to protect.

4. **Ask what happens when a rule is broken.** A wrong row someone fixes in the admin panel is not
   the same as a wrong invoice you must explain to a regulator.

5. **Ask who will read the code.** If the answer includes anyone outside the engineering team, the
   ubiquitous-language benefit is real. If not, discount it.

6. **Ask what the team can sustain.** Honestly. Then decide.

7. **Scope the answer.** The output is rarely "yes" or "no" for a whole system. It is usually
   *"yes for the job card, no for everything else"* — and that is the answer this lesson most wants
   you to be comfortable giving.

`examples/03-the-decision-procedure.php` runs this as a scorer over six briefs. Run it, then argue
with it — the scores are a prompt for the conversation, not a verdict, and the example says so.

---

## 9 — Common mistakes

| Mistake | Why it is wrong | Instead |
|---------|----------------|---------|
| Deciding for the whole system at once | Different parts have different rule density | Decide per bounded context, or per aggregate |
| Counting entities instead of rules | Fifty tables with no rules is still CRUD | Count rules that span objects |
| Treating "we might need it later" as a signal | You will not build the model you imagine, and the cost starts today | Wait for the second place a rule needs enforcing |
| Adopting the patterns without Rule 6 | Architecture theatre | Apply them where they pay |
| Assuming Active Record means bad | It is the correct answer for most software | Choose it deliberately, and say why |
| Deciding alone | The strongest signals are about the business, not the code | Ask someone who runs the business |

---

## 10 — Where else this shows up

The cost question is not specific to DDD. It is the same question you ask about a message queue, a
microservice boundary, a cache, or a feature flag system: *what does this cost on every read
forever, and what does it buy that I can name?*

If you can run section 8's procedure for a domain layer, you can run it for those too. The specific
signals differ. The shape of the argument does not.

---

## ✅ Lesson checklist

- [ ] Read this README fully — especially sections 2, 5 and 6
- [ ] Run and study `examples/01-the-same-feature-three-ways.php`
- [ ] Run and study `examples/02-when-the-ceremony-wins.php`
- [ ] Run and study `examples/03-the-decision-procedure.php`, then disagree with at least one score
- [ ] Complete `challenge/CHALLENGE.md` — four briefs, four decisions, two sentences each
- [ ] Take `quiz/QUIZ.md` closed-book

---

*Next lesson: **0.2 — Ubiquitous Language and Bounded Contexts** — why the patterns are meaningless
without the words.*
