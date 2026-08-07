# Verification record

> **Status: green.** Verified on PHP 8.5.7, 7 August 2026. Everything below has been executed, not
> just read.

---

## The result

Re-verified after each lesson lands. As of Lesson 2.2:

| Check | Result |
|-------|--------|
| PHP version | 8.5.7 |
| Language-feature probes | **10 / 10** — including `clone($obj, [...])`, static asymmetric visibility, `#[\NoDiscard]` |
| Files parsing | **72 / 72** |
| Reference solutions passing their own acceptance checks | **6 / 6** |
| Earlier lesson specs still passing against the newest code | **6 / 6** |
| Examples running to exit code 0 | **20 / 20** |
| `check.php` | stops correctly at Lesson 0.1 |

The only warning is `vendor/` not present, which is correct: Modules 0, 1 and 2 have no
dependencies at all, deliberately. Run `composer install` before Module 3.

---

## Why this file exists

The course was written in an environment with **no PHP available**, so at the point of delivery none
of it had been executed. That is precisely the gap that produced the previous course's entire
remediation bill: reference solutions that failed their own assertions, expected-output blocks that
had never matched reality, fabricated syntax for a PHP 8.5 feature. Every one of those survived
because nothing ever ran the code.

So the first thing that happened here was that it ran. Two things came out of it, and both are worth
recording.

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

### 3. A version assumption that was too absolute

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
assertions. To execute all fourteen:

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
