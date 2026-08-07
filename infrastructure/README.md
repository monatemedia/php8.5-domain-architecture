# `infrastructure/` — everything that is not the domain

Added in Lesson 3.1, because that is the first lesson with something to put here.

**Namespace `Infrastructure`.** `Infrastructure\InMemoryJobCards` lives in
`infrastructure/InMemoryJobCards.php`.

---

## What belongs here

Anything that fails the question the whole course is built on:

> *If I deleted my framework, my database driver and my web server tomorrow morning, would the code
> in this file still make sense to someone who runs the business?*

- Repository **implementations** — in-memory, SQLite, whatever comes next
- Mappers that turn rows into aggregates and back
- Controllers, middleware, HTTP concerns
- Dispatchers, queue adapters, clocks

## What does not

The repository **interface** is domain vocabulary — `JobCards::awaitingAuthorisation()` is a
sentence the workshop would recognise — so it lives in `domain/workshop/`, and the SQL never does.
That inversion is the whole of Lesson 3.1.

---

## The rule that is enforced

`domain/workshop/` may not import anything from here. Every challenge checks it, and the capstone
proves it for the whole directory with a test that scans the imports.

The arrow points one way: **infrastructure knows about the domain; the domain knows nothing.**

---

## Like `domain/workshop/`, this is yours

`infrastructure/*.php` is git-ignored — your answers to the challenges live here, and a fresh clone
should start empty. The reference implementations are in each lesson's `challenge/solution/`.
