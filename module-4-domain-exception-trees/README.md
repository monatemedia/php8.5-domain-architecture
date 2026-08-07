# Module 4 — Domain Exception Trees

> **Built and verified on PHP 8.5.7.** Four lessons, four challenges, four quizzes, and Gate 4 in
> `GATES.md`.

| Lesson | Subject | You build |
|--------|---------|-----------|
| [4.1](lesson-4.1-two-kinds-of-failure/) | Two kinds of failure — and the third everyone forgets | `WorkshopFailure`, `JobCardAlreadyExists`, `StorageFailure`, `GuardedJobCards` |
| [4.2](lesson-4.2-the-exception-tree/) | The exception tree — catch broadly, throw narrowly | `CarriesContext`, two abstract roots, one node, nine leaves |
| [4.3](lesson-4.3-exceptions-vs-results/) | Exceptions vs result types — ask, or act | `Refusal`, `Decision` |
| [4.4](lesson-4.4-the-api-boundary/) | The API boundary — RFC 9457 Problem Details | `ProblemDetails`, `ProblemDetailsMapper` |

---

## What the module is for

The `\InvalidArgumentException` / `\DomainException` split you have been using since Lesson 1.1 is
the seed of the whole tree. This module grows it, and then spends it.

The observable target is the thing every PHP API does wrong:

```
HTTP/1.1 500 Internal Server Error
```

…returned when a user tried to break a business rule. Nothing went wrong. The server understood the
request, applied a rule the business wrote down, and refused. That is the system **working**.

The fix is not middleware. It is the **type of the thing you throw** — middleware can only ever be as
precise as the exceptions reaching it. So the module works backwards from the boundary to the throw
site, and only reaches the boundary in 4.4, by which point there is nothing interesting left to
decide.

---

## The four arguments, one per lesson

**4.1 — Three categories, not two.** Expected (the business has an answer), exceptional (nobody
does), and programmer error (the code is wrong). Everyone remembers the first two. Forgetting the
third produces a `catch (\Throwable)` that swallows real bugs into a tidy 500.

**4.2 — Depth where depth buys something.** One class with a string code puts a `switch` inside a
`catch`. Thirty flat classes make it impossible to catch a subset. Three widths of `catch`, each one
a clause somebody actually writes — and a rule for adding nodes that stops the tree growing for the
sake of symmetry.

**4.3 — Ask, or act.** An exception is the right answer to a committed action and the wrong answer
to a question. Result types are right for the question and ruinous everywhere else. The lesson is
where the line is, and how to have both without maintaining every rule twice.

**4.4 — Nothing internal reaches the wire.** RFC 9457, status codes that mean what they say, and a
leak test that inspects the decoded body value by value — including a check that the detector itself
can fail.

---

## What accumulates

By the end of the module `domain/workshop/` holds a fifteen-class failure vocabulary, and
`infrastructure/` holds every opinion about what those failures mean over HTTP.

The thing to notice is Lesson 4.4's file list: **two new classes, both in `infrastructure/`, and
nothing changed in the domain.** Everything the boundary needed was already there as `failureCode()`
and `context()`, and neither of those knows what HTTP is.

That is the result. The mapper is only what demonstrates it.

---

## Where else this shows up

Every row is the same shape in a different vocabulary:

| Domain | Expected (a) | Exceptional (b) | 409 | 422 |
|--------|--------------|-----------------|-----|-----|
| E-commerce | card declined, out of stock | gateway timeout | basket already checked out | coupon malformed |
| Insurance | policy lapsed, outside cover | rating service down | claim outside cover period | amount not a number |
| Payments | insufficient funds | acquirer unreachable | already captured | currency unsupported |
| Booking | seat taken, fare rules | GDS dropped | seat taken since page load | date in the past |

**A card decline is category (a)**, and it is the most instructive example in the module: the code
handling it lives inside an HTTP client and *feels* infrastructural, but the bank understood the
request and refused it for a business reason. A gateway *timeout* on the same call, the same
afternoon, is (b) — because the answer to "would a retry work?" is different.

---

## Running it

```bash
php verify.php      # every reference solution, plus the regression sweep
php check.php       # where you are
php regression.php  # just the sweep, when it is what failed
```

Module 4 needs no dependencies of its own. Composer is still required from Module 3 onward.
