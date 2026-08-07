<?php
declare(strict_types=1);

/**
 * solution/run.php — the reference implementation, judged by the same checks.
 *
 * Roots in order, first match winning. This lesson's CourtesyVehicle — the one
 * with an id — has to beat Lesson 1.1's, which is why __DIR__ comes first and
 * why 1.1's solution directory is not registered at all.
 */

$root = dirname(__DIR__, 4);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload(__DIR__);
workshop_autoload($root . '/module-2-aggregates-and-consistency/lesson-2.1-aggregate-roots-and-boundaries/challenge/solution');
workshop_autoload($root . '/module-1-domain-modelling/lesson-1.4-where-invariants-live/challenge/solution');
workshop_autoload($root . '/module-1-domain-modelling/lesson-1.3-entities-and-identity/challenge/solution');
workshop_autoload($root . '/module-1-domain-modelling/lesson-1.2-value-objects/challenge/solution');

require dirname(__DIR__) . '/checks.php';
