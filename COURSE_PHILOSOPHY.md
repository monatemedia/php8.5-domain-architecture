# Course Philosophy — Golden Rules
> Advanced Domain Architecture & Tactical DDD

The previous course had six rules about **how to wire components**. These six are about
**what the components should be**. They apply across every module. When you are unsure whether a
design decision is right, run it through this list before you run it through your own taste.

Rule 6 is the one people skip. It is the one that stops the other five from doing damage.

---

## Rule 1 — The Dependency Rule Points Inward

The domain layer depends on nothing. Not on the framework, not on the ORM, not on PDO, not on the
HTTP request, not on the queue, not on the clock, not on `$_SESSION`. Everything else depends
on it.

**In practice:**

```
✅  final class JobCard { public function invoice(): Invoice { ... } }
    // Pure PHP. No imports outside the domain namespace.

❌  final class JobCard { public function invoice(): Invoice { $this->pdo->query(...); } }
    // The rule for invoicing is now welded to a database driver.

❌  final class JobCard { public function isOverdue(): bool { return time() > $this->dueAt; } }
    // Reaching for the clock is reaching outside. Take the current time as an argument.
```

The last one catches nearly everybody. `time()`, `date()`, `rand()`, `uniqid()`, `$_ENV`,
`getenv()` and static singletons are all the outside world. A domain object that reaches for any
of them cannot be tested without controlling the universe.

**The test you can actually run:** open the file and read the `use` statements. If the domain
layer imports anything infrastructural, the rule is broken. The capstone enforces exactly this
with a test that scans imports — the course's thesis in executable form.

**Where this surfaces:** every module. Primarily 3.1, 3.2 and the capstone.

---

## Rule 2 — Make Illegal States Unrepresentable

A domain object should be **impossible to construct in an invalid state**, not merely awkward to.
Validation that happens after construction is a request; validation in the constructor is a
guarantee.

**In practice:**

```
✅  new Money(-500, 'EUR')            → throws. There is no negative Money in this system.
✅  $jobCard->invoice()               → throws unless the work is complete.

❌  $money->setAmount(-500);          → a setter is a hole in every invariant above it.
❌  if ($money->isValid()) { ... }    → an object that can be invalid has already lost.
```

The practical consequence is that **public setters disappear from your domain layer**. Not
"are used sparingly" — disappear. State changes happen through named business operations
(`complete()`, `authoriseAdditionalWork()`, `cancel($reason)`), each of which is free to refuse.

Corollary: if a class has a getter and a setter for every property and no other methods, it is
not a domain object. It is a database row wearing an object costume, and Lesson 1.1 is about why
that costs you.

**Where this surfaces:** 1.1, 1.2, 1.4, 2.1. Everything after that assumes it.

---

## Rule 3 — The Aggregate Is a Consistency Boundary, and It Is Smaller Than You Think

An aggregate is the set of objects that must be correct **together, at the same instant**, inside
one transaction. That is the entire definition. It is not "things that belong together
conceptually", and it is not "everything reachable from this object".

**The question to ask** is never "is a vehicle part of a job card?" It is: *"if these two changed
a second apart, would the business be harmed?"* Usually the answer is no, and the two belong in
different aggregates linked by identity.

```
✅  JobCard holds its own LineItems.
    A job card's total must match its lines at every instant. One aggregate.

❌  JobCard holds the full Customer object, which holds every other JobCard.
    Loading one job card now loads the customer's entire history. Two clerks editing
    two unrelated job cards for the same customer now collide.

✅  JobCard holds a CustomerId.
    Different aggregate, referenced by identity.
```

Big aggregates are the second most common failure in tactical DDD, after the shared model. They
fail slowly and expensively: everything works in development with forty rows and collapses in
production with four hundred thousand.

**Where this surfaces:** 2.1, 2.2, 2.3. Diagnosed early in 0.1.

---

## Rule 4 — Speak the Business's Words, Exactly

If the workshop manager says "book in a vehicle" and your code says `createJobCardRecord()`, you
have introduced a translation layer that exists only in your head. Every conversation about a bug
now costs a translation in both directions, and translations lose things.

**In practice:**

```
✅  $jobCard->authoriseAdditionalWork($quote);   // the phrase the manager actually uses
❌  $jobCard->setStatus(3);                      // a phrase nobody uses
❌  $jobCard->update(['status' => 'auth']);      // a phrase that means nothing to anyone
```

This is not cosmetic. Naming is how a domain model gets corrected: when the code uses the
business's words, the business can read the code back to you and tell you where you are wrong.
When it uses yours, they cannot, and you find out in production.

The counterpart is **bounded contexts**. The same word genuinely means different things in
different parts of a business — "vehicle" to the workshop is a thing with a service history, to
accounts it is a line on an invoice, to the parts desk it is a compatibility key. One shared
`Vehicle` class serving all three is the single most common way a DDD project goes wrong. Let each
context keep its own model and its own vocabulary.

**Where this surfaces:** 0.2 introduces it. Every naming decision afterwards is an application
of it.

---

## Rule 5 — A Failure the Business Can Predict Is Not Exceptional

`PDOException` means the database fell over: nobody planned for it, nobody can act on it, log it
and return 500. `CannotInvoiceIncompleteJobCard` means somebody tried to break a rule the business
wrote down on purpose. These are different categories and must not share a code path.

```
✅  catch (JobCardException $e)    → 422, with a message the user can act on
✅  catch (\PDOException $e)       → 500, logged, message hidden

❌  catch (\Exception $e)          → everything becomes 500, and users are told
                                     "something went wrong" for typing a bad date
❌  throw new \Exception('nope')   → carries no type, so nothing downstream can
                                     distinguish it from a genuine crash
```

Two practical consequences. First, domain exceptions form a **tree**, so a boundary can catch
broadly (`DomainException`) while the core throws narrowly
(`CannotInvoiceIncompleteJobCard`). Second, the exception carries **structured data**, not a
formatted sentence — the sentence is a presentation decision and belongs at the boundary, where
it can be translated, localised, or turned into JSON.

**Where this surfaces:** the whole of Module 4. Rehearsed from 1.2 onwards, where value objects
start throwing.

---

## Rule 6 — Ceremony Must Be Paid For

Every rule above adds indirection. Indirection is a cost paid on every read of the code by every
developer forever, and it buys something only when the business rules are genuinely complicated.
Applied to a CRUD screen — form in, row out, no interesting invariants — the five rules above
produce four layers wrapped around an `UPDATE` statement, and the codebase is worse than if you
had never read this course.

**Signals that justify the ceremony:**

- An invariant spans more than one entity ("a courtesy vehicle cannot be on two open jobs").
- The lifecycle has illegal transitions ("cannot invoice before completion").
- The rules are argued about by people who do not write code.
- Getting it wrong costs money, breaks a law, or has to be explained to a regulator.

**Signals that it is not justified:**

- The database schema *is* the model, and everyone is happy about that.
- The work is reporting, content management, or configuration.
- The only rule anyone can name is "this field is required".

The honest answer for a great deal of real software is a fat controller and an Active Record
model, and knowing when that is the right answer is worth more than knowing how to build an
aggregate. Module 0 comes first for this reason.

**Where this surfaces:** Module 0 teaches it. Module 5 applies it to code you cannot rewrite.

---

## How These Rules Connect

```
Rule 6 (Ceremony must be paid for)
   ↓ decides whether to apply any of the rest at all
Rule 4 (Speak the business's words)
   ↓ gives you the names, without which
Rule 2 (Illegal states unrepresentable) has nothing to protect
   ↓ enforced within a boundary defined by
Rule 3 (The aggregate is a consistency boundary)
   ↓ which is only keepable because of
Rule 1 (The dependency rule points inward)
   ↓ and is communicated outward by
Rule 5 (Predictable failures are typed, not exceptional)
```

Rule 6 sits at the top on purpose. It is a gate, not a footnote.

---

## Carried Forward From the Previous Course

The six rules from *PHP 8.5 OOP Mastery* have not been retired — they are assumed. In particular:

| Old rule | How it is used here |
|----------|---------------------|
| 1 — Config belongs at the entry point | Becomes Rule 1. The domain layer is the deepest "not the entry point" there is. |
| 2 — Test behaviours, not layouts | Every acceptance check in this course asserts what the model refuses, never how it is built. |
| 3 — The type system is a security layer | Becomes Rule 2. A value object is the type system used aggressively. |
| 4 — Favour composition over inheritance | Aggregates are composition. Domain models with deep inheritance trees are almost always modelling a database, not a business. |
| 5 — Objects hold state or perform work | Entities hold state *and* enforce rules; domain services perform work. Lesson 2.4 is about telling them apart. |
| 6 — Read the failing test first | Read `challenge/checks.php` before you write a line. It is the brief, stated precisely. |

---

## A Note on What This Course Cannot Teach You

Modelling is a conversation. Every genuinely hard modelling decision in this course — where the
aggregate boundary goes, whether a rule is an invariant or a policy, whether two things are one
concept or two — is decided in real life by talking to the people who run the business, and often
by being wrong once first.

A course can hand you the vocabulary, the patterns, and the questions worth asking. It cannot hand
you the argument in the meeting room. Treat every "correct" answer here as *correct for the
domain as this course has described it* — and stay suspicious when someone states a modelling rule
without stating the business it came from.
