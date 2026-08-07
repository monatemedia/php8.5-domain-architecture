# Lesson 4.3 — Exceptions vs Result Types

> **Module 4 · Lesson 3 of 4**
> Prerequisites: Lesson 4.2. The tree you built is what makes this lesson cheap.

---

## 1. The question this lesson exists to complicate

You have just spent a lesson building a nine-class exception tree. Now:

> **Should any of those have been exceptions at all?**

The honest answer is *some of them, sometimes, depending on who is asking* — and this lesson is
about turning that into something you can decide rather than argue about.

There is a genre of blog post that says exceptions are goto with a stack trace and every failure
should be a return value. There is another that says result types are error codes rebranded and
PHP is not Rust. Both contain real arguments and both overreach. What follows tries to be neither.

---

## 2. The one thing an exception cannot do

Start with the concrete case, because the general argument is much less useful.

A job card sits in **Awaiting Authorisation** with an outstanding item. Somebody tries to complete
it. It cannot be completed for **two** reasons:

1. There is work nobody has approved.
2. The transition table has no path from Awaiting Authorisation to Completed.

`complete()` throws the first and abandons the second. Not because it is badly written — because
unwinding carries **one object**. That is what `throw` means.

Now ask who wanted to know:

| Caller | Wants |
|--------|-------|
| A queue worker completing job cards in a batch | one reason; it is going to log it and move on |
| A form the service advisor is filling in | **every** reason, so they fix them in one pass |
| A UI deciding whether to grey out the Complete button | to know *before* trying, without an exception |
| An operator on the phone to a customer | every reason, in their words |

Three of those four are badly served by an exception, and the reason is the same each time: **they
are asking a question, not performing an action.**

---

## 3. Ask, or act

That is the whole distinction, and it is more useful than "expected vs exceptional."

```php
$jobCard->complete();                        // ACT. I have decided. Refuse me if you must.
$decision = $jobCard->checkCanComplete();    // ASK. I have not decided yet.
```

An exception is the right answer to *act*. The caller committed; if it cannot be done, unwinding to
somebody who can cope is exactly right, and the caller does not have to write a single line about
the failure case.

A result is the right answer to *ask*. There is no failure — the answer *is* the return value.
Throwing here means using unwinding as a way to return a boolean, and the caller writes a `try` to
receive an answer they explicitly requested.

### The trap

Most codebases that adopt result types adopt them for **both**, and it is a mistake in one specific
direction: every caller now has to handle every failure at every level, including the ninety percent
that have nothing useful to do except pass it upward. Exceptions handle that case for free, and that
is not an accident of the design — it is the design.

**Exceptions are ergonomic and invisible. Results are explicit and noisy.** Neither adjective is a
compliment or an insult. They are the same property seen from two sides.

---

## 4. What the two styles actually cost

| | Exceptions | Results |
|---|---|---|
| Callers who just pass it up | free — write nothing | must unwrap and re-wrap at every level |
| Ignoring the failure | impossible by accident | one line, and it looks like a guard |
| Multiple reasons | one, then unwind | all of them |
| Asking before acting | needs a `try` around a question | natural |
| Control flow | invisible; a method's failures are not in its signature | visible in the type |
| Cost of the failure path | a stack trace per throw | a value |
| Static analysis | `@throws` is a comment nothing enforces | the return type is checked |

Two rows deserve more than a table cell.

### "Ignoring the failure is one line, and it looks like a guard"

This is the strongest argument against result types, and PHP 8.5 mostly answers it:

```php
$jobCard->checkCanComplete();     // <- the whole point, discarded
$jobCard->complete();
```

That reads like a guard and is a no-op. It is the same shape as the discarded wither from Lesson 1.2
— a line that looks like it does something and does not — and in a language without a
warn-on-discard mechanism it is a genuinely serious objection.

`#[\NoDiscard]` closes most of the gap. PHP emits a warning on that line. It does **not** fire if
the value is assigned to a variable nobody reads, and it is a warning rather than an error, so it is
"most" and not "all". But it is the difference between this pattern being recommendable in PHP and
not.

### "A method's failures are not in its signature"

`@throws` is a comment. Nothing enforces it, nothing updates it when a called method starts throwing
something new, and by the third layer it is fiction. The return type of `checkCanComplete()` is
checked by the engine.

Worth being precise about what this does and does not buy: it tells you the *shape* of the answer,
not which failures are possible. `Decision` does not say which codes it might carry. Getting that
would need a sealed union PHP does not have — Lesson 4.2's `final` leaves are as close as this
language gets.

---

## 5. Which operations get a Decision?

Not all of them. Adding `checkCanX()` beside every `x()` doubles the API to no purpose, and it is
the failure mode of this lesson rather than the goal.

> **Does anybody ask before acting?**

- **`complete()` — yes.** A UI greys out the button. A form validates before submit. An operator
  checks before promising a customer. Gets a `Decision`.
- **`invoice()` — yes**, same screen, same button row.
- **`addLine()` — no.** Nobody asks whether they may add a line. They add it, and if the job card is
  invoiced they are told. Stays exception-only.
- **`startWork()`, `cancel()`, `removeLine()` — no**, same reasoning.

### The asymmetry that looks wrong and is not

`checkCanInvoice()` can only ever return one refusal, because invoicing has exactly one guard. So
why does it exist?

Not for symmetry. **Because the caller is a screen with two buttons on it**, and that screen should
not have to know that "may I complete?" is answered by a `Decision` while "may I invoice?" is
answered by catching an exception. An interface that changes shape depending on how many rules
happen to sit behind it is an interface leaking its own implementation — and the day a second
invoicing rule arrives, a credit limit or an unpaid balance, nothing outside the class changes.

---

## 6. The failure this pattern is famous for

Add `checkCanComplete()` to a codebase that already has `complete()` and you have written the same
rules twice. Then:

- somebody adds a guard to `complete()` and not to the check
- the check says yes, the operation refuses
- the caller who did the responsible thing is the one who gets the bug

**Drift.** It is not hypothetical; it is what happens to this pattern in most codebases that adopt
it, and usually within a year.

### The fix is not discipline

```php
public function complete(): void
{
    $this->checkCanComplete()->orThrow();

    $this->transitionTo(JobCardStatus::Completed);
    $this->recordThat(new JobCardCompleted($this->id));
}
```

One list of guards, two ways of reading it. `orThrow()` throws the first refusal; the check returns
all of them. **They cannot disagree, because there is only one of them.**

This is what makes the pattern affordable. Without it you are maintaining two implementations of
every rule and hoping.

### Why `Refusal` holds the exception

The obvious `Refusal` is three arguments and no exception anywhere:

```php
Refusal::of('outstanding-authorisations', 'Job card JC-1042 cannot be...', [...]);
```

Simpler, and it puts the drift straight back: now the *message and code* are written twice, once in
the exception and once in the check, and nothing keeps them in step.

So `Refusal::from(CarriesContext $failure)` reads the code, the message and the context off the
exception, and `raise()` throws that same object. Lesson 4.2's tree is what makes this possible — a
failure that carries structured context can become a value; one that carries only a sentence cannot.

**This is the payoff for 4.2 that 4.2 could not show you.**

### And what it costs

Constructing an exception captures a stack trace, which is the expensive part of `new` in PHP. So
building a refused `Decision` costs one backtrace per reason, whether anyone throws it or not.

- Happy path: **nothing**. No refusals, no exceptions.
- Refusal path: roughly what throwing would have cost, and you were about to.
- A report calling `checkCanComplete()` on ten thousand job cards to count blocked ones: fifteen
  thousand backtraces for a number.

Example 03 measures it. If you have that report, the answer is a read model — Lesson 3.3 — not a
redesign of `Refusal`.

---

## 7. Where the two styles meet the boundary

Lesson 4.4 maps failures to HTTP, and the two styles land differently:

```php
// ACT: POST /job-cards/{id}/completion
try {
    $jobCard->complete();
    return $this->noContent();
} catch (WorkshopFailure $e) {
    return $this->problem($e);           // one reason. 409.
}

// ASK: GET /job-cards/{id}/available-actions
return $this->json([
    'complete' => $jobCard->checkCanComplete()->codes(),
    'invoice'  => $jobCard->checkCanInvoice()->codes(),
]);                                       // every reason. 200.
```

The second is a **200 with reasons in the body**, and that is correct: nothing failed. The client
asked what it may do and was told. Returning a 409 from a question is the mirror image of Lesson
4.1's 500-for-user-error.

---

## 8. When to use neither

Worth stating, because two options presented side by side make a third invisible.

**Return `null` when there is exactly one reason and it is obvious.** `JobCards::contains()`
returns a bool and does not need a `Decision` explaining why. A `Decision` here is ceremony —
Rule 6.

**Use a value object when the invalid state is unrepresentable.** `Money::of(-1)` throws today. A
`PositiveAmount` type would make the question not arise. That is Lesson 1.2's argument and it beats
both mechanisms, because the best failure is the one that cannot occur.

**Do not use either for programmer error.** A `TypeError` is not a `Decision` and should not be
caught. Lesson 4.1, category (c).

---

## 9. What this course does *not* build

A general `Result<T, E>`.

PHP has no generics. A general result type is either `mixed` plus a docblock — which the engine does
not check — or a library with template annotations only a static analyser understands. Both are real
options; both cost more than this domain needs.

`Decision` answers one question — *may I?* — where the "yes" case carries **no value**. A permission
has no payload. That collapses the generic problem entirely, and the class is thirty lines instead
of three hundred.

If you genuinely need `Result<T, E>` — a parser, a pipeline of fallible transformations — reach for
a library. Do not hand-roll one for four use cases. Rule 6 again, and it cuts both ways: this lesson
is as much about **not** building the general thing as about building the specific one.

---

## 10. Where else this shows up

| Domain | Ask | Act |
|--------|-----|-----|
| E-commerce | `checkCanCheckout()` — every problem with the basket at once | `checkout()` throws on the first |
| Insurance | `checkEligibility()` — all the reasons a claim is out of cover | `submitClaim()` throws |
| Banking | `checkCanTransfer()` — limit, KYC, account status, all of it | `transfer()` throws |
| Publishing | `checkCanPublish()` — missing image, no summary, unresolved comments | `publish()` throws |

Every row has the same shape: **the ask-path serves a screen, the act-path serves a transaction.**
If your domain has a screen that greys out a button, it wants the ask-path. If it does not, adding
one is ceremony.

---

## 11. Examples

```bash
php module-4-domain-exception-trees/lesson-4.3-exceptions-vs-results/examples/01-ask-or-act.php
php module-4-domain-exception-trees/lesson-4.3-exceptions-vs-results/examples/02-the-drift.php
php module-4-domain-exception-trees/lesson-4.3-exceptions-vs-results/examples/03-what-it-costs.php
```

Example 02 builds the drift deliberately, then removes it by construction — run it if section 6 felt
like a warning rather than a mechanism. Example 03 measures the backtraces and the `#[\NoDiscard]`
warning.

---

## 12. Then

- `challenge/CHALLENGE.md` — two small classes and two methods
- `quiz/QUIZ.md` — 20 questions, threshold 15

Next: **4.4**, where the tree and the Decision both reach an HTTP boundary, and nothing internal is
allowed through it.
