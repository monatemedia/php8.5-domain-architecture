# Verification record

> **Status: green.** Verified on PHP 8.5.7, 7 August 2026. Everything below has been executed, not
> just read.

---

## The result

Re-verified after each lesson lands. As of Lesson 3.4 — Modules 0 to 3 complete:

| Check | Result |
|-------|--------|
| PHP version | 8.5.7 |
| Language-feature probes | **10 / 10** — including `clone($obj, [...])`, static asymmetric visibility, `#[\NoDiscard]` |
| Files parsing | **132 / 132** |
| Reference solutions passing their own acceptance checks | **12 / 12** |
| Earlier lesson specs still passing against the newest code | **12 / 12** |
| Examples running to exit code 0 | **40 / 40** |
| `check.php` | stops correctly at Lesson 0.1 |

No warnings. `composer install` has been run — Module 3 is the first that needs it, and
`pdo_sqlite` is present for Lesson 3.4's integration tests.

---

## Why this file exists

The course was written in an environment with **no PHP available**, so at the point of delivery none
of it had been executed. That is precisely the gap that produced the previous course's entire
remediation bill: reference solutions that failed their own assertions, expected-output blocks that
had never matched reality, fabricated syntax for a PHP 8.5 feature. Every one of those survived
because nothing ever ran the code.

So the first thing that happened here was that it ran, and it has been re-run after every lesson
since. Four things came out of that, and all four are worth recording.

### 1. A false green in `verify.php` itself

The `#[\NoDiscard]` probe reported **OK on PHP 8.4**, where the attribute does nothing at all.

Attributes have parsed on every version since 8.0 and an unknown one is silently ignored — so
writing `#[\NoDiscard]` and checking that the function still returns the right value proves only
that the function works. The probe now checks that the `NoDiscard` **class** exists, which happens
only from 8.5.

That bug was sitting in the one file whose job is to prevent false greens. It would never have been
found by reading.

### 2. A regression test that was not testing regressions

Lessons 2.1 and 2.2 each change a class an earlier lesson created — `LabourLine` gains an identity,
then `CourtesyVehicle` does. Both challenges end with "re-run the earlier lesson's checks", and both
times that was done by running the earlier lesson's `solution/run.php`.

Which proved nothing. That runner registers the earlier lesson's **own** solution directory first,
so it loaded the old copy of the class and confirmed that 1.1 satisfies 1.1.

The student has **one** copy, in `domain/workshop/`. The claim the course actually makes is that the
newest version of every class still satisfies every earlier specification, and nothing was checking
it. `regression.php` now does: every solution directory registered newest-first, then each earlier
lesson's `checks.php` run against the result, one process per lesson. It is step 5 of `verify.php`.

Both additive changes turned out to be genuinely additive. That was luck plus care, and neither is a
test.

### 3. A check that could not tell a dependency from a sentence

Lesson 3.1 asserts that no domain file references the `Infrastructure` namespace. The first version
searched the raw source — and failed its own reference solution, because the doc block on
`JobCards` explains the inversion in prose: *"the domain declares what it needs; Infrastructure
supplies it."*

Describing the rule is not breaking it. A check that greps a whole file punishes a class for being
documented, and the fix is to strip comments first — which `no_float_in()` had been doing since
Lesson 1.2 for exactly the same reason, and which the new check simply failed to reuse.

Both now share `class_source_without_comments()` in `lib/acceptance.php`.

That one cost a single `verify.php` run, which is the cheapest a course bug has ever been found in
this project. It would have shipped in the previous course.

### 4. A version assumption that was too absolute

The first run was on 8.4.22, because Herd's **global** PHP override silently wins over the per-site
setting. The harness treated that as six failures. In fact only three files were affected — all
three examples whose entire purpose is to demonstrate 8.5 syntax — and every challenge and every
reference solution ran fine.

`verify.php` now separates *"this is broken"* from *"your runtime is a version behind"*, and reports
the second as unavailable rather than failed. `check.php` no longer hard-stops below 8.5 either. The
failure count now means what it says.

---

## Re-running it

Any time something behaves unexpectedly, or after any change to the course:

```bash
php verify.php      # is the course sound? six steps, including the regression sweep
php regression.php  # just the regression sweep, when it is what failed
php check.php       # environment, integrity, and where you are
```

`verify.php` runs **every reference solution against its own acceptance checks on every
invocation**, and then runs **every earlier lesson's spec against the newest version of every
class**. Between them: a solution that fails its own assertions cannot survive a single run, and a
lesson that quietly breaks an earlier one cannot either.

The examples are only syntax-checked by `verify.php`, because they print prose rather than
assertions. To execute all of them:

```bash
for f in module-*/lesson-*/examples/*.php; do
  php "$f" > /dev/null 2>&1 || echo "FAILED: $f"
done
```

Silence is success.

---

## If you find something anyway

Log it in the *Notes & Sticking Points* table at the foot of `PROGRESS.md` — specifically anything
that looks like a **course bug**: a reference solution failing its own checks, an example that will
not run, a claim in a README you cannot reproduce.

When a challenge fails, `check.php` runs the reference solution before blaming you. If that fails
too, you are told plainly that it is a course bug, it is not counted, and it does not block you. You
should never spend an evening trying to satisfy a broken assertion.

---

## Where to start

1. `COURSE_PHILOSOPHY.md` — six rules, twenty minutes
2. `DOMAIN.md` — the workshop; keep it open
3. `module-0-when-and-when-not/lesson-0.1-the-cost-of-ddd/README.md`

Module 0 is two lessons and no code, and it is the most important thing in the course. It is also
the one you will be most tempted to skip.
