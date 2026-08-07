# Quiz — Lesson 1.2: Value Objects
> Closed book. **20 questions. Threshold: 15.**
> Question 18 is the one most people get wrong. It is not a trick.

---

## Section A — Multiple choice

**Q1.** The three defining properties of a value object are:

- A) Final, readonly, and typed.
- B) Immutability, structural equality, self-validation.
- C) Immutability, a private constructor, and an `equals()` method.
- D) No dependencies, no setters, no inheritance.

---

**Q2.** Money should be stored as:

- A) `float`, rounded on output.
- B) `string`, to preserve exactness.
- C) `int` in the currency's smallest unit.
- D) `float`, with a precision constant.

---

**Q3.** `Currency` is an enum with a `minorUnits()` method rather than a plain string because:

- A) Enums are faster.
- B) The set is closed *and* the decimal precision is knowledge about the currency.
- C) Strings cannot be compared reliably.
- D) It is required by the type system.

---

**Q4.** A mutable "value object" used as a running total, snapshotted into an array after each
addition, produces:

- A) A memory leak.
- B) An array of identical values — every earlier snapshot rewritten by every later change.
- C) A type error.
- D) Correct behaviour, since the totals are cumulative anyway.

---

**Q5.** Relying on `==` rather than an explicit `equals()` is a mistake because:

- A) `==` is slower on objects.
- B) `==` compares object identity.
- C) `==` compares the properties you happened to store; it never lets you decide what equality
  *means*.
- D) `==` does not work on `readonly` classes.

---

**Q6.** Why is `Money`'s constructor private?

- A) `readonly` classes require it.
- B) So the guards in `of()` cannot be skipped — including by a test factory or a mapper.
- C) To prevent inheritance.
- D) To allow multiple constructors.

---

**Q7.** In PHP 8.5, `clone($object, ['prop' => $value])`:

- A) Is written with a `with` keyword.
- B) Is a call, and can only replace a `readonly` property from inside the declaring class.
- C) Bypasses property `set` hooks.
- D) Works only on `readonly` classes.

---

**Q8.** `#[\NoDiscard]` exists to catch:

- A) Unused parameters.
- B) A wither whose return value is thrown away — a no-op that reads like a mutation.
- C) Methods that return `void`.
- D) Deprecated method calls.

---

**Q9.** Which of these should **not** be a value object?

- A) `Money`
- B) `RegistrationNumber`
- C) `FaultDescription`, whose only rule is "not empty"
- D) `LabourUnits`, which rounds up to tenths with a minimum

---

**Q10.** In the challenge, a negative amount throws `\InvalidArgumentException` and a currency
mismatch throws `\DomainException`. The reasoning is:

- A) Currency mismatches are more serious.
- B) A negative amount is a malformed argument; a mismatch has two valid arguments and it is the
  *relationship* the business refuses.
- C) `DomainException` is the parent of `InvalidArgumentException`.
- D) It is arbitrary and the course admits it is arbitrary.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 11 | A value object that can be constructed in an invalid state is still a value object, just a weak one. | |
| 12 | `readonly` and property `set` hooks can be combined on the same property. | |
| 13 | Normalising at construction rather than at each comparison removes an entire class of bug. | |
| 14 | Every domain concept should be a value object. | |
| 15 | An enum is often a cheaper and stronger choice than a value object when the valid values are a fixed list. | |

---

## Section C — Short answer

**Q16.** Explain, in two sentences, why `Money` has no `divide()` method in the reference solution.

*Your answer:*

---

**Q17.** `Money::of(4500, Currency::EUR)` and `Money::of(4500, Currency::JPY)` both hold `4500`. One
is 45 euros, one is 4,500 yen. Is `Money` therefore an unsafe abstraction? Argue both sides.

*Your answer:*

---

**Q18.** A colleague proposes a `CustomerNote` value object wrapping a string, enforcing "not empty"
and "at most 2,000 characters". They point out — correctly — that the 2,000-character limit is
currently checked in three places and one of them says 1,000. Should they build it? Give your
answer *and* the strongest counter-argument to your own position.

*Your answer:*

---

**Q19.** Your `RegistrationNumber` refuses `'12345'` because it has no letter. Name a plausible real
case this rejects wrongly. What does that tell you about validation written from a specification
rather than from data?

*Your answer:*

---

**Q20.** The workshop issues credit notes, whose amount is conceptually negative — but `Money`
refuses negative amounts. Give two ways to model a credit note, and say which you prefer and why.

*Your answer:*

---
---

<details>
<summary><strong>Answer key</strong></summary>

**Q1 — B.** Immutability, structural equality, self-validation. A is a list of implementation
techniques, which is a different thing: `final readonly` is *how* you usually get immutability in
PHP, not what immutability is.

**Q2 — C.** Integer minor units. `int` arithmetic is exact; `float` accumulates error that shows up
as an unexplained discrepancy someone reconciles by hand. `string` is used by some libraries for
arbitrary precision, which is a legitimate different answer for scientific quantities, but overkill
for money that fits comfortably in `PHP_INT_MAX`.

**Q3 — B.** Both halves matter, and the second is the one that bites: how many decimal places a
currency has is knowledge about the currency. A `/ 100` elsewhere is correct until the first JPY
transaction and then wrong by a factor of a hundred.

**Q4 — B.** One object, stored three times. Every "snapshot" is the same instance, so each addition
rewrites all of them. The production symptom is historical records that change when new ones are
added — very hard to trace, because the code that looks wrong is correct.

**Q5 — C.** `==` on objects does compare property values, so it accidentally works for simple cases —
which is worse than failing, because it works until equality needs to mean something specific.
`RegistrationNumber` needs equality by normalised value, and only an explicit `equals()` lets you
decide that. (B describes `===`, not `==`.)

**Q6 — B.** A public constructor makes the guards optional. The first place they get skipped is a
test factory, then a mapper, then an import script. A private constructor also lets you have several
named factories with different rules, where a constructor can have only one signature.

**Q7 — B.** It is a call, not a keyword, and a `readonly` property can only be replaced from inside
the declaring class — which is why every wither is a method on the class. C is backwards: `set`
hooks *do* run on cloned values.

**Q8 — B.** The silent no-op. `$price->withAmount(9999);` is correct code that achieves nothing and
reads exactly like the mutable version somebody used to write.

**Q9 — C.** "Not empty" does not justify a type. You get a file, a name, a mapping, a conversion at
every boundary and a test file, in exchange for one guard you could write where it is used.

**Q10 — B.** Malformed argument versus refused relationship. Note the course also says out loud that
reasonable people put all four in one category, and that consistency matters more than which — but D
overstates it: a reason is given, and it is not arbitrary.

**Q11 — FALSE.** It is a struct with a nice name. The whole value of the type is that holding one
means the rules held; if it can be built invalid, holding one means nothing and every consumer must
re-check.

**Q12 — FALSE.** A `set` hook is a write, and `readonly` forbids writes after construction. That is
a real trade-off: hooks give normalisation on every assignment, `readonly` guarantees there is only
one assignment. `readonly` plus a static factory gets both, which is what the challenge uses.

**Q13 — TRUE.** The bug class is "one of the eleven comparison sites normalised differently".
Removing the eleven sites removes the class.

**Q14 — FALSE.** Section 6 of the README, and Rule 6 of the philosophy. This is the question Module
1 exists to stop you getting wrong.

**Q15 — TRUE.** And it is the most under-used option. An enum is less code, and the compiler
enforces the closed set for free.

**Q16** Dividing money raises a question the class cannot answer: where do the remainder minor units
go? EUR 10.00 split three ways gives 3.33 three times, which is EUR 9.99, and the missing cent is
found later by someone reconciling. The honest replacement is an allocation method that guarantees
the parts sum to the whole with a stated rule for the remainder — and *who gets the remainder* is a
business decision, so putting it in a value object would be hard-coding a policy.

**Q17** *For the objection:* the abstraction hides a factor-of-100 difference behind an identical
integer, so anyone reading `4500` in a debugger, a log or a database column learns nothing without
also seeing the currency. Bugs where a JPY amount is treated as cents are real, and the type does not
prevent them at the storage boundary — only inside the object.

*Against:* that is precisely why amount and currency are one object and never separable, which is
the abstraction working rather than failing. The alternative — a raw integer with the currency
somewhere else — has the identical ambiguity plus the ability to drift apart. `Money` cannot be
misread by any code that has the `Money`; the exposure is limited to the mapping boundary, which is
one place and testable.

*The honest conclusion:* the abstraction is safe within the domain and needs care at the edges,
which is a much better position than having no abstraction at all.

**Q18** **Yes, build it — but for the reason they gave, not the one they think.** "Not empty" and a
length cap are weak rules. What is not weak is *"one of the three places says 1,000"*: there is
already a live inconsistency, which means the rule has escaped and is drifting. That is the
signal from Lesson 0.1 — a rule enforced in several places, disagreeing — and it is what earns the
type, not the rule's complexity.

**The strongest counter-argument to my own answer:** the same fix is available for far less. One
`const MAX_NOTE_LENGTH` and one validation call at the single boundary where notes arrive removes
the inconsistency without a new type, a mapping, or a conversion at every edge. If notes arrive from
exactly one place, that is genuinely the better answer, and choosing the value object anyway is Rule
6 being ignored in the module about the patterns. The question that decides it is **how many
boundaries can create a note** — one, and use the constant; several, and build the type.

If you answered "no", and gave that argument, mark yourself correct. The failure is answering
without a counter-argument at all.

**Q19** Several countries issue plates that are entirely numeric, so a genuine registration would be
rejected as "no letter". More generally: the rule was written from a description of what plates look
like, not from a sample of the plates the business actually sees. It passes every test — because the
tests were written from the same description — and it fails on a real customer eighteen months
later, in a way that looks like a data problem rather than a validation bug. The lesson is that
validation rules are empirical claims about the world, and empirical claims should be checked
against the world before they become a `throw`.

**Q20** *Option 1 — a separate direction on the line.* Keep `Money` non-negative and let the thing
that holds it carry the sign: a `CreditNote` with a positive amount and its own type. Money stays
simple; the direction lives with the concept that has a direction.

*Option 2 — a signed `Money`.* Allow negatives and let a credit note be a negative amount. Fewer
types, and arithmetic across charges and credits just works.

*Preference, with the reason:* option 1. Option 2 makes every other use of `Money` weaker — a price,
a line total and a balance all become "possibly negative", so every consumer must consider a case
that cannot occur for them, and the negative-price bug the invariant was protecting against comes
back. The narrower type is worth the extra concept. Option 2 is defensible in an accounting context
where signed amounts genuinely are the domain's own model, and that is exactly the point: the right
answer depends on the business, not on the pattern.

</details>

---

**Score:** ____ / 20   ·   **Threshold: 15**
