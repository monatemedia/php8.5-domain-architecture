<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 3.2 checks
 * ==========================================================
 *
 *     php module-3-persistence-without-leakage/lesson-3.2-the-mapping-problem/challenge/starter.php
 *
 * Your work, in both layers:
 *
 *   domain/workshop/LabourLine.php   + reconstitute()
 *   domain/workshop/PartLine.php     + reconstitute()
 *   domain/workshop/JobCard.php      + reconstitute()
 *   infrastructure/JobCardMapper.php   new
 *
 * All three domain changes are ADDITIVE. Nothing is removed and no existing
 * signature moves, so `php regression.php` must stay green — and it is the
 * check that matters here, because you are editing three classes that four
 * earlier lessons specified.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');
infrastructure_autoload($root . '/infrastructure');

require __DIR__ . '/checks.php';
