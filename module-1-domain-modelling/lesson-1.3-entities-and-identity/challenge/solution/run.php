<?php
declare(strict_types=1);

/**
 * solution/run.php — the reference implementation, judged by the same checks.
 *
 * Two autoload roots. The first is this lesson's reference classes; the second
 * is Lesson 1.2's, because Vehicle takes a RegistrationNumber and the reference
 * run must not depend on the student having finished anything.
 *
 * Roots are tried in registration order and the first file that exists wins, so
 * this lesson's own classes always take precedence.
 */

$root = dirname(__DIR__, 4);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload(__DIR__);
workshop_autoload($root . '/module-1-domain-modelling/lesson-1.2-value-objects/challenge/solution');

require dirname(__DIR__) . '/checks.php';
