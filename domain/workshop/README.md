# `domain/workshop/` — your codebase

This directory is empty, and that is correct. **You fill it.**

Every challenge from Lesson 1.1 onwards writes here, and nothing is ever deleted. By the capstone
this is a workshop domain model you built yourself, not a folder of unrelated exercises.

---

## The rules of this directory

**Namespace `Workshop`, one class per file, named after the class.**

```php
<?php
declare(strict_types=1);

namespace Workshop;

final class CourtesyVehicle { /* ... */ }
```

→ `domain/workshop/CourtesyVehicle.php`

Sub-namespaces map to sub-directories: `Workshop\Exception\JobCardException` →
`domain/workshop/Exception/JobCardException.php`. That arrives in Module 4.

`lib/autoload-domain.php` does the loading. It is nine lines and worth reading once.

**Nothing in here may import anything outside `Workshop`.**

No Slim, no PDO, no PHP-DI, no framework, no `getenv()`, no `time()`. Every challenge checks this,
and the capstone proves it for the whole directory with a test that scans the imports.

Global built-ins are fine — `\InvalidArgumentException`, `\DomainException` and the SPL classes are
part of PHP, not a dependency. Note the leading backslash: inside `namespace Workshop`, an unqualified
`InvalidArgumentException` would refer to `Workshop\InvalidArgumentException`, which does not exist.

---

## What goes here, by lesson

| Lesson | Files |
|--------|-------|
| 1.1 | `CourtesyVehicle.php` |
| 1.2 | `Currency.php`, `Money.php`, `RegistrationNumber.php` |
| 1.3 | `VehicleId.php`, `OdometerReading.php`, `Vehicle.php` |
| 1.4 | `LabourUnits.php`, `LabourLine.php` |

Later lessons build on these rather than replacing them. If Lesson 1.3's checks report
`Class Workshop\RegistrationNumber not found`, go back and finish 1.2 — that dependency is the
accumulation working, not a bug.

---

## Rebuilding one

Nothing here is precious. If you want to redo a lesson, delete the files and run its `starter.php`
again — everything will fail, and the failures are the specification being read back to you.
