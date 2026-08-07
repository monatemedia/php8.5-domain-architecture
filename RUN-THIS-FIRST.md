# Verification record

> **Status: green.** Verified on PHP 8.5.7, 7 August 2026. Everything below has been executed, not
> just read.

---

## The result

Re-verified after each lesson lands. As of the capstone — the course is complete:

| Check | Result |
|-------|--------|
| PHP version | 8.5.7 |
| Language-feature probes | **10 / 10** — including `clone($obj, [...])`, static asymmetric visibility, `#[\NoDiscard]` |
| Files parsing | **see the run** |
| Reference solutions passing their own acceptance checks | **19 / 19** |
| Earlier lesson specs still passing against the newest code | **19 / 19** |
| Examples running to exit code 0 | all of them |
| Domain files scanned for infrastructural imports | **87, clean** |
| `check.php` | stops correctly at Lesson 0.1 |

No warnings. `composer install` has been run — Module 3 is the first that needs it, and
`pdo_sqlite` is present for Lesson 3.4's integration tests.

One example prints a warning on purpose: Lesson 4.3's `03-what-it-costs.php` discards a
`#[\NoDiscard]` return value so you can watch PHP object to it. That is the output, not a defect,
and the exit code is still 0.

---

## Why this file exists

The course was written in an environment with **no PHP available**, so at the point of delivery none
of it had been executed. That is precisely the gap that produced the previous course's entire
remediation bill: reference solutions that failed their own assertions, expected-output blocks that
had never matched reality, fabricated syntax for a PHP 8.5 feature. Every one of those survived
because nothing ever ran the code.

So the first thing that happened here was that it ran, and it has been re-run after every lesson
since. Eight things came out of that, and all eight are worth recording. Four came from Modules 4
and 5, and every one was found by the syntax sweep or by a reference solution failing its own
checks — none by reading.

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

### 4. Two arrow functions that could not return void

Lesson 4.3's example 02 wrote its test callables as `static fn(string $s): void => $x->complete();`.
Arrow functions **always** return their expression, so a `void` return type on one is a fatal error:
*"A void function must not return a value."*

Three of them, in one file, and every one was written in the same minute. It is the kind of mistake
that only exists because arrow functions look like short closures and are not, and the only thing
that would ever have found it is running the file.

### 5. A property name that SPL had already taken

Lesson 4.2's example 01 builds the "one exception class carrying a string code" design, to argue
against it. The obvious spelling of that class is:

```php
public function __construct(string $message, public readonly string $code)
```

...which is a fatal error, because `\Exception` already declares `protected int $code` and a
subclass may not redeclare a non-readonly property as readonly.

Both `$code` and `$message` are taken on every exception in PHP. That turned out to be worth keeping
rather than merely fixing — it is a small piece of evidence for why the tree in Lesson 4.2 carries
`failureCode()` as a **method** rather than reusing a property name SPL owns — so the example now
says so in a doc block, and the README says it too.

### 6. A leak test that failed on the correct answer

Lesson 4.4 asserts that nothing internal reaches the wire, by searching problem bodies for forbidden
substrings. The first version searched the raw JSON for a backslash and for the word `Error`.

Both were wrong, in opposite directions:

- **`Error`** appears in `"title": "Internal Server Error"` — the HTTP status phrase, and exactly
  what RFC 9457 asks for when `type` is `about:blank`. The check failed the right answer.
- **A backslash** appears in any JSON-encoded body whose message quotes something: `"Front pads"`
  becomes `\"Front pads\"`. Without `JSON_UNESCAPED_UNICODE` an em dash does the same.

The fix was to inspect the **decoded structure** rather than the JSON text, so an encoder's choices
can neither raise a false alarm nor hide a real leak — `Workshop\JobCard` is a backslash in the
decoded string whatever the encoder did to it — and to drop `Error` from the list.

A leak test that fires on correct output is a leak test somebody switches off. The suite now also
feeds the detector a poisoned body and asserts it fires, because a test that cannot fail is not a
test.

### 7. A source grep that could not tell PartStockRow from PartStock

Lesson 5.2 asserts that two legacy services were left alone — that neither was converted to use the
extracted aggregate. The first version searched their source for `PartStock`:

```php
!str_contains(class_source_without_comments(PickingService::class), 'PartStock')
```

Both services take a `PartStockRow $row` parameter, and `PartStockRow` **contains** `PartStock`. So
the check reported that two untouched files had been converted, and failed its own reference
solution.

The fix is a word boundary — `preg_match('/\bPartStock\b/', ...)` — which does not match
`PartStockRow`, because `k` and `R` are both word characters.

**This is the third time this project has been bitten by the same shape of mistake.** Lesson 3.1's
Rule 1 check could not tell a dependency from a sentence describing one; `no_float_in()` had already
solved that exact problem a module earlier and nobody reused it; and now a substring test cannot tell
an identifier from a longer identifier containing it.

The rule, written down properly this time: **a check that reads source is a parser with the parsing
left out.** If it is going to make a decision, give it enough context to make it correctly — comments
stripped, identifiers bounded — or one day it will fail something that is right.

Found by the reference solution failing its own checks, which is the one signal in this harness that
cannot be argued with.

### 8. A version assumption that was too absolute

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
