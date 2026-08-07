<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 2.2 checks
 * ==========================================================
 *
 *     php module-2-aggregates-and-consistency/lesson-2.2-transactions-and-consistency/challenge/starter.php
 *
 * Your work goes in domain/workshop/:
 *
 *     CourtesyVehicleId.php
 *     CourtesyFleet.php
 *
 * ...plus one addition to the CourtesyVehicle.php you wrote in Lesson 1.1.
 *
 * AND WHEN IT PASSES, RUN THIS TOO:
 *
 *     php module-1-domain-modelling/lesson-1.1-rich-vs-anemic/challenge/starter.php
 *
 * It must still be green. Adding an id to CourtesyVehicle without disturbing
 * its Lesson 1.1 API is half of what this challenge is testing.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');

require __DIR__ . '/checks.php';
