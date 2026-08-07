# Lesson 4.2 — The Exception Tree

> **Module 4 · Lesson 2 of 4**
> Prerequisites: Lesson 4.1. You will re-parent the two exceptions it produced and give names to
> nine more.

---

## 1. What 4.1 left behind

The decorator worked. It also found something:

```php
throw new \DomainException(sprintf(
    'Job card %s is already in this collection. To replace it, remove it first.',
    $jobCard->id()
));
```

A failure with **no type**. Nothing can single it out, so nothing can treat it differently, and the
message is carrying the entire meaning. 4.1 fixed that one and left eleven more — eight in `JobCard`
and one in `SqliteJobCards`, plus the two already done.

This lesson does the rest, and the interesting part is not the renaming. It is deciding **what shape
they go in**, because the two obvious answers are both wrong.

---

## 2. The two failure modes of exception design

### One class for everything

```php
class WorkshopException extends \DomainException
{
    public function __construct(string $message, public readonly string $failureCode)
    {
        parent::__construct($message);
    }
}

throw new WorkshopException('Cannot invoice yet', 'JC_NOT_COMPLETE');
```

> Aside, and it is the first thing you hit if you write this yourself: the property cannot be called
> `$code`. `\Exception` already declares `protected int $code`, and a subclass may not redeclare a
> non-readonly property as readonly, or change its type. `$code` and `$message` are both taken on
> every exception in PHP — one more reason the tree in the third design carries `failureCode()` as a
> **method** rather than reusing a property name SPL already owns.

Common, and it looks disciplined. The problem is that **the code is data and the type is not**, so a
caller who wants to handle one specific failure writes:

```php
catch (WorkshopException $e) {
    if ($e->failureCode === 'JC_NOT_COMPLETE') { ... }
}
```

That is a `switch` on a string doing the work a `catch` clause exists to do. It cannot be checked by
anything, a typo is silent, and the set of possible codes is discoverable only by grepping.

### A flat list of thirty classes

```php
JobCardNotFound, JobCardAlreadyExists, IllegalStatusTransition, JobCardIsTerminal,
LineNotFound, CurrencyMismatch, VehicleNotFound, OdometerWentBackwards,
CourtesyVehicleUnavailable, FleetCodeInvalid, TaxRateNegative, ...
```

Better — each is catchable. But every one extends `\DomainException` directly, so there is no way to
catch *a subset*. A use case that works on job cards and wants to handle every job-card failure the
same way has to name eight classes in eight `catch` clauses, and forget the ninth when it is added.

### The shape that works

Depth where depth buys something, and nowhere else:

```
\Throwable
└── \Exception
    └── \LogicException
        ├── \DomainException
        │   └── Workshop\WorkshopException          abstract  ─┐
        │       └── Workshop\JobCardException       abstract   │
        │           ├── JobCardNotFound                        │
        │           ├── JobCardAlreadyExists                   │  all
        │           ├── IllegalStatusTransition                │  implement
        │           ├── JobCardIsTerminal                      │  Workshop\
        │           ├── LineNotFound                           │  CarriesContext
        │           ├── LineCurrencyMismatch                   │       │
        │           ├── NothingAwaitingAuthorisation           │       │  which
        │           └── OutstandingAuthorisations              │       │  extends
        │                                                      │       │  Workshop\
        └── \InvalidArgumentException                          │       │  WorkshopFailure
            └── Workshop\InvalidWorkshopInput       abstract  ─┤       │
                └── MissingWorkDescription                     │       │
```

Three widths of `catch`, all of them clauses somebody actually writes:

| Clause | Catches | Written by |
|--------|---------|------------|
| `catch (LineNotFound $e)` | one failure | a use case that can recover from exactly this |
| `catch (JobCardException $e)` | every job-card failure | a job-card use case |
| `catch (WorkshopFailure $e)` | everything the domain refuses, both branches | the API boundary, once |

---

## 3. Every node must earn its place

The tree above has three internal nodes and it is easy to draw four, or six. The rule that stops
that:

> **Name the `catch` clause that would use the node, and name the field it holds.
> If you can do neither, you are drawing a diagram.**

`JobCardException` passes both. It holds `JobCardId` — every failure below it is about one job card,
and declaring that field eight times is what a parent is for — and `catch (JobCardException)` is a
clause a job-card use case genuinely writes.

**`CourtesyVehicleException` fails both, and the course deliberately does not add it.** The fleet is
the second aggregate root, and symmetry is tempting. But with one subclass there is no field to
hoist, and nobody writes `catch (CourtesyVehicleException)` when catching the subclass is strictly
more precise. A node with one child is a rename with extra steps.

Add it when the second class arrives. That is a two-minute change then, and it costs nothing now.
This is the same argument as Rule 6 — *ceremony must be paid for* — applied to a hierarchy rather
than to a layer.

### Internal nodes are abstract; leaves are final

```php
abstract class WorkshopException extends \DomainException implements CarriesContext
final class JobCardNotFound extends JobCardException
```

`abstract` means `throw new WorkshopException('something went wrong')` will not compile. Without it,
somebody will do exactly that on the afternoon they are in a hurry, and you are back to a failure
with no type.

`final` on the leaves means the set of throwable types is **closed and enumerable**, which is what
lets Lesson 4.4's mapper be exhaustive rather than hopeful.

---

## 4. Two roots, because SPL gives you no choice

The tree has two trunks, and this is where Lesson 4.1's marker interface stops being defensible and
becomes necessary.

| Root | Under | Means |
|------|-------|-------|
| `WorkshopException` | `\DomainException` | *"the request made sense; the answer is no"* |
| `InvalidWorkshopInput` | `\InvalidArgumentException` | *"the request did not make sense"* |

The two SPL parents are siblings. There is no class both can extend except `\LogicException`, which
also covers half of `vendor/`. So `WorkshopFailure` — the empty interface from 4.1 — is what spans
them, and the boundary catches that.

### Which branch does a failure belong on?

The test is **not** the same as 4.1's three categories. Both branches are category (a): expected
failures the business has an answer for. The question that separates them:

> **Could the caller have known this was invalid without asking the domain?**

- **Yes → input.** A form could have caught it. A type could have caught it. The caller's own code
  could have caught it. `discoverAdditionalWork('')` — an empty string is empty everywhere.
- **No → a business rule.** It depends on state the caller does not own.
  `authoriseAdditionalWork()` on a job card with nothing pending — only the job card knows that.

That distinction is not academic. Lesson 4.4 maps it to different status codes:

```
InvalidWorkshopInput  ->  422 Unprocessable Content   "fix your request"
WorkshopException     ->  409 Conflict                "fix the world"
```

A client can retry a 422 with a better payload. Retrying a 409 with the same payload fails
identically until something else changes. To a client that automates retries that is a real
difference, and it disappears if both arrive as 400.

### The duplication at the roots

`WorkshopException` and `InvalidWorkshopInput` contain the same eight lines of context storage, and
it cannot be shared — a trait holding the private state of two hierarchies is worse to explain than
the duplication.

Worth recognising the shape, because it recurs: **PHP's single inheritance means every genuine
two-root design pays a small duplication tax at the roots.** The alternative is one root and a lie
in the type system.

---

## 5. Structured context: fields, not sentences

Here is what the old `transitionTo()` produced:

```
Job card JC-1042 is In Progress and cannot become Invoiced.
Legal from here: Awaiting Authorisation, Awaiting Parts, Completed, Cancelled.
```

Everything a caller could want is in there, and **all of it is in English.** A client that wants to
grey out the buttons for illegal transitions has to parse that sentence. It will. Then somebody
improves the wording and the client breaks, for reasons nobody connects to a copy edit.

The same facts as fields:

```php
$e->failureCode();   // 'illegal-status-transition'
$e->context();       // ['jobCardId' => 'JC-1042',
                     //  'from'      => 'in_progress',
                     //  'to'        => 'invoiced',
                     //  'allowed'   => ['awaiting_authorisation', 'awaiting_parts',
                     //                  'completed', 'cancelled'],
                     //  'terminal'  => false]
```

Survives rewording, translation, and a designer shortening the string. `allowed` is the field that
makes it worth doing: a UI can build itself from it.

### Three rules for `context()`

**Scalars and lists of scalars. No domain objects.** This becomes RFC 9457 extension members in 4.4,
which have to be JSON. Handing the boundary a `JobCardId` means the boundary has to know how to
serialise one — which puts domain types in the mapper, and the mapper is infrastructure. Call
`$id->value()` here, once.

**Machine values in context; human labels in the message.** `awaiting_authorisation` is for a
machine; "Awaiting Authorisation" is for a person. Mixing them means one audience is always served
badly. The reference implementation puts `$status->value` in the context and `$status->label()` in
the message, deliberately.

**Lists stay lists.** `OutstandingAuthorisations` carries `descriptions` as `list<string>`, not a
joined sentence. Joining puts a presentation decision — semicolons? commas? "and"? — inside the
domain, and takes from the client the ability to render bullet points. The *message* joins them,
because a message is prose.

---

## 6. Why there is a failure code as well as a class name

`$e::class` gives you `Workshop\IllegalStatusTransition` free. So why declare
`'illegal-status-transition'` by hand?

**The class name is internal and must stay that way.** Lesson 4.4 asserts that no internal name
reaches the wire. A client switching on `Workshop\IllegalStatusTransition` has your namespace layout
in its codebase, and renaming a class becomes a breaking API change.

**They change for different reasons.** A class gets renamed in a refactor; a wire code must not.
Splitting one class into two is an internal decision, and a client's `switch` should keep working.
Two names, because there are two contracts.

### Why it is declared and not derived

`strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $shortName))` would generate every code
automatically and is genuinely tempting. It also re-couples the wire format to the class name — the
exact coupling the paragraph above exists to prevent — with the coupling now **invisible**, because
no line of code says so.

Declared explicitly, a rename shows up in review as a changed string, and somebody asks whether
clients depend on it. Derived, it shows up as nothing at all.

### How it is enforced

`failureCode()` is declared on `CarriesContext` and *not* implemented on `WorkshopException`. An
interface method left undefined in an abstract class stays abstract, so PHP refuses to load any
concrete subclass that does not supply one.

A default implementation on the base — returning something derived, or `''` — would let a new
exception ship without a code, and the place you would find out is a client's `switch` falling
through to its default branch.

---

## 7. Why `CarriesContext` is a second interface

The obvious move is to put `failureCode()` and `context()` on `WorkshopFailure`, which already means
"ours". Lesson 4.1's checks forbid it — they assert that interface declares no methods — and that
constraint was deliberate.

The two interfaces answer different questions:

- `WorkshopFailure`: *may I catch this as a business refusal?* Every throwable the domain will ever
  produce must answer it, and the answer is the same shape forever. **That is why it can be frozen.**
- `CarriesContext`: *what do you know, in fields?* Not everything needs to answer it.

Merge them and the marker stops being freezable: every addition to the data contract becomes a
breaking change for every implementer, including the ones that had no data. Interface segregation,
usually taught with an example nobody would write, arriving as an actual decision.

---

## 8. What the aggregate looks like afterwards

Before:

```php
private function transitionTo(JobCardStatus $next): void
{
    if (!$this->status->canTransitionTo($next)) {
        throw new \DomainException(sprintf(
            'Job card %s is %s and cannot become %s. %s',
            $this->id, $this->status->label(), $next->label(),
            $this->status->isTerminal()
                ? 'That state is terminal — nothing follows it.'
                : 'Legal from here: ' . implode(', ', array_map(
                    static fn(JobCardStatus $s): string => $s->label(),
                    $this->status->allowedNext())) . '.'
        ));
    }

    $this->status = $next;
}
```

After:

```php
private function transitionTo(JobCardStatus $next): void
{
    if (!$this->status->canTransitionTo($next)) {
        throw IllegalStatusTransition::from($this->id, $this->status, $next);
    }

    $this->status = $next;
}
```

The method now decides **one thing** — is the transition legal — and the exception describes itself.
`JobCard` contains no `sprintf` at all after this lesson, and the checks assert it. An aggregate root
holding eleven format strings was doing presentation.

The honest cost: `JobCard` now names seven classes it did not name before. They are all in
`Workshop\`, all describing this aggregate's own rules, none of them infrastructure — but it is real
coupling. **If that list ever reaches thirty, the aggregate is too big.** Lesson 2.1's warning about
boundaries, arriving from a direction it did not anticipate: the size of an aggregate's exception
vocabulary measures the size of the aggregate.

---

## 9. Nothing earlier breaks, and why that is not luck

Every new class is still a `\DomainException` or a `\InvalidArgumentException` — through two
intermediate classes instead of directly. So:

- Lesson 3.1's `is_subclass_of(JobCardNotFound::class, DomainException::class)` still passes
- every `throws(\DomainException::class, ...)` in Modules 2 and 3 still passes
- `$e->jobCardId` still reads the same, though the property moved to a parent

That is the fourth additive change in this course and it is not luck. It works because the original
types were chosen on the merits — 3.1's doc block argued for `\DomainException` over
`\RuntimeException` before there was any tree to fit into. **Re-parenting is cheap when the first
parent was right.**

One thing did get better rather than merely surviving: `JobCardNotFound`'s
`?JobCardId $jobCardId = null` is gone. It was nullable only because a named constructor had to
assign it after construction. With the identity as a constructor parameter one level up it is
non-nullable — an exception about a job card cannot exist without one, and now the type says so.

`php regression.php` is what proves all of this, and it is part of the acceptance criteria.

---

## 10. Where else this shows up

| Domain | Root | Node | Leaf |
|--------|------|------|------|
| E-commerce | `ShopFailure` | `OrderException` | `OrderAlreadyShipped` |
| Insurance | `PolicyFailure` | `ClaimException` | `ClaimOutsideCoverPeriod` |
| Payments | `PaymentFailure` | `CardException` | `CardDeclined` (with `declineCode` in context) |
| Accounting | `LedgerFailure` | `JournalException` | `PeriodClosed` |

The payments row is the one worth studying. `CardDeclined` carrying a `declineCode` in structured
context is exactly this design, shipped by every payment processor, for exactly the reason in
section 5: `insufficient_funds` and `do_not_honor` need different handling by the merchant, and no
amount of message parsing is a safe way to tell them apart.

---

## 11. Examples

```bash
php module-4-domain-exception-trees/lesson-4.2-the-exception-tree/examples/01-three-shapes.php
php module-4-domain-exception-trees/lesson-4.2-the-exception-tree/examples/02-catching-at-three-widths.php
php module-4-domain-exception-trees/lesson-4.2-the-exception-tree/examples/03-context-not-sentences.php
```

Example 01 builds all three designs from section 2 and makes the same requirement of each. Example
03 is the one to run if section 5 felt like a matter of taste — it parses a message with a regex,
then changes one word of the message.

---

## 12. Then

- `challenge/CHALLENGE.md` — thirteen classes, read `challenge/checks.php` first
- `quiz/QUIZ.md` — 20 questions, threshold 15

Next: **4.3**, which asks whether any of these should have been exceptions at all.
