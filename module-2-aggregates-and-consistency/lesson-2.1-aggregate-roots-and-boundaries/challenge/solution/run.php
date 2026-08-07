<?php
declare(strict_types=1);

/**
 * solution/run.php — the reference implementation, judged by the same checks.
 *
 * Four autoload roots, tried in order, first match winning:
 *
 *   1. this lesson       — including its LabourLine, which must beat 1.4's
 *   2. Lesson 1.4        — LabourUnits
 *   3. Lesson 1.3        — VehicleId
 *   4. Lesson 1.2        — Currency, Money
 *
 * Order matters for exactly one class. LabourLine exists in both this directory
 * and Lesson 1.4's, and 2.1's version — the one that implements Line — has to
 * be the one that loads.
 */

$root = dirname(__DIR__, 4);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload(__DIR__);
workshop_autoload($root . '/module-1-domain-modelling/lesson-1.4-where-invariants-live/challenge/solution');
workshop_autoload($root . '/module-1-domain-modelling/lesson-1.3-entities-and-identity/challenge/solution');
workshop_autoload($root . '/module-1-domain-modelling/lesson-1.2-value-objects/challenge/solution');

require dirname(__DIR__) . '/checks.php';
