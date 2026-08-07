<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 3.4 contract
 * ============================================================
 *
 *     php module-3-persistence-without-leakage/lesson-3.4-testing-persistence/challenge/starter.php
 *
 * Your work:
 *
 *     infrastructure/SqliteJobCards.php
 *
 * One file. Everything else in this lesson is the CONTRACT — the same
 * assertions, run against your in-memory repository from 3.1 and your SQLite
 * one, to prove they are substitutable.
 *
 * Expect the in-memory half to pass immediately. It already did in 3.1.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');
infrastructure_autoload($root . '/infrastructure');

require __DIR__ . '/checks.php';
