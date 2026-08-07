# Advanced Domain Architecture & Tactical DDD
### Modelling business reality in PHP 8.5 — aggregates, invariants, events and exception trees

> **Build status:** Modules 0 and 1 are written and verified on PHP 8.5.7, as are Lessons 2.1 and
> 2.2. The rest of Modules 2–6 is scaffolded from the blueprint but not yet built. `php check.php`
> counts only what exists, so the percentage is honest.

---

## Prerequisite

You must have completed **PHP 8.5 OOP Mastery** — all six modules, the capstone, and the quizzes.
Not *read it*: completed it.

That is not gatekeeping. This course assumes you can already compose objects, inject dependencies,
wire a container, and write a test with a fake. Every one of those is used here without
explanation. Module 2 is unteachable to someone who has not internalised Module 3 of that course.

**The shift in subject matter:** the previous course was about *how to wire components*. This one
is about *what the components should be*. It moves from technical objects — loggers, mailers,
repositories — to business objects: a job card that knows the rules for cancelling itself, a price
that cannot be negative, a bill that refuses to be issued twice.

---

## The one question this whole course answers

> **"If I deleted my framework, my database driver and my web server tomorrow morning, would the
> code in this file still make sense to someone who runs the business?"**

- **Yes** → it belongs in the **domain layer**. Entities, value objects, aggregates, domain events,
  domain exceptions.
- **No** → it belongs in the **infrastructure layer**. Controllers, repository implementations, HTTP
  mapping, database drivers, queue adapters.

Every lesson comes back to this. If you remember nothing else, remember the question.

---

## ⚠️ Read this before you plan anything

**Most applications should not use tactical DDD.** It is a large amount of ceremony that pays for
itself only when the business rules are genuinely complicated. Applied to a CRUD app — forms in,
rows out, no interesting invariants — it produces four layers of indirection around an `UPDATE`
statement and makes the codebase worse.

Module 0 exists to teach you to tell the difference, and it comes first deliberately. A course that
teaches only the patterns produces developers who apply them everywhere. The judgement of *when
not to* is the more valuable half, and it is the half almost every DDD course omits.

---

## Start here

> **Verified green on PHP 8.5.7.** 46/46 files parse, 10/10 language probes pass, all four
> reference solutions pass their own acceptance checks, all fourteen examples run. The record —
> including the two bugs the first run exposed — is in [`RUN-THIS-FIRST.md`](RUN-THIS-FIRST.md).

```bash
cd ~/Herd/php8.5-domain-architecture

php verify.php         # is the course sound? runs every reference solution
php check.php          # environment, integrity, and where you are
```

`check.php` walks the lessons in order and stops at the first unsolved one, so it always gives you
exactly one next action. Run it whenever you are unsure what to do next.

You do **not** need `composer install` yet. Modules 0, 1 and 2 are plain PHP with no dependencies
at all — which is the point being made, not an accident. Composer is needed from Module 3.

Then, in this order:

1. `cp PROGRESS.template.md PROGRESS.md` — your copy of the tracker. It is git-ignored, because
   your ticks and scores are yours rather than course material.
2. Read `COURSE_PHILOSOPHY.md` — the six rules. Twenty minutes, and everything after leans on it.
3. Read `DOMAIN.md` — the vehicle workshop every lesson models. Keep it open.
4. Open `module-0-when-and-when-not/lesson-0.1-the-cost-of-ddd/README.md`.
5. Record what you finish in `PROGRESS.md`.

### What is tracked, and what is yours

Everything the course ships is in the repository, including **every reference solution and every
quiz answer key** — that is where a good deal of the teaching lives, and none of it is hidden.

Two things are not tracked, because they belong to whoever is working through it:

| Yours, git-ignored | Why |
|--------------------|-----|
| `domain/workshop/*.php` | your answers to the code challenges. If a finished one were committed, a fresh clone would report Module 1 complete before its owner had opened a file. |
| `PROGRESS.md` | your ticks, scores and sticking points. The template is tracked instead. |

Grading is unaffected by this. `check.php` runs *your* `domain/workshop/` against the course's
`challenge/checks.php`, and falls back to `challenge/solution/run.php` to tell a course bug from a
mistake of yours. Both of those are tracked, so a stranger who has cloned this and written nothing
gets graded correctly from the first run.

---

## 📁 Folder structure

```
php8.5-domain-architecture/
├── README.md                        ← you are here
├── RUN-THIS-FIRST.md                ← verification record, and how to re-run it
├── COURSE_PHILOSOPHY.md             ← the six rules
├── DOMAIN.md                        ← the workshop: language, lifecycle, invariants
├── PROGRESS.template.md             ← copy to PROGRESS.md; that copy is git-ignored
├── GATES.md                         ← end-of-module recall, with spaced carry-forward
├── check.php                        ← where am I?
├── verify.php                       ← is the course intact?
├── regression.php                   ← does the newest code still satisfy every earlier lesson?
├── run-tests.php                    ← PHPUnit runner (Module 3 onwards)
├── index.php                        ← cover page, if you point a Herd site here
├── composer.json · phpunit.xml
│
├── lib/
│   ├── acceptance.php               ← how a challenge is judged. Read this early.
│   └── autoload-domain.php          ← nine-line PSR-4 autoloader
│
├── domain/workshop/                 ← YOUR codebase. Grows from Module 1 to the capstone.
│
├── module-0-when-and-when-not/      ← ✅ written
├── module-1-domain-modelling/       ← ✅ written
├── module-2-aggregates-and-consistency/       ← scaffolded
├── module-3-persistence-without-leakage/      ← scaffolded
├── module-4-domain-exception-trees/           ← scaffolded
├── module-5-living-with-a-framework/          ← scaffolded
└── module-6-capstone/                         ← scaffolded
```

Each lesson keeps the shape that worked in the previous course, with one change:

```
lesson-X.Y-name/
├── README.md          ← the theory
├── examples/          ← runnable, numbered. Run them, do not skim them.
├── challenge/
│   ├── CHALLENGE.md   ← the brief in prose
│   ├── checks.php     ← the brief stated precisely. Read it FIRST.
│   ├── starter.php    ← run this to see how you are doing
│   └── solution/      ← the reference implementation
└── quiz/QUIZ.md       ← answer key at the bottom
```

**The change is `checks.php`.** The previous course judged your work by comparing printed text
against an expected-output block written by hand — which failed in both directions, marking correct
answers wrong and wrong answers correct. Here, every challenge ships a fixed list of assertions.
They run against your code and print `ACCEPTANCE: all checks passed` or a list of what is still
failing. Your `echo` statements cannot fail you, and they cannot fool the checker either.

Read `checks.php` before you start. It is not hidden from you and it is not meant to be — working
out what a system must do from its assertions is the same skill you use when you inherit a codebase
with a test suite and no documentation.

---

## 🗺️ Roadmap

```
[Module 0: When — and When Not — to Do This]    ← the gate. Do not skip it.
         ↓
[Module 1: Domain Modelling]           value objects, entities, invariants
         ↓
[Module 2: Aggregates & Consistency]   boundaries, transactions, domain events
         ↓
[Module 3: Persistence Without Leakage]  repositories, the mapping problem
         ↓
[Module 4: Domain Exception Trees]     typed failures, API boundary mapping
         ↓
[Module 5: Living With a Framework]    what transfers into Laravel/Symfony, and what does not
         ↓
[Capstone]                             one domain, end to end
```

---

## 📊 Completion table

| Module | Lessons | Challenges | Quizzes | Built | Status |
|--------|:-------:|:----------:|:-------:|:-----:|--------|
| 0 — When and When Not | 2 | 2 | 2 | ✅ | `[ ] Not started` |
| 1 — Domain Modelling | 4 | 4 | 4 | ✅ | `[ ] Not started` |
| 2 — Aggregates & Consistency | 4 | 4 | 4 | — | `[ ] Not built` |
| 3 — Persistence Without Leakage | 4 | 4 | 4 | — | `[ ] Not built` |
| 4 — Domain Exception Trees | 4 | 4 | 4 | — | `[ ] Not built` |
| 5 — Living With a Framework | 2 | 2 | 2 | — | `[ ] Not built` |
| Capstone | 1 | 1 | 1 | — | `[ ] Not built` |
| **Total** | **21** | **21** | **21** | | |

Roughly one challenge per lesson. DDD is judgement, and judgement comes from repetition.

---

## 🛠️ Target environment

| Item | Value |
|------|-------|
| PHP | **8.5** — verified on 8.5.7. Degrades on 8.4: three examples that demonstrate 8.5 syntax will not parse, and `#[\NoDiscard]` goes inert. Below 8.4, nothing loads. |
| Runtime | Laravel Herd, CLI only. Nothing is served over HTTP. |
| Location | `~/Herd/php8.5-domain-architecture` |
| Dependencies | None for Modules 0–2. PHPUnit from Module 3; Slim 4 + PHP-DI 7 for the capstone. |
| Domain layer | **Zero framework dependencies.** Enforced by a test, not by good intentions. |

If `php check.php` reports the wrong PHP version, check Herd's **global** override as well as the
per-site version. A global override silently wins over the project setting, and it is exactly what
put the first verification run of this course on 8.4 instead of 8.5.

---

## How this course was built, and what that means for you

The blueprint for this course opens with an instruction that is worth repeating to you, because it
tells you what to trust:

> The OOP Mastery course was written and never executed. It shipped with reference solutions that
> failed their own assertions, expected-output blocks that had never matched reality, a solution
> referencing twelve classes it did not declare, and fabricated syntax for a PHP 8.5 feature. Every
> one of those survived because nothing ever ran the code.

Two things follow.

**First, `verify.php` runs every reference solution against its own checks, every time.** Not a
sample — all of them. A solution that fails its own assertions cannot survive a single verify run,
which is the only durable defence against the failure above.

**Second, when a challenge fails, the checker runs the reference solution before blaming you.** If
the solution fails too, you are told plainly that it is a course bug, it is not counted against
you, and it does not block you. You should never spend an evening trying to satisfy a broken
assertion.

If you find one anyway, that is a real finding. Note it in the *Notes & Sticking Points* table at
the foot of `PROGRESS.md`.

---

## What this course is for

**After PHP 8.5 OOP Mastery**, you can build systems where swapping MySQL for PostgreSQL, or SMTP
for an API, is a config change rather than a rewrite — and you can prove it with tests.

**After this course**, you can take a tangled set of real business rules — the ones people argue
about in meetings — and express them in code that refuses to represent an invalid state. The
business logic becomes independent of storage and transport, so it can be tested in isolation and
survives the framework being replaced.

That combination matters most where incorrect state is expensive rather than merely annoying:
financial and billing systems with calculation and audit requirements, logistics and workshop
management where a record must stay consistent across many operators, insurance and claims
processing, and regulated domains where "the system allowed it" is not an acceptable answer.

Realistically: this is the skill set that separates a developer who implements specifications from
one who is trusted to *model the problem*. It will not, by itself, make anyone an architect — that
also requires having been wrong at scale a few times. But it is the technical half of the job, and
it is the half you can actually study.

---

*Start with `php check.php`, then Module 0. Module 0 is two short lessons and no code, and it is
the most important thing in the course.*
