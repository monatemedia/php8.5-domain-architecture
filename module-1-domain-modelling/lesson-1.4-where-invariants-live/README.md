# Lesson 1.4 — Where Invariants Live ⭐
> **Module 1: Domain Modelling** · Advanced Domain Architecture

| | |
|---|---|
| **Time** | ~30 min reading · ~25 min running the examples. Challenge on top. |
| **Comes after** | Lesson 1.3 — Entities and Identity |
| **Covers** | The four candidate homes · Which one owns a rule · Invariant vs policy vs sanity check · Why "validate everywhere" is a design failure · Duplicating a rule on purpose |
| **You write** | `LabourUnits.php`, `LabourLine.php` |
| **Needs PHP** | 8.5+ |

---

## 📁 Lesson folder

```
lesson-1.4-where-invariants-live/
├── README.md
├── examples/
│   ├── 01-four-places-one-rule.php     ← the same check, four homes, four blast radii
│   ├── 02-invariant-or-policy.php      ← the distinction that decides everything
│   └── 03-relocating-the-checks.php    ← a class with scattered guards, fixed
├── challenge/
│   ├── CHALLENGE.md
│   ├── checks.php
│   ├── starter.php
│   └── solution/
└── quiz/QUIZ.md
```

---

## 1 — The question

You have a rule. Four places could enforce it:

```
   ┌──────────────────────────────────────────────────────────────┐
   │  1. The API boundary        validate the request              │
   │  2. A service                check before doing the work      │
   │  3. A setter on the model    check when the field changes     │
   │  4. The constructor          check at construction            │
   └──────────────────────────────────────────────────────────────┘
```

Most codebases answer "all four, to be safe". That is a design failure, and this lesson is about
why — and about the one case where duplication is correct.

---

## 2 — What each home actually protects

| Home | Holds for | Fails for |
|------|-----------|-----------|
| **API boundary** | Requests through that boundary | A console command, a queue worker, a data migration, the second controller added next year |
| **Service** | Callers who remember the service | The bulk import, the admin screen, the second service, the test factory |
| **Setter** | Callers using that setter | Direct property access, hydration, `clone`, construction |
| **Constructor** | **Everything** | Nothing — there is no way to obtain the object without going through it |

Only the last row has an empty right-hand column, and that is the whole argument. A rule in the
constructor holds for code that has not been written yet, which is where most of your bugs live.

Lesson 1.1's example 02 was this table with the failures acted out.

---

## 3 — Three kinds of guard, and why the difference matters

The single most useful distinction in the lesson. Every guard you write is one of three things, and
they belong in different places.

### An invariant

*Must always be true. Of the object, at every instant, regardless of where it came from.*

- Money is never negative.
- A registration is normalised.
- Labour bills in whole tenths.

**Home:** the constructor, or a named operation that changes the state. Nowhere else.
**Test:** if you loaded this straight from the database and the rule were violated, would that be a
data-corruption incident? Yes → invariant.

### A policy

*What the business currently prefers. It varies, it is negotiated, and it will change.*

- Additional work under 50 proceeds without authorisation.
- Tax is 20%.
- Fleet accounts get a 200 threshold instead.

**Home:** passed *in* as an argument or a configured object. **Never** a constant inside a domain
class.
**Test:** could two customers reasonably have different answers? Could it change after a meeting?
Yes → policy.

A policy hard-coded into a value object is a bug with a commencement date. Nobody finds it until the
day it changes, and then the change requires a deployment rather than a setting.

### A sanity check

*Not a business rule at all. It exists to catch a typo.*

- An odometer reading above 2,000,000 km.
- A description longer than 10,000 characters.

**Home:** wherever the typo can occur — usually the boundary. It should be **overridable**, or set
so far out that no real value reaches it.

**This is the one people get wrong**, and Lesson 1.3's follow-up question was about it. A sanity
check with no override path becomes a business rule by accident. The haulage truck arrives on 2.4
million km, the guard refuses a true reading, the service adviser types 999,999 instead, and the
history is quietly wrong forever. Nobody logs a bug, because from the desk it looked like validation
working.

> If you take one thing from this lesson: **before you write a guard, say out loud which of the
> three it is.** Half the argument about where it goes disappears once you have.

---

## 4 — Which object owns an invariant

Given that it is an invariant, *which* object holds it? One test:

> **Can you decide the rule while holding only this object?**

- **Yes** → it belongs on this object.
- **No** → it belongs on whatever holds the context you need.

Worked through, from Lesson 1.3:

| Rule | Holding only… | Decidable? | Home |
|------|--------------|:----------:|------|
| A reading is not negative | an `OdometerReading` | yes | `OdometerReading` |
| A reading is not lower than the last | an `OdometerReading` | **no** — no previous | `Vehicle` |
| A courtesy vehicle is not allocated twice | a `CourtesyVehicle` | yes | `CourtesyVehicle` |
| A courtesy vehicle is on one open job card *anywhere* | a `CourtesyVehicle` | **no** — no other job cards | nothing in Module 1 — see 2.2 |

The last row is important and it is honest: **some rules have no correct home yet.** That is not a
failure of your modelling; it is the boundary of what a single object can enforce, and Module 2 is
about what you do instead. A rule forced onto an object that cannot see enough to enforce it
produces a check that looks like protection and is not.

---

## 5 — Why "validate everywhere" is a design failure

The instinct is defensible: more checks, more safety. Three reasons it does not work out.

**Copies drift.** Eleven checks, one of which says 1,000 where the others say 2,000. This has
already happened in your codebase; the only question is whether you know where.

**Nobody knows which is authoritative.** When two disagree, the fix requires archaeology: which was
written first, which is used by more callers, which one the business actually meant. That
conversation costs more than the check ever saved.

**The real one is hidden by the noise.** A reader scanning for "where is the rule about X?" finds
four, and cannot tell which is the definition and which are echoes. So they add a fifth.

### The exception, and it is real

**A boundary may duplicate a rule for the purpose of a better error message.**

```php
// At the API boundary — for the message, not for the guarantee
if ($request['amount'] < 0) {
    return problem(422, 'Amount must be zero or more.');
}

// In the model — the actual rule
if ($amountMinor < 0) {
    throw new \InvalidArgumentException(/* ... */);
}
```

That is not two homes for one rule. It is one rule and one **presentation** of it. The distinction
is testable:

- If you deleted the boundary check, would anything become possible? **No** → it was presentation,
  and it is fine.
- If you deleted the model check, would anything become possible? **Yes** → that was the rule.

The failure mode is a boundary check that is *stricter* than the model. Then it is not
presentation — it is a second rule wearing presentation's clothes, and the day someone calls the
model from a console command, they get behaviour the API would have refused.

---

## 6 — Order inside a method: validate first, mutate last

Mentioned in 1.1, and it matters more each module.

```php
// Wrong — half-applied when it throws
public function invoice(): void
{
    $this->status     = Status::Invoiced;
    $this->invoicedAt = $now;
    if (!$this->isComplete()) {
        throw new CannotInvoiceIncompleteJobCard(/* ... */);
    }
}
```

The exception propagates, the caller handles it, and a half-mutated object stays in memory — and
gets saved. Nothing catches that. There is no rollback for an object.

**Every guard first. Every assignment after.** In a method with one assignment this looks like
pedantry; in Module 2's aggregate methods, which change a status, a collection, a total and a
recorded event together, it is the only thing that makes "the object is always valid" true rather
than aspirational.

---

## 7 — The rule you are about to write for the challenge

Invariant **I3** from `DOMAIN.md`:

> Labour is billed in whole tenths of an hour, rounded **up**, with a minimum of five tenths per
> line.

Three separate things in one sentence. Take them apart before you write anything:

| Part | Kind | Where |
|------|------|-------|
| Whole tenths — no fractional tenths exist | invariant | `LabourUnits`, at construction |
| Rounded **up** — 7 minutes bills as 2 tenths, never 1 | invariant | `LabourUnits`, at construction |
| Minimum five tenths per line | **arguable** — see below | `LabourUnits`, and the challenge says so |

That third one is worth an argument, and the challenge asks you to have it. A half-hour minimum is
exactly the shape of a policy: it is a commercial decision, it differs between workshops, and a
fleet contract might waive it. It is treated as an invariant here because `DOMAIN.md` states it as
one — which is the honest reason, and a weaker one than it looks.

**Noticing that is the exercise.** A rule is an invariant because the business says it can never be
otherwise, not because it appears in a specification.

---

## 8 — Common mistakes

| Mistake | Why it is wrong | Instead |
|---------|----------------|---------|
| Validating in all four places | Copies drift; the real one is hidden | One home, plus presentation at the boundary |
| A policy as a `const` in a domain class | A bug with a commencement date | Pass it in |
| A sanity check with no override | Becomes a business rule by accident, and produces invented data | Set it far out, or make it overridable |
| Guards after assignments | A half-mutated object survives the exception | Validate first, mutate last |
| Forcing a rule onto an object that cannot see enough | A check that looks like protection | Leave it unenforced and say so — Module 2 |
| A boundary check stricter than the model | A second rule pretending to be a message | Boundary checks may be equal or looser, never stricter |

---

## 9 — Where else this shows up

"Where does this rule live?" is not a DDD question. It is the same question as:

- **Database constraints vs application validation** — the constraint is the invariant, the
  application check is the message. Exactly section 5's split.
- **Type systems vs runtime checks** — the same argument about how early a rule can be enforced.
- **Front-end vs back-end validation** — the front end is presentation; if the back end trusts it,
  there is no rule at all. Every "we validate on the client" incident is this lesson.

If you can articulate why client-side validation is not a rule, you already have the instinct.

---

## ✅ Lesson checklist

- [ ] Read this README fully — especially sections 3 and 5
- [ ] Run and study `examples/01-four-places-one-rule.php`
- [ ] Run and study `examples/02-invariant-or-policy.php`
- [ ] Run and study `examples/03-relocating-the-checks.php`
- [ ] Read `challenge/checks.php` before writing anything
- [ ] Complete the challenge
- [ ] Compare against `challenge/solution/`, and log disagreements in `PROGRESS.md`
- [ ] Take `quiz/QUIZ.md` closed-book
- [ ] Take **Gate 1** in `GATES.md` with every file shut

---

*Module 1 ends here. `domain/workshop/` now holds nine classes, and Module 2 turns `JobCard` into an
aggregate root over them.*
