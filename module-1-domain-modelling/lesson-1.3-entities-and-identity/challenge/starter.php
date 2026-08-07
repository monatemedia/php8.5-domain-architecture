<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 1.3 checks
 * ==========================================================
 *
 *     php module-1-domain-modelling/lesson-1.3-entities-and-identity/challenge/starter.php
 *
 * Your work goes in:
 *
 *     domain/workshop/VehicleId.php
 *     domain/workshop/OdometerReading.php
 *     domain/workshop/Vehicle.php
 *
 * This lesson also USES Workshop\RegistrationNumber from Lesson 1.2. If that
 * file is not in domain/workshop/ yet, the Vehicle checks will fail with
 * "Class not found" — go back and finish 1.2 first. That dependency is the
 * accumulation working, not a bug.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');

require __DIR__ . '/checks.php';
