<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 5.2 checks
 * ==========================================================
 *
 *     php module-5-living-with-a-framework/lesson-5.2-strangling-a-legacy-model/challenge/starter.php
 *
 * You write four files:
 *
 *     domain/workshop/PartStock.php                    the ONE aggregate
 *     domain/workshop/InsufficientStock.php            a new leaf, state branch
 *     domain/workshop/InvalidQuantity.php              a new leaf, input branch
 *     infrastructure/Legacy/StockReservations.php      the strangler
 *
 * And you edit one:
 *
 *     infrastructure/ProblemDetailsMapper.php          two catalogue entries
 *
 * The course supplies the legacy system in `challenge/given/` — a row and three
 * services. You do not edit any of it, and two of the three services are still
 * there when you finish. That is an acceptance criterion, not an oversight.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');

infrastructure_autoload($root . '/infrastructure');
infrastructure_autoload(__DIR__ . '/given');

// The miniature ORM, supplied with Lesson 5.1 and reused here.
infrastructure_autoload(dirname(__DIR__, 2) . '/lesson-5.1-when-the-framework-owns-your-models/challenge/given');

require __DIR__ . '/checks.php';
