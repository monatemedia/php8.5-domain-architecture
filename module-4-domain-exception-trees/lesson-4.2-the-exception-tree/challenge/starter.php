<?php
declare(strict_types=1);

/**
 * starter.php — run your work against the Lesson 4.2 checks
 * ==========================================================
 *
 *     php module-4-domain-exception-trees/lesson-4.2-the-exception-tree/challenge/starter.php
 *
 * The largest challenge in the course by file count, and the least difficult
 * per file. Thirteen classes, most of them under twenty lines.
 *
 *     domain/workshop/CarriesContext.php                NEW  interface
 *     domain/workshop/WorkshopException.php             NEW  abstract root
 *     domain/workshop/InvalidWorkshopInput.php          NEW  abstract root
 *     domain/workshop/JobCardException.php              NEW  abstract node
 *
 *     domain/workshop/IllegalStatusTransition.php       NEW
 *     domain/workshop/JobCardIsTerminal.php             NEW
 *     domain/workshop/LineNotFound.php                  NEW
 *     domain/workshop/LineCurrencyMismatch.php          NEW
 *     domain/workshop/NothingAwaitingAuthorisation.php  NEW
 *     domain/workshop/OutstandingAuthorisations.php     NEW
 *     domain/workshop/MissingWorkDescription.php        NEW
 *
 *     domain/workshop/JobCardNotFound.php               EDIT re-parent
 *     domain/workshop/JobCardAlreadyExists.php          EDIT re-parent
 *     domain/workshop/JobCard.php                       EDIT seven throw sites
 *     infrastructure/SqliteJobCards.php                 EDIT one throw site
 *
 * Every edit is additive at the type level: each new class is still a
 * \DomainException or a \InvalidArgumentException, so every assertion in
 * Modules 1 to 3 keeps passing. `php regression.php` is part of the acceptance
 * criteria for this one, and it is the check that matters most.
 */

$root = dirname(__DIR__, 3);

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

workshop_autoload($root . '/domain/workshop');
infrastructure_autoload($root . '/infrastructure');

require __DIR__ . '/checks.php';
