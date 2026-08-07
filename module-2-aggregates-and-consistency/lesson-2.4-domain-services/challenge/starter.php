<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 2.4 checks
 * ==========================================================
 *
 *     php module-2-aggregates-and-consistency/lesson-2.4-domain-services/challenge/starter.php
 *
 * Your work goes in domain/workshop/:
 *
 *     TaxRate.php                      a VALUE OBJECT
 *     CourtesyVehicleEligibility.php   a DOMAIN SERVICE
 *
 * Two classes, and the lesson is knowing which is which — plus one operation
 * you must NOT write, which the checks also test for.
 *
 * Nothing else changes. JobCard is untouched.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');

require __DIR__ . '/checks.php';
