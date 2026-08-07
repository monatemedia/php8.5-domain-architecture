<?php
declare(strict_types=1);

/**
 * solution/run.php — the reference implementation, judged by the same checks.
 *
 * Run by verify.php on every invocation. See the note in
 * lesson-1.1-rich-vs-anemic/challenge/solution/run.php for why.
 */

$root = dirname(__DIR__, 4);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload(__DIR__);

require dirname(__DIR__) . '/checks.php';
