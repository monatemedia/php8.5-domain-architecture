<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 4.3 checks
 * ==========================================================
 *
 *     php module-4-domain-exception-trees/lesson-4.3-exceptions-vs-results/challenge/starter.php
 *
 * Two small classes and two new methods:
 *
 *     domain/workshop/Refusal.php    NEW  one reason, as a value
 *     domain/workshop/Decision.php   NEW  yes, or every reason why not
 *     domain/workshop/JobCard.php    EDIT checkCanComplete(), checkCanInvoice(),
 *                                         and complete()/invoice() rewritten
 *                                         to go through them
 *
 * After 4.2 this one is short, and the difficulty is all in section 5 of the
 * README: deciding which operations deserve a Decision and which do not.
 * Adding one to every method is the failure mode, not the goal.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');
infrastructure_autoload($root . '/infrastructure');

require __DIR__ . '/checks.php';
