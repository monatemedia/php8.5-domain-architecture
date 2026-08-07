<?php
declare(strict_types=1);

/**
 * regression.php — does the LATEST code still satisfy EVERY earlier lesson?
 * ==========================================================================
 *
 *     php regression.php
 *
 * `verify.php` runs this as one of its steps. It is here as a top-level script
 * too, because when it fails you will want to run it on its own.
 *
 *
 * THE GUARANTEE THIS COURSE MAKES
 * --------------------------------
 * Classes accumulate. You write Money in Lesson 1.2 and it is still there at
 * the capstone. Some lessons change earlier classes: 2.1 gives LabourLine an
 * identity, 2.2 gives CourtesyVehicle one. Both changes are meant to be purely
 * additive, and both challenges end with "re-run the earlier lesson's checks".
 *
 * The claim is therefore:
 *
 *     the newest version of every class still satisfies every earlier
 *     lesson's specification.
 *
 * Nothing else in the harness checks that. `verify.php` step 4 runs each
 * lesson's own solution against its own checks, which proves each lesson is
 * internally consistent and says nothing about whether 2.2 broke 1.1 — because
 * 1.1's runner loads 1.1's copy of CourtesyVehicle, not 2.2's.
 *
 * The student has ONE copy, in domain/workshop/. This script tests the thing
 * the student actually has.
 *
 *
 * HOW
 * ---
 * One process per lesson. Every solution directory is registered NEWEST FIRST,
 * so the most recent version of each class wins, and then an earlier lesson's
 * checks.php is run against it.
 *
 * Separate processes because the lessons deliberately reuse class names, and
 * because each checks.php ends by calling acceptance_report(), which exits.
 */

$root = __DIR__;
$php  = escapeshellarg(PHP_BINARY);

$checkFiles = glob($root . '/module-*/lesson-*/challenge/checks.php') ?: [];

usort($checkFiles, static function (string $a, string $b): int {
    $key = static function (string $path): int {
        $path = str_replace('\\', '/', $path);
        return preg_match('#/lesson-(\d+)\.(\d+)-#', $path, $m) === 1
            ? ((int) $m[1]) * 1000 + (int) $m[2]
            : 0;
    };

    return $key($a) <=> $key($b);
});

if ($checkFiles === []) {
    echo "No challenges found — nothing to regress against.\n";
    exit(0);
}

echo "Running " . count($checkFiles) . " lesson spec(s) against the latest version of every class.\n";
echo str_repeat('-', 72) . "\n";

$failures = [];

foreach ($checkFiles as $checks) {
    $relative = str_replace(str_replace('\\', '/', $root) . '/', '', str_replace('\\', '/', $checks));
    $lesson   = preg_match('#/lesson-(\d+\.\d+)-#', $relative, $m) === 1 ? $m[1] : '?';

    printf('  Lesson %-6s ', $lesson);

    $out = [];
    $rc  = 0;
    exec(
        $php . ' ' . escapeshellarg($root . '/lib/regression-run.php')
        . ' ' . escapeshellarg($checks) . ' 2>&1',
        $out,
        $rc
    );

    $text = implode("\n", $out);

    if ($rc === 0 && str_contains($text, 'ACCEPTANCE: all checks passed')) {
        echo "PASS\n";
        continue;
    }

    echo "FAIL\n";
    $failures[$lesson] = $text;
}

echo str_repeat('-', 72) . "\n";
printf("%d of %d lesson specs still hold.\n", count($checkFiles) - count($failures), count($checkFiles));

if ($failures === []) {
    echo "\nEvery earlier lesson still passes against the latest code.\n";
    exit(0);
}

foreach ($failures as $lesson => $text) {
    echo "\n" . str_repeat('=', 72) . "\nLesson {$lesson}\n" . str_repeat('=', 72) . "\n";

    // Only the failing lines — the passing ones are noise here.
    foreach (explode("\n", $text) as $line) {
        if (str_contains($line, '[ FAIL ]')
            || str_contains($line, 'ACCEPTANCE:')
            || preg_match('/(Fatal error|Parse error|Uncaught)/i', $line) === 1) {
            echo $line . "\n";
        }
    }
}

echo "\n";
echo "A lesson that used to pass and now does not means a later lesson changed\n";
echo "a class in a way that broke an earlier contract. That is a course bug,\n";
echo "and the fix belongs in the LATER lesson — the earlier spec is the thing\n";
echo "being protected.\n\n";

exit(1);
