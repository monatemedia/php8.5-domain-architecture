<?php
declare(strict_types=1);

/**
 * solution/run.php — run the REFERENCE implementation against the same checks
 * ============================================================================
 *
 *     php .../challenge/solution/run.php
 *
 * Identical to starter.php except for which directory the autoloader is
 * pointed at. That is deliberate: the reference implementation is judged by
 * exactly the file that judges you, so a solution that fails its own checks
 * cannot survive a single `php verify.php`.
 *
 * `verify.php` runs this, and every other solution/run.php in the course, on
 * every invocation. The previous course shipped reference solutions that failed
 * their own assertions because nothing ever ran them. This is the fix.
 */

$root = dirname(__DIR__, 4);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

// The reference implementation, which lives beside this file.
workshop_autoload(__DIR__);

require dirname(__DIR__) . '/checks.php';
