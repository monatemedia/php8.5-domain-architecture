# Quiz — Lesson 4.1: Two Kinds of Failure
> Closed book. **20 questions. Threshold: 15.**

---

## Section A — Multiple choice

**Q1.** An API returns 500 when a user tries to invoice an incomplete job card. The root cause is
usually:

- A) A misconfigured web server.
- B) The boundary having no type to distinguish a business refusal from a crash, because both
  arrived as a bare `\Throwable`.
- C) A missing route.
- D) A database timeout.

---

**Q2.** The three categories of failure are:

- A) Fatal, recoverable, warning.
- B) Expected (the business has an answer), exceptional (nobody has an answer), and programmer
  error (the code is wrong).
- C) 4xx, 5xx and 2xx.
- D) Checked, unchecked, runtime.

---

**Q3.** The category people most often forget is:

- A) Expected.
- B) Exceptional.
- C) Programmer error — and forgetting it produces a `catch (\Throwable)` that swallows real bugs.
- D) None; two categories are sufficient.

---

**Q4.** *"Would retrying the identical request, unchanged, ever succeed?"* If **yes**, the failure is
probably:

- A) Expected.
- B) Exceptional — the machinery might recover.
- C) Programmer error.
- D) Not a failure at all.

---

**Q5.** A card declined by the bank is:

- A) Exceptional — it came from an HTTP client, which is infrastructure.
- B) Expected — the bank understood the request and refused it for a business reason the caller can
  act on.
- C) Programmer error.
- D) It depends on the acquirer.

---

**Q6.** In SPL, `\DomainException` extends:

- A) `\RuntimeException`.
- B) `\LogicException` — a branch documented as errors that should lead to a fix in your code, which
  a broken business rule is not.
- C) `\Error`.
- D) `\Throwable` directly.

---

**Q7.** `\DomainException` and `\InvalidArgumentException` are:

- A) Parent and child.
- B) Siblings whose only common ancestor is `\LogicException`, which is also the ancestor of
  exceptions thrown throughout `vendor/`.
- C) Unrelated.
- D) Both under `\RuntimeException`.

---

**Q8.** The reason a marker **interface** is used rather than a common base class is:

- A) Interfaces are faster.
- B) PHP allows one parent and many interfaces, and SPL has already spent the inheritance slot on
  two incompatible branches.
- C) Abstract classes cannot be thrown.
- D) Convention.

---

**Q9.** `interface WorkshopFailure extends \Throwable` — the `extends \Throwable` part:

- A) Is optional decoration.
- B) Lets handlers call `getMessage()`/`getPrevious()` on what they caught, and makes PHP refuse at
  compile time to let a non-throwable class implement it.
- C) Is required for all interfaces.
- D) Makes it catchable, which it otherwise would not be.

---

**Q10.** `catch (\Exception $e)` does **not** catch:

- A) `\DomainException`.
- B) `\PDOException`.
- C) `\TypeError` — `\Error` is a sibling of `\Exception`, not a descendant.
- D) `\RuntimeException`.

---

**Q11.** `Infrastructure\StorageFailure` deliberately does not implement `WorkshopFailure` because:

- A) It is in a different namespace.
- B) It must **not** be caught by the clause meaning "the business refused something" — it is a 500
  and it should stay one.
- C) Interfaces cannot cross namespaces.
- D) It would create a circular dependency.

---

**Q12.** The original driver exception is kept on `getPrevious()` rather than merged into the
message because:

- A) Messages have a length limit.
- B) The operator needs `SQLSTATE[HY000]` and the user must never see it — the chain reaches one and
  a `getMessage()` renderer reaches the other.
- C) `previous` is faster.
- D) PHP requires it.

---

**Q13.** In `GuardedJobCards`, a caught `WorkshopFailure` is re-thrown as the same object rather
than rewrapped because rewrapping:

- A) Is slower.
- B) Reports the decorator's file and line in every stack trace, flattens the subclass, and discards
  structured context.
- C) Is not allowed in PHP.
- D) Would break the interface.

---

**Q14.** If the `catch` clauses in the decorator are written in the wrong order — `\Exception`
first — the symptom is:

- A) A fatal error.
- B) Every domain failure is translated into a `StorageFailure`, because every one of them is also
  an `\Exception`.
- C) Nothing is caught.
- D) Only `\Error`s are affected.

---

**Q15.** The decorator's constructor is typed `JobCards $inner` rather than `SqliteJobCards $inner`
because:

- A) It is shorter.
- B) It makes the decorator work on every implementation, present and future — Lesson 3.4's
  substitutability being spent rather than admired.
- C) PHP forbids concrete types in constructors.
- D) In-memory repositories cannot be decorated.

---

## Section B — Short answer

**Q16.** `InMemoryJobCards::add()` originally threw `new \DomainException('Job card ... is already
in this collection.')`. Explain in two sentences why the decorator translated that into a
`StorageFailure`, and why that was the decorator behaving correctly.

---

**Q17.** Give one failure from each of the three categories, drawn from a domain that is **not** a
vehicle workshop, and state which test told you the category.

---

**Q18.** A colleague widens the decorator's second clause from `catch (\Exception)` to
`catch (\Throwable)` "so nothing escapes." Describe the concrete incident this causes: what breaks,
what the alert says, and where the on-call engineer wastes the first hour.

---

**Q19.** The README says the marker interface "costs one `implements` clause, applied where the
class is declared" and prefers that to wrapping. State the one thing wrapping gives you that the
marker does not, and why it was not worth it here.

---

**Q20.** Nothing forces a new domain exception to implement `WorkshopFailure`. Describe how you
would find the ones that forgot, and say whether your method belongs in a test, a static analyser, or
a code review.

---
---

# Answer key

**Q1 — B.** The boundary can only be as precise as the types reaching it. Every fix that starts with
the middleware ends up back at the throw site.

**Q2 — B.**

**Q3 — C.** And the cost is specific: `catch (\Throwable)` turns a `TypeError` with a file and line
number into "users occasionally see an error."

**Q4 — B.** The retry test separates (b) from the rest. It does not separate (a) from (c) — that is
what the rewrite test is for.

**Q5 — B.** The most instructive example in the lesson, because the code that handles it lives
inside an HTTP client and *feels* infrastructural. A gateway **timeout** on the same call is (b):
same client, same method, different answer to "would a retry work?"

**Q6 — B.** The name is right and the parent is wrong, and there is nothing you can do about either.
Using `\DomainException` for business rules anyway is a defensible convention; it is not a
foundation.

**Q7 — B.**

**Q8 — B.**

**Q9 — B.** Both halves matter. The second is what stops the marker drifting into meaning something
else.

**Q10 — C.** And that is correct behaviour, not a gap to be plugged.

**Q11 — B.** The check asserting this is one of the few in the course whose failure means you have
misunderstood rather than mistyped.

**Q12 — B.**

**Q13 — B.** The subclass point is the expensive one: a `JobCardIsTerminal` rewrapped as a generic
failure reaches Lesson 4.4's boundary as a 500 instead of a 409, and no type checker notices.

**Q14 — B.** Note the diagnostic shape: the pass-through checks and the translation checks fail
*together*, which looks like two bugs and is one.

**Q15 — B.**

**Q16.** Model answer: a bare `\DomainException` carries no marker, so it is indistinguishable from
one thrown by any package in `vendor/` — the decorator had nothing to go on but a type that means
nothing in particular. That is correct behaviour, because the alternative is trusting an SPL class
that thousands of unrelated libraries also throw; the fix belongs at the throw site, which is why the
class got a name.

**Q17.** Any consistent set. One good answer, from e-commerce:

- (a) *Coupon expired* — rewrite test: yes, the customer can order without it.
- (b) *Redis connection refused* — retry test: a retry might succeed.
- (c) *`Argument #1 must be of type Cart, null given`* — neither test offers a way out; nothing the
  caller or the infrastructure does helps.

Credit depends on naming which test produced the answer, not on the examples.

**Q18.** Model answer: a mapper bug — a `TypeError` or a call on `null` — is caught and rethrown as
`StorageFailure`. The alert reads "storage failed during get", so the on-call engineer checks
connection pools, disk and replica lag, all of which are healthy. The stack trace in the log points
at the decorator, and the real one is on the `previous` chain, which the alert template does not
render. Nothing is wrong with the database and nothing will be found until somebody reads the code.

**Q19.** Wrapping gives you a real base **class**, which can hold shared behaviour — a `context()`
method, a default code, a constructor that enforces something. An interface cannot carry state or
implementation.

That was not worth it here because 4.1 needs nothing shared; it needs a catchable type. Note that
Lesson 4.2 does want shared behaviour and gets it — by adding abstract classes *underneath* the
marker, one per SPL branch, rather than replacing it. The marker stays because it is the only thing
that spans both.

**Q20.** A test, and specifically a **reflection-driven** one: scan every class in
`domain/workshop/`, keep those that are `\Throwable`, and assert each implements `WorkshopFailure`.
It is a handful of lines and it runs in the suite.

Static analysis is the weaker answer here and it is worth knowing why: PHPStan and Psalm can be made
to enforce this with a custom rule, but "every throwable in this directory implements that
interface" is a project convention rather than a type error, so you are writing a plugin either way
— and the test version reads more like the rule it enforces.

Code review is the wrong answer. It is the mechanism that already failed, every time this has ever
gone wrong.

The capstone builds exactly this scan for a different rule: that `domain/` imports nothing from
`infrastructure/`.

---

## Scoring

| Score | Verdict |
|-------|---------|
| 18–20 | Go to 4.2. |
| 15–17 | Pass. Re-read section 4 before 4.2 — the SPL hierarchy is assumed from here. |
| 11–14 | Re-run examples 01 and 02, then retake. |
| 0–10 | Re-read the lesson. Section 2 is the one everything else rests on. |
