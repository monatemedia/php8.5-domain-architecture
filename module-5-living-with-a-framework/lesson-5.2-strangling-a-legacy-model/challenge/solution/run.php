<?php
declare(strict_types=1);

/**
 * solution/run.php — the reference implementation, judged by the same checks.
 */

$root = dirname(__DIR__, 4);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload(__DIR__);
workshop_autoload($root . '/module-4-domain-exception-trees/lesson-4.3-exceptions-vs-results/challenge/solution');
workshop_autoload($root . '/module-4-domain-exception-trees/lesson-4.2-the-exception-tree/challenge/solution');
workshop_autoload($root . '/module-4-domain-exception-trees/lesson-4.1-two-kinds-of-failure/challenge/solution');
workshop_autoload($root . '/module-3-persistence-without-leakage/lesson-3.4-testing-persistence/challenge/solution');
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
infrastructure_autoload(dirname(__DIR__) . '/given');
infrastructure_autoload($root . '/module-5-living-with-a-framework/lesson-5.1-when-the-framework-owns-your-models/challenge/given');
infrastructure_autoload($root . '/module-4-domain-exception-trees/lesson-4.4-the-api-boundary/challenge/solution/infrastructure');
infrastructure_autoload($root . '/module-4-domain-exception-trees/lesson-4.2-the-exception-tree/challenge/solution/infrastructure');
infrastructure_autoload($root . '/module-4-domain-exception-trees/lesson-4.1-two-kinds-of-failure/challenge/solution/infrastructure');
infrastructure_autoload($root . '/module-3-persistence-without-leakage/lesson-3.4-testing-persistence/challenge/solution/infrastructure');
infrastructure_autoload($root . '/module-3-persistence-without-leakage/lesson-3.3-querying-without-leaking/challenge/solution/infrastructure');
infrastructure_autoload($root . '/module-3-persistence-without-leakage/lesson-3.2-the-mapping-problem/challenge/solution/infrastructure');
infrastructure_autoload($root . '/module-3-persistence-without-leakage/lesson-3.1-repository-interfaces/challenge/solution/infrastructure');

require dirname(__DIR__) . '/checks.php';
