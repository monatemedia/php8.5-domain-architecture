<?php
declare(strict_types=1);

/**
 * lib/regression-run.php — run ONE lesson's checks against the LATEST code
 * =========================================================================
 *
 *     php lib/regression-run.php <path-to-a-challenge/checks.php>
 *
 * Not meant to be run by hand. `regression.php` at the course root invokes it
 * once per lesson, in its own process.
 *
 * WHAT MAKES THIS DIFFERENT FROM solution/run.php
 * ------------------------------------------------
 * A lesson's own `solution/run.php` registers that lesson's directory first and
 * then the ones it depends on. So Lesson 1.1's runner loads Lesson 1.1's
 * CourtesyVehicle — the version without an identity — and proves that the
 * class as written in 1.1 satisfies 1.1.
 *
 * That is worth knowing and it is NOT the guarantee this course actually makes.
 * The student does not keep six copies of CourtesyVehicle; they have one, in
 * domain/workshop/, and Lesson 2.2 edits it. The claim being made is:
 *
 *     the newest version of every class still satisfies every earlier
 *     lesson's specification.
 *
 * So this runner registers EVERY solution directory, NEWEST FIRST, which makes
 * the latest version of each class win. Then it runs an earlier lesson's checks
 * against it. If Lesson 2.2's CourtesyVehicle broke something Lesson 1.1
 * asserted, this is what catches it — and nothing else in the harness would.
 *
 * This file exists because the hole was noticed after both additive changes had
 * already shipped, and "we ran the old solution against the old checks" had
 * been mistaken for a regression test.
 */

$root = dirname(__DIR__);

if (($argv[1] ?? '') === '') {
    fwrite(STDERR, "Usage: php lib/regression-run.php <path-to-checks.php>\n");
    exit(2);
}

$checks = $argv[1];

if (!is_file($checks)) {
    fwrite(STDERR, "No such checks file: {$checks}\n");
    exit(2);
}

require_once $root . '/lib/acceptance.php';
require_once $root . '/lib/autoload-domain.php';

/**
 * Every reference-solution directory, in course order.
 *
 * @return list<string>
 */
function solutionDirsInCourseOrder(string $root): array
{
    $dirs = glob($root . '/module-*/lesson-*/challenge/solution', GLOB_ONLYDIR) ?: [];

    usort($dirs, static function (string $a, string $b): int {
        $key = static function (string $path): int {
            $path = str_replace('\\', '/', $path);
            return preg_match('#/lesson-(\d+)\.(\d+)-#', $path, $m) === 1
                ? ((int) $m[1]) * 1000 + (int) $m[2]
                : 0;
        };

        return $key($a) <=> $key($b);
    });

    return array_values($dirs);
}

// NEWEST FIRST. The autoloader tries roots in registration order and the first
// file that exists wins, so this makes the most recent version of every class
// the one that loads.
//
// From Lesson 3.1 a solution directory may also contain an `infrastructure/`
// subdirectory. Registering both roots keeps the placement checks meaningful
// during a regression sweep.
foreach (array_reverse(solutionDirsInCourseOrder($root)) as $dir) {
    workshop_autoload($dir);

    if (is_dir($dir . '/infrastructure')) {
        infrastructure_autoload($dir . '/infrastructure');
    }
}

require $checks;
