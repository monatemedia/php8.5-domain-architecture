<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 2.3 checks
 * ==========================================================
 *
 *     php module-2-aggregates-and-consistency/lesson-2.3-domain-events/challenge/starter.php
 *
 * Your work goes in domain/workshop/:
 *
 *     DomainEvent.php  RecordsDomainEvents.php
 *     JobCardOpened.php  JobCardCompleted.php  JobCardInvoiced.php
 *
 * ...plus one addition to the JobCard.php you wrote in Lesson 2.1.
 *
 * AND WHEN IT PASSES:
 *
 *     php regression.php
 *
 * JobCard changes here, so 2.1's specification is the thing at risk. The
 * regression sweep runs every earlier lesson against your newest code.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');

require __DIR__ . '/checks.php';
