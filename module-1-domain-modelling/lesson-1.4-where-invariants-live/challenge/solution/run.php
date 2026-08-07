<?php
declare(strict_types=1);

/**
 * solution/run.php — the reference implementation, judged by the same checks.
 *
 * The second autoload root supplies Money and Currency from Lesson 1.2, so this
 * run does not depend on the student having finished anything.
 */

$root = dirname(__DIR__, 4);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload(__DIR__);
workshop_autoload($root . '/module-1-domain-modelling/lesson-1.2-value-objects/challenge/solution');

require dirname(__DIR__) . '/checks.php';
