<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 1.2 checks
 * ==========================================================
 *
 *     php module-1-domain-modelling/lesson-1.2-value-objects/challenge/starter.php
 *
 * Nothing to fill in here. Your work goes in:
 *
 *     domain/workshop/Currency.php
 *     domain/workshop/Money.php
 *     domain/workshop/RegistrationNumber.php
 *
 * Build them in that order — Money needs Currency. RegistrationNumber is
 * independent and is the easier warm-up if you would rather start there.
 *
 * Expect a long list of failures on the first run. That is the specification
 * being read to you, not a judgement.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');

require __DIR__ . '/checks.php';
