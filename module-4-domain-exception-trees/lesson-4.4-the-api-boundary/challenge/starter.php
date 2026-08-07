<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 4.4 checks
 * ==========================================================
 *
 *     php module-4-domain-exception-trees/lesson-4.4-the-api-boundary/challenge/starter.php
 *
 * Two classes, and NEITHER of them is in the domain:
 *
 *     infrastructure/ProblemDetails.php        NEW  an RFC 9457 document
 *     infrastructure/ProblemDetailsMapper.php  NEW  anything thrown -> a safe body
 *
 * `domain/workshop/` is not touched by this lesson at all. That is the whole
 * point of it: everything the boundary needs was already there, as
 * failureCode() and context(), and none of it knows what HTTP is.
 *
 * There is no Slim, no PSR-7 and no middleware here. The mapper is a pure
 * function from a Throwable to a value, which is what makes it testable
 * without a web server. The capstone wires it into an actual request cycle.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');
infrastructure_autoload($root . '/infrastructure');

require __DIR__ . '/checks.php';
