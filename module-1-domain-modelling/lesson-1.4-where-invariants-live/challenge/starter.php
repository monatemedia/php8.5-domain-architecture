<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 1.4 checks
 * ==========================================================
 *
 *     php module-1-domain-modelling/lesson-1.4-where-invariants-live/challenge/starter.php
 *
 * Your work goes in:
 *
 *     domain/workshop/LabourUnits.php
 *     domain/workshop/LabourLine.php
 *
 * Uses Workshop\Money and Workshop\Currency from Lesson 1.2.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');

require __DIR__ . '/checks.php';
