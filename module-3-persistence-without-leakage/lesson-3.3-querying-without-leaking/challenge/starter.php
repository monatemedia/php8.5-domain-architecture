<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 3.3 checks
 * ==========================================================
 *
 *     php module-3-persistence-without-leakage/lesson-3.3-querying-without-leaking/challenge/starter.php
 *
 * Your work, both files in infrastructure/:
 *
 *     infrastructure/JobCardSummary.php       a read model
 *     infrastructure/JobCardSummaryQuery.php  a query service
 *
 * NOTHING goes in domain/workshop/ this time, and that is the lesson. Lesson
 * 3.1 put the repository interface in the domain; this one puts nothing there,
 * for a reason the README spends a section on.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');
infrastructure_autoload($root . '/infrastructure');

require __DIR__ . '/checks.php';
