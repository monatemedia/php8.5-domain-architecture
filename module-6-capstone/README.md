# Module 6 — Capstone

> **Built and verified on PHP 8.5.7.** One lesson, one challenge, one quiz, and Gate 6 in `GATES.md`.

| Lesson | Subject | You build |
|--------|---------|-----------|
| [6.1](lesson-6.1-the-workshop-end-to-end/) | The workshop, end to end | four use cases, `ImportScan`, a Slim app, a PHP-DI container, a Problem Details middleware |

---

## What it assembles, and what it adds

The domain model from Module 1, aggregates and events from Module 2, persistence from Module 3, the
exception tree from Module 4 — behind a Slim 4 API wired with PHP-DI.

**It adds no business rules.** Four use cases that orchestrate and decide nothing, a controller with
no `try` in it, a middleware whose entire error-handling logic is about twenty lines, and one class
that reads files.

If assembling it had required changing the domain, the domain was wrong. That is the test, and it is
the one you can only run at the end.

---

## The result

```
87 files scanned in the domain layer.
Every name they refer to, in full:

    DomainException
    InvalidArgumentException
    NoDiscard
    Throwable
```

Not "no framework imports" — **no imports**. Domain classes share a namespace, so they name each
other unqualified; there is not one `use` statement in the layer. The only names that survive the
tokeniser are the SPL types the exception tree extends.

Delete Slim, PDO, PHP-DI and the web server. There is nothing left to remove.

That is the course's thesis, and `verify.php` now fails if it stops being true.

---

## Two things the assembly exposed

Neither was visible from inside any single lesson. That is what a capstone is for, and it is why
"we will integrate at the end" is such an expensive plan.

**`remove()` then `add()`.** Lesson 3.1 chose a collection-oriented repository with no `save()`,
warned it had a cost, and refused to design around it. Here is the bill — and it is worse than ugly,
because between the two commits the job card does not exist.

**A malformed identity returns 500.** `VehicleId::fromString('')` throws a bare
`\InvalidArgumentException`, so the mapper has nothing to key on and correctly refuses to guess.
Module 4 named every throw site in `JobCard` and left the value objects to the reader; nothing
noticed for two modules, because nothing had asked a value object to refuse something across an HTTP
boundary.

**The checks assert both rather than working around them.**

---

## Running it

```bash
composer install     # Slim 4 and PHP-DI 7 are used for real from here
php verify.php
php check.php
```

The capstone's checks drive actual PSR-7 requests through an actual Slim application against an
actual SQLite database. Nothing is mocked.
