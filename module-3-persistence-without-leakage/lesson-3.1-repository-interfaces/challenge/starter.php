<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 3.1 checks
 * ==========================================================
 *
 *     php module-3-persistence-without-leakage/lesson-3.1-repository-interfaces/challenge/starter.php
 *
 * Your work goes in TWO places, and which file goes where is the lesson:
 *
 *     domain/workshop/JobCards.php          the interface — domain vocabulary
 *     domain/workshop/JobCardNotFound.php   a domain exception
 *     infrastructure/InMemoryJobCards.php   the implementation — NOT the domain
 *
 * `infrastructure/` is new in this lesson. It has a README explaining what
 * belongs there; the short version is everything that would stop making sense
 * if you deleted the database.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');
infrastructure_autoload($root . '/infrastructure');

require __DIR__ . '/checks.php';
