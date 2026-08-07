# Module 1 — Domain Modelling
> Advanced Domain Architecture & Tactical DDD

| | |
|---|---|
| **Lessons** | 4 |
| **Challenges** | 4, all code |
| **Time** | ~4 hours |
| **Needs PHP** | 8.5+. No Composer, no dependencies. |
| **Output** | `domain/workshop/` — nine classes you keep for the rest of the course |

---

## What this module is

Module 0 decided *whether*. This module is the first *how*.

You build the smallest pieces of the workshop model: the values that cannot be wrong, the entities
that have identity, and the rules that live on them. Nothing here is an aggregate yet — that is
Module 2 — and nothing is persisted — that is Module 3. This is the layer everything else stands on.

By the end you will have written nine classes into `domain/workshop/`, and every one of them will
still be there at the capstone.

---

## The through-line

Every lesson in this module is an instance of one idea:

> **Move the rule to the thing that owns it, and then make it impossible to get past.**

- **1.1** asks where behaviour lives, and shows what it costs when it lives in a service.
- **1.2** applies that at the smallest scale: a value that validates itself and cannot change.
- **1.3** separates the things that have identity from the things that are only their values.
- **1.4** is the hard one: given a rule, *which* object owns it, and what does "owns" mean.

If you finish 1.4 and still find yourself asking "should this check go in the constructor or the
service?", that is the lesson to re-read. It is the most practically useful in the module.

---

## The lessons

### [1.1 — Rich vs Anemic Models](lesson-1.1-rich-vs-anemic/)

The anemic trap: getters, setters, and all the logic in a service. Why that is a procedural program
wearing an object costume, and why it is so common that it is the default rather than a mistake.

**PHP 8.5:** `readonly`, asymmetric visibility (`public private(set)`), constructor promotion.
**You write:** `CourtesyVehicle.php`

---

### [1.2 — Value Objects](lesson-1.2-value-objects/) ⭐

Immutability, structural equality, self-validation. Eliminating primitive obsession: `Money`
instead of `float`, `RegistrationNumber` instead of `string`. Why a value object that can be
constructed in an invalid state is not a value object.

**PHP 8.5:** `readonly class`, `clone($obj, [...])` for withers, `#[\NoDiscard]`.
**You write:** `Currency.php`, `Money.php`, `RegistrationNumber.php`

---

### [1.3 — Entities and Identity](lesson-1.3-entities-and-identity/)

Identity as a continuous thread, independent of attributes. Two vehicles with identical details are
two vehicles; one vehicle with every attribute changed is still that vehicle. Choosing identity:
natural keys, surrogate keys, UUIDs, and who generates them.

**You write:** `VehicleId.php`, `OdometerReading.php`, `Vehicle.php`

---

### [1.4 — Where Invariants Live](lesson-1.4-where-invariants-live/) ⭐

The most practical lesson in the module. Validation in the constructor, in a setter, in a service,
at the API boundary — which belongs where, and why "validate everywhere" is a design failure.
Always-true invariants versus context-dependent rules.

**You write:** `LabourUnits.php`, `LabourLine.php`

---

## What you will have built

```
domain/workshop/
├── CourtesyVehicle.php       1.1   allocation rules, no setters
├── Currency.php              1.2   backed enum, minor-unit precision
├── Money.php                 1.2   non-negative, currency-safe, integer cents
├── RegistrationNumber.php    1.2   normalising, equality by normalised form
├── VehicleId.php             1.3   identity, generated
├── OdometerReading.php       1.3   never decreases
├── Vehicle.php               1.3   an entity: identity independent of attributes
├── LabourUnits.php           1.4   tenths of an hour, rounded up, minimum five
└── LabourLine.php            1.4   a chargeable line, impossible to construct wrong
```

Every one of these is used in Module 2, where `JobCard` becomes an aggregate root over them.

---

## How the challenges work in this module

Different from Module 0, and different from the previous course. Read this once.

**You write into `domain/workshop/`, not into the challenge folder.** The challenge folder holds
the checks and the reference solution; your code lives in the domain directory, because it is
supposed to accumulate.

```
1. Read challenge/CHALLENGE.md   — the brief, in prose
2. Read challenge/checks.php     — the brief, stated precisely. This is the spec.
3. Write your classes in domain/workshop/
4. Run:  php module-1-domain-modelling/lesson-X.Y-name/challenge/starter.php
5. Repeat until it prints:  ACCEPTANCE: all checks passed
```

`checks.php` is not hidden and is not meant to be. Read it first. Working out what a system must do
from its assertions is exactly the skill you use when you inherit a codebase with a test suite and
no documentation.

**`challenge/solution/` holds a working implementation.** Do not open it until you have a genuine
attempt. When you do open it, the interesting question is not "did I get it right" — the checks
already told you — but *"where did they make a different choice, and which of us is right?"* Log
disagreements in the table at the foot of `PROGRESS.md`. Several of the solutions here are one
reasonable option among two or three.

---

## A warning specific to this module

Module 1 is where people who have just read about DDD go and put value objects on everything.

Every class you write here costs a file, a mapping, and a name someone has to learn.
`Money` earns it: currency mixing is a real bug with real money attached. `RegistrationNumber`
earns it: normalisation is a genuine rule that would otherwise be duplicated.
A `Colour` value object wrapping a string with no rules attached earns nothing.

Rule 6 does not stop applying because you are in the module about the patterns. Lesson 1.2's quiz
asks you to reject a proposed value object, and it is the question most people get wrong.

---

*Start with [Lesson 1.1](lesson-1.1-rich-vs-anemic/README.md).*
