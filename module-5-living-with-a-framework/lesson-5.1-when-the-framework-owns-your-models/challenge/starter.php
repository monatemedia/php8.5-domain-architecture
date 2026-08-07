<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 5.1 checks
 * ==========================================================
 *
 *     php module-5-living-with-a-framework/lesson-5.1-when-the-framework-owns-your-models/challenge/starter.php
 *
 * You write ONE file:
 *
 *     infrastructure/Legacy/JobCardRecord.php
 *
 * The course supplies `Infrastructure\Legacy\ActiveRecord` in `challenge/given/`
 * — a sixty-line Active Record base you can read in full. You do not edit it,
 * and you cannot: in the situation this lesson is about, the framework is not
 * yours to change.
 *
 * NOTHING IN `domain/workshop/` CHANGES. You will use JobCardStatus, Money,
 * Currency, JobCardId and IllegalStatusTransition exactly as they are, from an
 * object that extends an ORM base class. That is most of the lesson.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');

// Your work first, then the code the course supplies.
infrastructure_autoload($root . '/infrastructure');
infrastructure_autoload(__DIR__ . '/given');

require __DIR__ . '/checks.php';
