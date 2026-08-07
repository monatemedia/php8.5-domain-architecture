<?php
declare(strict_types=1);

/**
 * solution/run.php — the reference implementation, judged by the same checks.
 *
 * Note there is nothing new in the domain for this lesson — the workshop roots
 * are registered only so the examples and any carried-forward classes resolve.
 */

$root = dirname(__DIR__, 4);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/module-3-persistence-without-leakage/lesson-3.2-the-mapping-problem/challenge/solution');
workshop_autoload($root . '/module-3-persistence-without-leakage/lesson-3.1-repository-interfaces/challenge/solution');
workshop_autoload($root . '/module-2-aggregates-and-consistency/lesson-2.4-domain-services/challenge/solution');
workshop_autoload($root . '/module-2-aggregates-and-consistency/lesson-2.3-domain-events/challenge/solution');
workshop_autoload($root . '/module-2-aggregates-and-consistency/lesson-2.2-transactions-and-consistency/challenge/solution');
workshop_autoload($root . '/module-2-aggregates-and-consistency/lesson-2.1-aggregate-roots-and-boundaries/challenge/solution');
workshop_autoload($root . '/module-1-domain-modelling/lesson-1.4-where-invariants-live/challenge/solution');
workshop_autoload($root . '/module-1-domain-modelling/lesson-1.3-entities-and-identity/challenge/solution');
workshop_autoload($root . '/module-1-domain-modelling/lesson-1.2-value-objects/challenge/solution');

infrastructure_autoload(__DIR__ . '/infrastructure');

require dirname(__DIR__) . '/checks.php';
