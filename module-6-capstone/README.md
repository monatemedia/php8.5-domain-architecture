# Module 6 — Capstone
> Not built yet.

The workshop, end to end: the domain model from Module 1, aggregates and events from Module 2,
persistence from Module 3, and the exception tree from Module 4, behind a Slim API using the
container from *PHP 8.5 OOP Mastery*.

**Acceptance:** the entire `domain/` directory has zero imports from Slim, PDO, PHP-DI or anything
else infrastructural, proved by a test that scans the imports.

That test is the course thesis in executable form — for a codebase built this way from the start.
Lesson 5.1 is explicit that it is an ideal to reason with, not a standard to hold every existing
application to.
