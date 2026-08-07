<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 1.1 checks
 * ==========================================================
 *
 *     php module-1-domain-modelling/lesson-1.1-rich-vs-anemic/challenge/starter.php
 *
 * There is nothing to fill in HERE. Your work goes in:
 *
 *     domain/workshop/CourtesyVehicle.php
 *
 * This file loads that directory and runs challenge/checks.php against whatever
 * it finds. Run it as often as you like — it changes nothing and writes nothing.
 *
 * If it reports "Class Workshop\CourtesyVehicle not found", the file does not
 * exist yet, or its namespace is not `Workshop`, or it is not named
 * CourtesyVehicle.php. Those are the only three possibilities.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

// YOUR code. Not the reference solution — that is loaded by solution/run.php.
workshop_autoload($root . '/domain/workshop');

require __DIR__ . '/checks.php';
