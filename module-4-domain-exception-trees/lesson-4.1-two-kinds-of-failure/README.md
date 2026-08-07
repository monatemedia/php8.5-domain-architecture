# Lesson 4.1 — Two Kinds of Failure

> **Module 4 · Lesson 1 of 4**
> Prerequisites: Module 3 complete. You will modify `JobCardNotFound` from Lesson 3.1 and
> decorate the repositories from 3.1 and 3.4.

---

## 1. The symptom

Open the network tab on almost any PHP application and try to do something the business does not
allow. Invoice a job that is not finished. Book a courtesy vehicle that is already out. You will
often get this:

```
HTTP/1.1 500 Internal Server Error
```

500 means *"something went wrong on the server and we do not know what."* But nothing went wrong.
The server understood the request perfectly, applied a rule the business wrote down, and refused.
That is the system **working**. It is a 409 or a 422, and the response should say which rule and
what to do about it.

The reason it comes out as a 500 is almost never a routing mistake. It is that the code has no way
to tell the two apart. Somewhere near the top there is:

```php
try {
    $controller->handle($request);
} catch (\Throwable $e) {
    return new Response(500, [], 'Internal Server Error');
}
```

…and `\Throwable` catches a business rule and a dead database with equal enthusiasm.

This module fixes that, and the fix is not middleware. **It is the type of the thing you throw.**
Middleware can only be as precise as the exceptions reaching it.

---

## 2. Three categories, not two

The module is called *exception trees* and it is tempting to jump to the tree. Do not. Every
decision in the next three lessons falls out of a classification you have to get right first, and
it has **three** buckets, not the two people usually name.

### (a) Expected failures — the business has an answer

> *"You cannot invoice a job card that is not complete."*
> *"That courtesy vehicle is already on job card JC-1042."*
> *"There is no job card with id JC-9999."*

The defining property: **somebody who runs the business already knows what to do about it.** Marcus
would not be surprised by any of these. He would say "well, finish the job first" — and that
sentence is the response body.

These are not errors in the system. They are the system doing its job. They are also, and this
matters for Lesson 4.4, entirely safe to show a user, because they contain no information the user
did not already have.

### (b) Exceptional failures — nobody has an answer

> `PDOException: SQLSTATE[HY000] [2002] Connection refused`
> A disk full. A DNS failure. The payment gateway returning HTML instead of JSON.

The defining property: **nothing the caller does differently would have helped.** The request was
valid. The rules were satisfied. The machinery underneath fell over.

These are genuinely 500s, they belong in a log with a stack trace, and they should tell the user
nothing beyond "something broke, we know about it."

### (c) Programmer error — the code is wrong

> `TypeError: Argument #1 must be of type Money, string given`
> `Error: Call to a member function total() on null`
> `\LogicException: this method should be unreachable`

The defining property: **it should be impossible, and the fix is a code change.** No amount of
correct user behaviour and no amount of working infrastructure prevents it, because the bug is in
the file.

Everyone remembers (a) and (b) and forgets (c), and forgetting it produces a specific, expensive
mistake: a `catch (\Throwable)` at the boundary that swallows real bugs into a tidy 500 page and a
log line nobody reads. **You want programmer error to be loud.** It is the one category where the
correct handling is to let it hit the floor in development and page somebody in production.

---

## 3. The test, in one question

Categories are easy to agree with and hard to apply at 4pm on a Thursday. So here is the operational
version, and it is the Module 0 question wearing different clothes:

> **If I read this failure aloud to Marcus, would he recognise it as something that happens in his
> workshop?**

- *"Someone tried to invoice job card 1042 before the work was signed off"* → **yes**. Expected. (a)
- *"The database connection was refused"* → **no**, that is not a thing that happens in a workshop.
  Exceptional. (b)
- *"We passed a string where a Money was expected"* → **no**, and he would be alarmed you were
  telling him. Programmer error. (c)

Two secondary tests, for the cases where that one is genuinely ambiguous:

**The retry test.** *Would retrying the identical request, unchanged, ever succeed?*
Yes → probably exceptional (b): the database might be back. No → expected (a): the job card is still
not complete, and it will still not be complete in ten seconds.

**The rewrite test.** *Is there a different, valid request the caller could send that would work?*
Yes → expected (a): finish the job, then invoice it. No → (b) or (c).

---

## 4. PHP's built-in hierarchy, and why it is a trap

PHP ships an exception hierarchy in SPL, and it looks like it was designed for exactly this:

```
\Throwable
├── \Error                       ← engine-level. TypeError, ArgumentCountError, DivisionByZeroError
│                                  This is category (c). Do not catch it.
└── \Exception
    ├── \LogicException          ← "a bug — detectable at compile time"
    │   ├── \BadFunctionCallException
    │   ├── \DomainException     ← ...but this is where everyone puts business rules
    │   ├── \InvalidArgumentException
    │   ├── \LengthException
    │   └── \OutOfRangeException
    └── \RuntimeException        ← "only detectable at run time"
        ├── \OutOfBoundsException
        ├── \OverflowException
        ├── \RangeException
        ├── \UnderflowException
        └── \UnexpectedValueException
        (\PDOException extends \RuntimeException)
```

Three things to notice, because each one bites.

**First, the split is not the split you need.** SPL divides on *when the failure is detectable*
(compile time vs run time). You need it divided on *whose problem it is*. Those cut across each
other. `\DomainException` — the one whose name promises business rules — sits under
`\LogicException`, the branch documented as "errors in the program logic that should lead to a fix
in your code." A job card that is not ready to invoice is not a bug in your code.

This course has used `\DomainException` for business rules since Lesson 1.1 anyway, and so does
almost everyone, because the name is right even though the parent is wrong. That is a defensible
convention. It is not a foundation.

**Second, `\DomainException` and `\InvalidArgumentException` are siblings.** Your domain throws
both — `Money::of(-1)` is an invalid argument, `$jobCard->invoice()` on an incomplete card is a
domain rule. They share no ancestor except `\LogicException`, and catching *that* at your API
boundary also catches every `BadMethodCallException` in every library you depend on.

So there is **no single class** you can catch to mean "my domain refused something." This is not a
gap you can design around by picking a better base class. It is a consequence of a hierarchy that
was fixed in 2006, and the next section is the way out.

**Third, `\Error` is not an `\Exception`.** `catch (\Exception $e)` does not catch a `TypeError`.
That is usually what you want — category (c) should be loud — but people write
`catch (\Throwable $e)` to "be safe" and then wonder why bugs vanish.

---

## 5. The way out is an interface

You cannot change what `\DomainException` extends. You can add something to it.

```php
namespace Workshop;

interface WorkshopFailure extends \Throwable
{
}
```

Empty. No methods, no constants. A **marker interface**: its whole job is to be a type that can be
named in a `catch` clause.

```php
try {
    $jobCard->invoice();
} catch (WorkshopFailure $e) {
    // Every expected failure this domain can produce, and nothing else.
}
```

`JobCardNotFound extends \DomainException implements WorkshopFailure` and
`NegativeMoney extends \InvalidArgumentException implements WorkshopFailure` are now catchable
together, despite having incompatible parents. A `PDOException` is not. A `TypeError` is not.

Interfaces are multiple; base classes are single. PHP gives every class exactly one inheritance
slot, and SPL has already spent yours.

### Why not just wrap everything?

The obvious alternative is to catch `\InvalidArgumentException` inside the domain and rethrow it as
a `WorkshopException`. It works, and it costs you a stack trace boundary, a `previous` chain to
unwrap, and a rule that every developer must remember at every throw site. The marker interface
costs one `implements` clause per class, applied where the class is declared. Prefer the version
that cannot be forgotten in a hurry.

### Why an *empty* interface is not a code smell here

Marker interfaces get criticised, and the criticism is usually right: an interface with no methods
often means someone wanted an annotation. That criticism does not apply when the marker's purpose
**is** to be a catchable type, because in PHP a `catch` clause takes a type and nothing else. The
alternative — `catch (\Throwable $e) { if ($e->isDomainFailure()) ... }` — is worse in every way,
starting with the fact that it catches `\Error`.

Lesson 4.2 gives this tree abstract classes with real behaviour underneath the marker. The marker
stays because it is what spans the two SPL branches.

---

## 6. The other half: translating at the edge

The marker tells you what a domain failure **is**. It does nothing about the driver exceptions
coming the other way.

`SqliteJobCards` from Lesson 3.4 can throw a raw `\PDOException`. Look at what that means for a
caller:

```php
try {
    $jobCards->get($id);
} catch (\PDOException $e) {
    // ...
}
```

That `catch` clause is application code — code that has no business knowing there is a database at
all — naming a class from `ext-pdo`. Swap SQLite for a REST API and every one of those clauses is
wrong, silently, and the way you find out is in production.

**A repository implementation that lets a driver exception escape has not finished inverting the
dependency.** The interface is clean and the failures are not, and failures are part of the
contract whether or not you wrote them down.

So infrastructure translates:

```php
namespace Infrastructure;

final class StorageFailure extends \RuntimeException
{
}
```

- **`\RuntimeException`, not `\DomainException`.** Category (b). Nobody's business rule was broken.
- **It does not implement `WorkshopFailure`.** That is the whole point. It must not be caught by
  the clause that means "the business refused something," because it will become a 500 and it
  should.
- **It lives in `infrastructure/`.** A storage failure is not a fact about a workshop, by the same
  test that put `JobCardNotFound` in the domain.
- **It keeps the original as `previous`.** Translating must not destroy evidence. The operator
  debugging this at 2am needs `SQLSTATE[HY000] [2002]`, even though the user must never see it.

### Doing it without editing every method

The mechanical approach is a try/catch in all seven methods of `SqliteJobCards`. It works, it is
noisy, and it will be missing from method eight.

The challenge uses a **decorator** instead: a class that implements `JobCards`, holds another
`JobCards`, and does nothing but translate failures.

```php
final class GuardedJobCards implements JobCards
{
    public function __construct(private readonly JobCards $inner) {}

    public function get(JobCardId $id): JobCard
    {
        return $this->guard(fn(): JobCard => $this->inner->get($id));
    }
    // ...
}
```

Three properties are worth having and none of them are free with try/catch-everywhere:

1. **It works on any implementation.** In-memory, SQLite, the HTTP one you write next year. It
   knows nothing about them beyond the interface — Lesson 3.4's substitutability, being spent.
2. **The translation policy is in one file.** One place to read it, one place to change it.
3. **It composes.** A retry decorator, a logging decorator and a cache decorator stack in the same
   way, in an order you choose at wiring time.

The rule it enforces is exactly two lines long:

> **A `WorkshopFailure` passes through untouched. Anything else becomes a `StorageFailure`.**

"Untouched" means untouched — the same object, not a copy, not rewrapped. A `JobCardNotFound` that
comes out of the decorator with a different stack trace has been damaged in transit for no reason.
The checks assert object identity, with `===`.

---

## 7. What this does *not* mean

**It does not mean catching exceptions everywhere.** The decorator sits at one boundary. Code
between the boundary and the aggregate should catch nothing at all — a failure that nobody can do
anything about should travel up to somebody who can.

**It does not mean the domain never throws category (b).** It means the domain never throws a
failure that *names* infrastructure. If a domain service genuinely cannot proceed for a reason that
is not a business rule, that is a real situation; it just is not a `\PDOException`.

**It does not mean wrapping `\Error`.** `TypeError` inside a repository is category (c) — a bug in
your mapper. Wrapping it as `StorageFailure` tells the operator the database is unhealthy when the
database is fine. The challenge is specific about this: catch `\Exception`, not `\Throwable`.

That last one is the whole "three categories, not two" section paying rent. If you had classified
failures into "domain" and "not domain," you would have wrapped the `TypeError`.

---

## 8. Where else this shows up

| Domain | Expected (a) | Exceptional (b) |
|--------|--------------|-----------------|
| E-commerce | Card declined, out of stock, coupon expired | Payment gateway timeout, Redis down |
| Insurance | Policy lapsed, claim outside cover, excess unpaid | Rating service unreachable |
| Booking | Seat taken, fare rules forbid the change | GDS connection dropped |
| Accounting | Period closed, unbalanced journal entry | Ledger replica lagging |

**A card decline is category (a).** It is the single most instructive example, because the code that
handles it lives inside an HTTP client and *feels* infrastructural. But the bank understood the
request and refused it for a business reason, the caller can do something about it, and retrying
unchanged will not help. It is a 402, not a 500.

A gateway *timeout* on the same call is (b). Same client, same method, same afternoon — different
category, because the answer to "would a retry work?" is different.

---

## 9. Examples

```bash
php module-4-domain-exception-trees/lesson-4.1-two-kinds-of-failure/examples/01-the-500-problem.php
php module-4-domain-exception-trees/lesson-4.1-two-kinds-of-failure/examples/02-catchable-together.php
php module-4-domain-exception-trees/lesson-4.1-two-kinds-of-failure/examples/03-translating-at-the-edge.php
```

Example 02 shows the SPL sibling problem concretely, and is the one to run if section 4 felt
abstract. Example 03 wraps a `TypeError` on purpose to show you what that costs.

---

## 10. Then

- `challenge/CHALLENGE.md` — read `challenge/checks.php` first
- `quiz/QUIZ.md` — 20 questions, threshold 15

Next: **4.2**, where the marker acquires a tree underneath it, and `JobCard`'s twelve
`\DomainException` throws get names.
