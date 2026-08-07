<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the capstone checks
 * ========================================================
 *
 *     php module-6-capstone/lesson-6.1-the-workshop-end-to-end/challenge/starter.php
 *
 * The last challenge in the course. You write:
 *
 *     domain/workshop/OpenJobCard.php          application services -
 *     domain/workshop/StartWork.php            orchestration only, no rules
 *     domain/workshop/CompleteJobCard.php
 *     domain/workshop/InvoiceJobCard.php
 *
 *     infrastructure/ImportScan.php            the course's thesis, executable
 *     infrastructure/WorkshopApp.php           the composition root
 *     infrastructure/Http/JobCardsController.php
 *     infrastructure/Http/ProblemDetailsMiddleware.php
 *     infrastructure/Http/UnexpectedFailureLog.php
 *
 * Everything else already exists. That is the point of the capstone: five
 * modules of decisions, assembled, with nothing rewritten.
 *
 * This is the first challenge that needs `composer install` for more than
 * PHPUnit — Slim 4 and PHP-DI 7 are both used for real, and the checks drive
 * actual PSR-7 requests through an actual Slim application against an actual
 * SQLite database. Nothing here is mocked.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/vendor/autoload.php';
require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');
infrastructure_autoload($root . '/infrastructure');

require __DIR__ . '/checks.php';
