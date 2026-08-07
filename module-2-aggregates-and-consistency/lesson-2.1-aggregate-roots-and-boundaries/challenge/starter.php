<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 2.1 checks
 * ==========================================================
 *
 *     php module-2-aggregates-and-consistency/lesson-2.1-aggregate-roots-and-boundaries/challenge/starter.php
 *
 * Your work goes in domain/workshop/:
 *
 *     LineId.php  Line.php  PartNumber.php  PartLine.php
 *     JobCardStatus.php  JobCardId.php  AccountHolderId.php  JobCard.php
 *
 * ...plus one addition to the LabourLine.php you wrote in Lesson 1.4.
 *
 * This lesson uses Currency, Money, VehicleId, LabourUnits and LabourLine from
 * Module 1. If those are missing, finish Module 1 first — the failures will say
 * "Class not found" and that is what they mean.
 *
 * AND WHEN IT PASSES, RUN THIS TOO:
 *
 *     php module-1-domain-modelling/lesson-1.4-where-invariants-live/challenge/starter.php
 *
 * It must still be green. Changing LabourLine in a way that breaks Lesson 1.4
 * is the one failure mode this challenge is designed around.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');

require __DIR__ . '/checks.php';
