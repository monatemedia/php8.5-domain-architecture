# Lesson 1.3 — Entities and Identity
> **Module 1: Domain Modelling** · Advanced Domain Architecture

| | |
|---|---|
| **Time** | ~25 min reading · ~20 min running the examples. Challenge on top. |
| **Comes after** | Lesson 1.2 — Value Objects |
| **Covers** | Identity as a thread · Entity vs value object · Choosing a key · Who generates identity · Equality by identity · Monotonic state |
| **You write** | `VehicleId.php`, `OdometerReading.php`, `Vehicle.php` |
| **Needs PHP** | 8.5+ |

---

## 📁 Lesson folder

```
lesson-1.3-entities-and-identity/
├── README.md
├── examples/
│   ├── 01-identity-vs-equality.php     ← the distinction, run
│   ├── 02-choosing-a-key.php           ← natural, surrogate, UUID, and who generates it
│   └── 03-entity-with-a-rule.php       ← Vehicle: identity fixed, state guarded
├── challenge/
│   ├── CHALLENGE.md
│   ├── checks.php
│   ├── starter.php
│   └── solution/
└── quiz/QUIZ.md
```

---

## 1 — The distinction, in one question

> **Does it make sense to say "this one, specifically"?**

- A vehicle: yes. *That* vehicle, the blue one in bay 3, the one we had in last March. Even after a
  respray, a new engine and a change of owner, it is still that vehicle.
- Five hundred euros: no. There is no *particular* five hundred euros. Any five hundred euros is as
  good as any other, which is exactly why swapping one for an equal one is always safe.

That is the whole distinction, and everything else follows from it.

| | Entity | Value object |
|---|--------|--------------|
| Defined by | Identity | Its attributes |
| Two with identical attributes are | Two things | The same thing |
| Changes over time | Yes, in place | No — replaced |
| Equality | By identity | By structure |
| PHP shape | `final class`, `private(set)` state | `final readonly class` |
| Mutation | Named operations | Withers returning new instances |

---

## 2 — Identity is a thread, not a snapshot

An entity's identity is **continuous through change**.

```php
$vehicle = new Vehicle($id, RegistrationNumber::fromString('AB12CDE'), $odometer);

$vehicle->reRegister(RegistrationNumber::fromString('XY98ZAB'));
$vehicle->recordOdometer(OdometerReading::of(120_000));

// Every attribute has changed. It is still the same vehicle.
```

And the converse:

```php
$a = new Vehicle($idA, $sameRegistration, $sameOdometer);
$b = new Vehicle($idB, $sameRegistration, $sameOdometer);

$a->equals($b);   // false. Identical attributes; two different vehicles.
```

Both directions matter, and the second one catches people. It is the reason an entity's `equals()`
compares **only the identifier** and nothing else. An `equals()` that also compares attributes is a
value object's equality bolted onto an entity, and it will tell you an object is not itself after
somebody edits it.

---

## 3 — The same real thing can be an entity in one context and a value in another

This is where the distinction stops being a definition and becomes a modelling decision.

| Concept | Workshop context | Parts context |
|---------|-----------------|---------------|
| **Vehicle** | An **entity**. It has a history, a bay, faults. *This* one. | A **value**. A fitment key. Two vehicles with the same engine code, year and trim are interchangeable. |
| **Part number** | A **value**. K9K-1234 is K9K-1234. | An **entity** in the catalogue: it has a supersession history, and a superseded part is still *that* part. |

Neither table cell is wrong. Whether something has identity is a fact about **what the context does
with it**, not about the thing itself. Lesson 0.2's bounded contexts and this lesson are the same
subject seen from two angles.

If you cannot decide whether something is an entity, the question is almost never "what is it?" It
is: *does anything in this context need to refer to one particular one over time?*

---

## 4 — Choosing an identifier

Three families, with the trade-off stated honestly.

### Natural key — a business identifier

`RegistrationNumber` for a vehicle. `PartNumber` for a part.

**For:** no extra concept; the business already uses it; two systems agree on it for free.
**Against:** business identifiers change. A vehicle can be re-registered. When a "permanent"
identifier changes, every reference to it is now wrong, and the migration is the kind that runs for
a weekend.

**Rule of thumb:** a natural key is safe only when the business would call it a *different thing*
if the key changed. Registration fails that test — re-register a vehicle and it is the same vehicle.

### Surrogate key — a database-generated integer

**For:** small, fast, indexes well, unambiguous.
**Against:** it does not exist until the row does. That means the object has no identity until it is
saved, so you cannot compare two unsaved entities, cannot reference one from another before saving,
and every constructor grows a `?int $id = null`. Those nulls spread, and Lesson 0.2's shared-model
tell — nullable fields whose population depends on where the object came from — arrives on your
domain model.

### UUID — generated by the application

**For:** the object has identity from the instant it is constructed. No nulls, no save-before-you-
can-reference, and two systems can generate identifiers without talking to each other.
**Against:** 16 bytes rather than 4, and randomly ordered UUIDs fragment database indexes badly —
which is what UUID v7 exists to fix.

> **This course uses application-generated identity, and the reason is Rule 1.** If identity comes
> from the database, the domain model cannot construct a complete object without infrastructure —
> and "you cannot make a valid `Vehicle` without a database connection" is the dependency rule
> broken in the most inconvenient possible place, because it hits every test you will ever write.
>
> This is a real trade-off and not a free win. If you are working in a codebase with surrogate keys,
> Lesson 5.1 covers living with them honestly.

---

## 5 — Wrap the identifier in a type

```php
final readonly class VehicleId { /* ... */ }
```

A `string $id` parameter accepts any string, including a `JobCardId`, an account reference, or
someone's surname. The type system cannot help, because you did not give it anything to work with.

```php
function attach(VehicleId $vehicleId, JobCardId $jobCardId): void
```

Now the arguments cannot be swapped. This is the single cheapest bug prevention in the whole
subject: one small class per identifier, no rules to speak of, and an entire family of
transposition bugs becomes unwritable.

> **Note this is an exception to Lesson 1.2's rule.** `VehicleId` enforces almost nothing — it wraps
> a string with a format check. By section 6 of that lesson it should not exist. It earns its place
> anyway, because identifiers are passed around more than any other value in a system, and the bug
> it prevents is silent. Being able to say *why* an exception is justified is the point; "the rule
> says no but I did it anyway" is not the same thing.

---

## 6 — State that only moves one way

`OdometerReading` is a value object. The **rule that it may not decrease** is not on the reading —
it is on the `Vehicle`, because it is a rule about a *sequence* of readings, and a single reading
has no sequence.

```php
final class Vehicle
{
    public function recordOdometer(OdometerReading $reading): void
    {
        if ($reading->isLessThan($this->odometer)) {
            throw new \DomainException(/* ... */);
        }
        $this->odometer = $reading;
    }
}
```

This is worth pausing on, because it is the pattern for most interesting entity rules:

> **The value object knows what a valid value is. The entity knows what a valid *change* is.**

`OdometerReading::of(-1)` throws, because a negative reading is not a reading. `OdometerReading::of(1)`
succeeds, and `$vehicle->recordOdometer(OdometerReading::of(1))` throws if the vehicle is on 84,500 —
because the *transition* is illegal, not the value.

Lesson 1.4 is entirely about telling those two apart.

---

## 7 — Common mistakes

| Mistake | Why it is wrong | Instead |
|---------|----------------|---------|
| `equals()` comparing attributes on an entity | An edited entity stops being itself | Compare identity only |
| `?int $id = null` until saved | Identity that does not exist yet; nulls spread everywhere | Generate identity at construction |
| Registration as the identifier | It changes, and then every reference is wrong | A surrogate identity; registration is an attribute |
| `string $id` parameters | Nothing stops a `JobCardId` being passed | A typed identifier per concept |
| Making everything an entity | Most of a model is values | Ask: "this one, specifically"? |
| A `readonly` entity | It cannot express its own lifecycle | `private(set)` state, named operations |
| Regenerating identity in a wither | Two objects claiming to be the same thing | Entities do not have withers |

---

## 8 — Where else this shows up

The entity/value split is one of the few pieces of modelling vocabulary that transfers everywhere:

- **React** — `key` on a list item is identity; the props are the value. Getting `key` wrong
  produces exactly the bug this lesson describes: the framework thinks one thing is another.
- **Event sourcing** — a stream has identity; each event is a value, immutable forever.
- **Git** — a commit is a value (its hash *is* its content); a branch is an entity (the same branch
  points at different commits over time).
- **Any REST API** — resources have URIs because they have identity. Representations do not.

If you have ever debugged a list that re-orders itself when re-rendered, you have met an identity
bug.

---

## ✅ Lesson checklist

- [ ] Read this README fully — especially sections 3 and 6
- [ ] Run and study `examples/01-identity-vs-equality.php`
- [ ] Run and study `examples/02-choosing-a-key.php`
- [ ] Run and study `examples/03-entity-with-a-rule.php`
- [ ] Read `challenge/checks.php` before writing anything
- [ ] Complete the challenge
- [ ] Compare against `challenge/solution/` and log disagreements in `PROGRESS.md`
- [ ] Take `quiz/QUIZ.md` closed-book

---

*Next lesson: **1.4 — Where Invariants Live** — the most practically useful lesson in the module.*
