<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 4.1 checks
 * ==========================================================
 *
 *     php module-4-domain-exception-trees/lesson-4.1-two-kinds-of-failure/challenge/starter.php
 *
 * You write three new files and modify two you already have:
 *
 *     domain/workshop/WorkshopFailure.php         NEW  the marker interface
 *     domain/workshop/JobCardAlreadyExists.php    NEW  a failure that had no name
 *     domain/workshop/JobCardNotFound.php         EDIT one `implements` clause
 *     infrastructure/StorageFailure.php           NEW  category (b)
 *     infrastructure/GuardedJobCards.php          NEW  the decorator
 *     infrastructure/InMemoryJobCards.php         EDIT one throw site
 *
 * Both edits are additive: nothing is removed and no signature moves, so
 * `php regression.php` must stay green. Run it when this passes.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');
infrastructure_autoload($root . '/infrastructure');

require __DIR__ . '/checks.php';
