# Lesson 5.1 — When the Framework Owns Your Models

> **Module 5 · Lesson 1 of 2**
> Prerequisites: Modules 1–4. This lesson uses all of them and adds nothing to `domain/workshop/`.

---

## 1. The situation you are actually in

Four modules have built a domain layer with no framework in it, a repository interface the domain
declares, a mapper that reconstitutes aggregates, and an exception tree that reaches an HTTP
boundary without leaking.

Almost nobody reading this gets to start there.

What you have is a Laravel application from 2019 where `App\Models\JobCard` extends
`Illuminate\Database\Eloquent\Model`, has forty public attributes, and is used in 200 places.
Rewriting it is not on the table and should not be — a rewrite that takes eight months and delivers
the same features is how architecture gets a bad name in an organisation.

**So this lesson is not "how to do DDD in Laravel."** It is: *given that the ORM owns your model,
which parts of the previous four modules can you have, what does each cost, and where do you stop?*

---

## 2. What an Active Record model actually is

Three separate things wearing one class, and they cause three separate problems:

| It is | Which means |
|-------|-------------|
| **a row** | it can be constructed in any state, including states the business forbids |
| **with a public setter for every column** | any rule you write can be routed around by assigning the column |
| **that knows how to query and save itself** | it cannot be tested, or reasoned about, without a database |

Every real ORM adds magic on top: `__get` and `__set` mean there is no property to make private and
no method the compiler will stop you calling. That is not incidental — it is what makes the ORM
pleasant, and it is exactly what makes rules hard to enforce.

The course ships a sixty-line `Infrastructure\Legacy\ActiveRecord` reproducing precisely those three
properties and nothing else. Read it; it is short, and every line of it is doing something a real
one does.

---

## 3. The ladder

The useful question is not "domain layer, yes or no." It is a ladder, and each rung has a different
price:

| Rung | What | Cost | Buys |
|------|------|------|------|
| **1** | Named methods instead of public setters | **free** | the operation has a name the business uses |
| **2** | Invariants in the model, not in a service | **cheap** | the rule becomes unavoidable |
| **3** | Value objects over the existing columns | **cheap** | no floats for money, no strings for identity |
| **4** | Typed domain exceptions + boundary mapping | **cheap** | 409 instead of 500; clients can act |
| **5** | A separate domain layer with a mapper | **expensive** | testable without a database; survives the ORM |

**Rungs 1 to 4 give you most of the value of this course for a fraction of the cost, and none of
them requires a schema change, a migration, or a rewrite.** The challenge climbs 1, 2 and 3. Rung 4
turns out to need no work at all — section 5.

### Rung 1 — named methods

```php
$jobCard->status = 'completed';     // before
$jobCard->complete();               // after
```

Free, and the highest value per hour available in a legacy codebase. Three reasons:

- **It gives the operation a name the business already uses.** `complete()` is a thing Marcus says.
  `status = 'completed'` is a thing a table says.
- **It creates a place to put the rule**, which rung 2 then fills. Without a method there is nowhere
  for a guard to live except a service, and a service can be skipped.
- **It makes the call sites greppable.** `grep -rn '->complete()'` finds every place work is
  finished. `grep -rn "status = "` finds nothing useful, ever.

To make it stick you have to stop the setter, which in an ORM means taking over `__set` for the
columns that have rules. Be honest about what that is: a **runtime** check, not a type. A
`LogicException` at 3am is worse than a compile error, and PHP will not give you the compile error
because a magic setter is magic. That is the cost of the framework owning the model, and it does not
go away until rung 5.

### Rung 2 — the invariant moves in

The rule that was in `JobCardService::complete()` — and also in the controller, and also in the
console command, in three slightly different versions — moves onto the model.

Lesson 1.4's argument, with one addition specific to this situation: **you do not have to find all
three copies first.** Put the rule on the model, leave the service copies where they are, and delete
them as you touch them. A duplicated correct rule is not a bug; an un-enforced rule is.

### Rung 3 — value objects over existing columns

```php
public function total(): Money
{
    return Money::of((int) $this->attributes['total_cents'], Currency::from($this->attributes['currency']));
}
```

**The schema does not move.** `total_cents` is still an integer column, `currency` is still
`char(3)`, and the report somebody wrote in SQL last year still works. In Eloquent this is a cast or
an accessor; in Doctrine an embeddable or a custom type. The mechanism differs; the decision is the
same one Lesson 1.2 made.

This rung has the best ratio of "bugs prevented" to "diff size" in the whole ladder, because money
arithmetic in an anemic model is where the expensive mistakes live.

### Rung 5 — and why it is often theatre

A separate `Workshop\JobCard`, a `JobCards` repository, a mapper between the aggregate and the ORM
entity. Everything Modules 1–3 taught.

What it genuinely buys: **rules testable without a database**, and **a domain that survives
replacing the ORM**. Both are real.

What it costs, and this list is the lesson:

- **Two models of the same thing**, maintained together, forever.
- **A mapper**, which is the file every new column has to be added to and the file everybody forgets.
- **The framework fighting you.** Lazy loading gives you an aggregate whose collections hit the
  database when touched. Model events fire on save, not on the domain operation. Mass assignment,
  query scopes, `$appends`, observers — every one of them assumes the model is the domain object.
- **Two vocabularies in review.** "Do you mean the entity or the aggregate?" costs somebody an hour
  a week forever.

Applied to an application whose rules are *"validate these fields, save the row"*, rung 5 produces
four layers of indirection around an `UPDATE` statement. **That is Module 0's warning, arriving
inside an existing codebase where it is much harder to hear.**

---

## 4. When rung 5 *is* worth it

Module 0's signals still apply — invariants spanning entities, a lifecycle with illegal transitions,
rules the business argues about, calculations with legal consequences. Two more that are specific to
this situation:

**The persistence model and the domain model have genuinely diverged in shape.** One aggregate is
five tables; or one table serves three different concepts distinguished by a `type` column. Mapping
is then work you are already doing by hand, badly, in several places.

**You are already writing the mapper, one method at a time.** Every `toArray()`, every
`fromRequest()`, every `$model->fresh()->load('lines')` in a service is a piece of a mapping layer
that nobody has admitted exists. At that point rung 5 is not new cost; it is consolidating cost you
are already paying.

### And the counter-signal

**You cannot get a test suite around the current behaviour.** Then do not start with rung 5. Start
with rungs 1–3, which are small enough to review by eye, and use the tests you write on the way up
to make rung 5 possible later. Lesson 5.2 is entirely about that path.

---

## 5. Module 4 transfers wholesale, and this is the surprise

Rung 4 — typed exceptions and boundary mapping — needs **no persistence changes at all**.

```php
final class JobCardRecord extends ActiveRecord
{
    private function transitionTo(JobCardStatus $next): void
    {
        if (!$this->status()->canTransitionTo($next)) {
            throw IllegalStatusTransition::from($this->id(), $this->status(), $next);
        }

        $this->attributes['status'] = $next->value;
    }
}
```

That is `Workshop\IllegalStatusTransition` — Module 4's class, unchanged — thrown from an Active
Record model. `ProblemDetailsMapper` turns it into a 409 with its context intact and needs no new
entry, **because it keys on `failureCode()` and knows nothing about where the exception came from.**

The checks assert something stronger: the problem body produced from the legacy record is
byte-identical to the one produced from the domain aggregate. The mapper cannot tell them apart, and
should not.

**So the cheapest large improvement available to a legacy PHP application is Module 4.** An exception
tree and a boundary mapper have no opinion about persistence, which means they can be added to
anything. If you take one thing from this course back to work on Monday, take that.

Value objects and enums are the same story for a different reason: `JobCardStatus` with its
transition table was never persistence-shaped, so there is nothing to port. It works from an ORM
model because it never knew what an ORM was.

---

## 6. What does *not* transfer

Honesty about this is the difference between a useful lesson and a sales pitch.

**Aggregate boundaries.** Module 2's rule — one aggregate per transaction, reference others by
identity — fights an ORM directly. `$jobCard->vehicle->registration` is one property access in
Eloquent, it is what the whole team already writes, and forbidding it buys you consistency
guarantees the application was not making anyway. You can hold the line on new code; you will not
retrofit it.

**Reconstitution as a decision.** Lesson 3.2 chose `reconstitute()` deliberately. An ORM hydrates
without asking, and you do not get to opt out. That turns out to cost less than expected — the rules
are on the transitions rather than on construction — but it is not your call to make.

**Domain events with a clean release point.** Model events fire on `save()`, which is a persistence
moment and not a business one. You can add a domain event list to a record; you cannot easily stop
the framework's own events firing beside it, and now there are two mechanisms.

**"Zero infrastructural imports."** The capstone proves the domain directory imports nothing from
Slim, PDO or PHP-DI. That is an ideal **for a codebase built that way from the start** — it is
worth reasoning with, and it is not a standard to hold an existing application to. A course that
implies otherwise sends people back to work to pick fights they cannot win.

---

## 7. Data Mapper, briefly

Everything above assumes Active Record. Doctrine is a Data Mapper, and the ladder changes shape:

| | Active Record | Data Mapper |
|---|---|---|
| Rung 1 | fight `__set` | properties are already private; just do not add setters |
| Rung 2 | same | same, and easier — there is no magic to route around |
| Rung 3 | casts / accessors | embeddables or custom types |
| Rung 5 | a separate model plus a mapper | **often unnecessary** — the entity can *be* the aggregate |

The important row is the last. A Doctrine entity is a plain object with private properties that a
mapper populates by reflection. It has no `save()`, no static finders, no magic attributes. **It is
already most of the way to a domain object**, and the honest advice for a Doctrine codebase is
usually: put the rules on the entity, keep the repository interface in the domain, and do not build
a second model.

The cost that remains is real but small: annotations or attributes on domain classes, and a
`Collection` type from the ORM in your aggregate. Example 02 runs both shapes side by side.

---

## 8. Where else this shows up

Every framework produces the same conversation with different nouns:

| Stack | "The model" is | Rung 5 is |
|-------|----------------|-----------|
| Laravel / Eloquent | an Active Record | a second model + mapper. Expensive. |
| Symfony / Doctrine | a Data Mapper entity | often unnecessary — the entity is the aggregate |
| CodeIgniter, legacy in-house | an array from a query | rung 1 is where the value is; there is no model to improve |
| Rails, Django | an Active Record | identical conversation, different language |

The third row is worth noticing. When the "model" is a raw array, the ladder starts lower and rungs
1–3 are transformative on their own — you are introducing objects where there were none.

---

## 9. Examples

```bash
php module-5-living-with-a-framework/lesson-5.1-when-the-framework-owns-your-models/examples/01-the-ladder.php
php module-5-living-with-a-framework/lesson-5.1-when-the-framework-owns-your-models/examples/02-two-shapes.php
php module-5-living-with-a-framework/lesson-5.1-when-the-framework-owns-your-models/examples/03-theatre-or-worth-it.php
```

Example 03 costs out rung 5 for two applications — one where it pays and one where it does not — and
the difference is not how big they are.

---

## 10. Then

- `challenge/CHALLENGE.md` — one file, three rungs
- `quiz/QUIZ.md` — 20 questions, threshold 15

Next: **5.2**, which is this lesson applied to code you cannot see all of.
