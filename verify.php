<?php
declare(strict_types=1);

/**
 * verify.php — is the COURSE intact?
 * ===================================
 *
 *     php verify.php
 *
 * Answers one question and no other: are the course files themselves sound?
 * Nothing here is about you or your progress — that is `check.php`.
 *
 *   1. PHP is 8.5 or newer
 *   2. The 8.4 / 8.5 language features the lessons rely on actually work here
 *   3. Every .php file in the course parses
 *   4. Every reference solution passes its own acceptance checks
 *   5. Composer dependencies are present (needed from Module 3 onwards)
 *
 * Step 4 is the one that matters, and it is the reason this file exists in this
 * form. The previous course shipped reference solutions that failed their own
 * assertions, because nothing ever ran them. Running every solution on every
 * verify makes that class of bug impossible to ship twice.
 *
 * It modifies nothing. Exit 0 = green, 1 = something to fix.
 */

$root     = __DIR__;
$failures = 0;
$warnings = 0;

function heading(string $text): void {
    echo "\n" . str_repeat('=', 72) . "\n  {$text}\n" . str_repeat('=', 72) . "\n\n";
}
function pass(string $m): void { echo "  [ OK ]   {$m}\n"; }
function warn(string $m): void { global $warnings; $warnings++; echo "  [ WARN ] {$m}\n"; }
function fail(string $m): void { global $failures; $failures++; echo "  [ FAIL ] {$m}\n"; }

/** Run a command, return [exitCode, combinedOutput]. */
function shell(string $cmd): array {
    $out = []; $rc = 0;
    exec($cmd . ' 2>&1', $out, $rc);
    return [$rc, implode("\n", $out)];
}

$php = escapeshellarg(PHP_BINARY);


// ─────────────────────────────────────────────────────────────────────────────
heading('1. PHP version');

echo "  Detected: PHP " . PHP_VERSION . "  (" . PHP_BINARY . ")\n\n";

if (PHP_VERSION_ID >= 80500) {
    pass('PHP 8.5+ — every file in this course will run.');
} elseif (PHP_VERSION_ID >= 80400) {
    warn('PHP ' . PHP_VERSION . ' detected. The course targets 8.5.');
    echo "           What actually breaks on 8.4, exhaustively:\n";
    echo "             - three EXAMPLE files that exist to demonstrate 8.5 syntax\n";
    echo "               (clone with, static asymmetric visibility). Skipped below.\n";
    echo "             - #[\\NoDiscard] parses but does nothing, so the discarded-\n";
    echo "               wither warning never fires.\n";
    echo "           What does NOT break: every challenge, every reference solution,\n";
    echo "           and every other example. Modules 0 and 1 are usable on 8.4.\n";
    echo "\n";
    echo "           To get 8.5 in Herd: open Herd, go to the PHP tab, install 8.5,\n";
    echo "           and set it as the global version — then re-run this. A global\n";
    echo "           override silently wins over any per-site setting, so check both.\n";
} else {
    fail('PHP ' . PHP_VERSION . ' is too old. Property hooks alone need 8.4.');
    echo "           Install PHP 8.5 via Herd's PHP tab and set it as the global\n";
    echo "           version, then re-run this.\n";
}


// ─────────────────────────────────────────────────────────────────────────────
heading('2. Language features the lessons rely on');

/**
 * Each probe must parse, run, and echo PROBE_OK as its last act. Checking for
 * that sentinel is far more reliable than trusting an exit code, and a probe
 * that throws reports its real error instead of a useless "failed to run".
 *
 * Never end a probe with a top-level `return` — that halts the script before
 * the sentinel is echoed. Assert by throwing instead.
 */
$probes = [
    'Readonly classes (8.2)' =>
        'readonly class R82 { public function __construct(public int $n) {} }
         if ((new R82(5))->n !== 5) { throw new RuntimeException("readonly class failed"); }',

    'Enums, backed (8.1)' =>
        'enum E81: string { case A = "a"; }
         if (E81::from("a") !== E81::A) { throw new RuntimeException("enum from() failed"); }',

    'Enums with methods (8.1)' =>
        'enum S81: string { case Open = "open"; case Done = "done";
             public function isFinal(): bool { return $this === self::Done; } }
         if (S81::Open->isFinal()) { throw new RuntimeException("enum method failed"); }',

    'First-class callable syntax (8.1)' =>
        'final class F81 { public function n(int $i): int { return $i * 2; } }
         $f = (new F81())->n(...);
         if ($f(3) !== 6) { throw new RuntimeException("fcc failed"); }',

    'Asymmetric visibility, instance (8.4)' =>
        'class A84 { public private(set) string $s = "x"; }
         if ((new A84())->s !== "x") { throw new RuntimeException("aviz read failed"); }',

    'Asymmetric visibility, static (8.5)' =>
        'class A85 { public static private(set) string $s = "x"; }
         if (A85::$s !== "x") { throw new RuntimeException("static aviz read failed"); }',

    'Property hooks (8.4)' =>
        'class P84 { public string $v = "" { set(string $x) => trim($x); } }
         $o = new P84(); $o->v = "  a  ";
         if ($o->v !== "a") { throw new RuntimeException("set hook did not trim"); }',

    // A readonly property may only be replaced by clone-with from INSIDE the
    // declaring class. From the outside it throws "Cannot modify readonly
    // property" — which is exactly why every wither in this course is a method
    // on the class rather than a helper beside it.
    'clone with — clone($obj, [...]) (8.5)' =>
        'final class C85 { public function __construct(public readonly int $n) {}
             public function withN(int $n): static { return clone($this, ["n" => $n]); } }
         $a = new C85(1); $b = $a->withN(2);
         if ($a->n !== 1 || $b->n !== 2) { throw new RuntimeException("clone-with returned wrong values"); }',

    // CAREFUL. Attributes parse on every version since 8.0 — an unknown one is
    // simply ignored — so writing #[\NoDiscard] and checking that the function
    // still works passes on 8.4, where the attribute does precisely nothing.
    // That is a false green, and it was in this file until a run on 8.4 exposed
    // it. Probe for the attribute CLASS, which only exists from 8.5.
    '#[\\NoDiscard] attribute (8.5)' =>
        '#[\\NoDiscard("use it")] function nd85(): int { return 1; }
         if (!class_exists("NoDiscard")) {
             throw new RuntimeException("the NoDiscard attribute class does not exist, so the attribute is inert here");
         }
         if (nd85() !== 1) { throw new RuntimeException("NoDiscard function misbehaved"); }',

    'Deep clone of a nested value object' =>
        // Module 2 depends on this: an aggregate that hands out its internal
        // collection must hand out a copy, and the copy must not share objects.
        'final class Leaf { public function __construct(public readonly int $v) {} }
         final class Tree { public function __construct(public array $leaves) {} }
         $t = new Tree([new Leaf(1)]);
         $c = clone $t; $c->leaves[] = new Leaf(2);
         if (count($t->leaves) !== 1) { throw new RuntimeException("array property not copied on clone"); }',
];

$probeDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php85domain_probe';
@mkdir($probeDir, 0777, true);

/**
 * The version a probe needs, read out of its own label.
 *
 * Running a 8.5 probe on 8.4 and calling the result a FAILURE is not useful —
 * nothing is broken, the feature simply is not there yet. Those are reported as
 * unavailable instead, so the failure count means what it says.
 */
$requiredVersionOf = static function (string $label): int {
    if (preg_match('/\((\d)\.(\d)\)/', $label, $m) === 1) {
        return ((int) $m[1]) * 10000 + ((int) $m[2]) * 100;
    }
    return 0;
};

foreach ($probes as $label => $code) {
    $required = $requiredVersionOf($label);

    if (PHP_VERSION_ID < $required) {
        warn($label . ' — not available on PHP ' . PHP_VERSION . '. Not a failure.');
        continue;
    }

    $file = $probeDir . DIRECTORY_SEPARATOR . 'probe_' . md5($label) . '.php';
    file_put_contents($file, "<?php\ndeclare(strict_types=1);\n" . $code . "\necho 'PROBE_OK';\n");

    [$status, $text] = shell($php . ' ' . escapeshellarg($file));
    $text = trim($text);

    if ($status === 0 && str_contains($text, 'PROBE_OK')) {
        pass($label);
    } else {
        $reason = 'no output, exit code ' . $status;
        foreach (explode("\n", $text) as $l) {
            $l = trim($l);
            if ($l !== '' && $l !== 'PROBE_OK') { $reason = $l; break; }
        }
        fail($label . "\n           " . $reason);
    }
    @unlink($file);
}
@rmdir($probeDir);


// ─────────────────────────────────────────────────────────────────────────────
heading('3. Syntax check — every .php file in the course');

$skipDirs = ['/vendor/', '/.git/', '/node_modules/', '/.phpunit.cache/'];

$files = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $f) {
    /** @var SplFileInfo $f */
    if ($f->getExtension() !== 'php') { continue; }
    $path = str_replace('\\', '/', $f->getPathname());
    foreach ($skipDirs as $skip) {
        if (str_contains($path, $skip)) { continue 2; }
    }
    if ($f->getFilename() === 'verify.php') { continue; }
    $files[] = $f->getPathname();
}
sort($files);

/**
 * Files that CANNOT parse below PHP 8.5, because demonstrating 8.5 syntax is
 * what they are for.
 *
 * On an older runtime these are reported as SKIPPED rather than FAILED. The
 * distinction matters: "this file is broken" and "your PHP is a version behind"
 * need completely different actions, and a verify that cannot tell them apart
 * hands you six failures when the honest count is one.
 *
 * A parse error is a compile-time event, so there is no version guard that can
 * rescue a file from inside itself. Listing them here is the only honest option.
 *
 * @var list<string> paths relative to the course root, forward slashes
 */
$requiresPhp85 = [
    'module-1-domain-modelling/lesson-1.1-rich-vs-anemic/examples/04-asymmetric-visibility.php',
    'module-1-domain-modelling/lesson-1.2-value-objects/examples/03-withers-and-clone-with.php',
    'module-1-domain-modelling/lesson-1.4-where-invariants-live/examples/01-four-places-one-rule.php',
];

/** Trim the course root off a path so messages stay readable in a narrow terminal. */
$relative = static function (string $path) use ($root): string {
    $p = str_replace('\\', '/', $path);
    $r = rtrim(str_replace('\\', '/', $root), '/') . '/';
    return str_starts_with($p, $r) ? substr($p, strlen($r)) : $p;
};

$broken  = [];
$skipped = [];

foreach ($files as $file) {
    $rel = $relative($file);

    if (PHP_VERSION_ID < 80500 && in_array($rel, $requiresPhp85, true)) {
        $skipped[] = $rel;
        continue;
    }

    [$status, $out] = shell($php . ' -l ' . escapeshellarg($file));
    if ($status !== 0) {
        // php -l repeats the whole absolute path three times. Strip the root so
        // the actual error is visible without horizontal scrolling.
        $msg = str_replace([$root . DIRECTORY_SEPARATOR, $root . '/'], '', $out);
        $broken[$rel] = trim(str_replace("\n", "\n           ", $msg));
    }
}

$checked = count($files) - count($skipped);
echo "  Checked {$checked} files.\n\n";

if ($broken === []) {
    pass("{$checked} files parse cleanly.");
} else {
    foreach ($broken as $rel => $msg) {
        fail($rel);
        echo "           {$msg}\n";
    }
    echo "\n  " . ($checked - count($broken)) . "/{$checked} files parse.\n";
}

if ($skipped !== []) {
    echo "\n";
    warn(count($skipped) . ' file(s) skipped — they need PHP 8.5 to parse and you are on ' . PHP_VERSION . '.');
    foreach ($skipped as $rel) {
        echo "           - {$rel}\n";
    }
    echo "           These are the examples whose whole purpose is to demonstrate 8.5\n";
    echo "           syntax. Nothing is wrong with them. Everything else in the\n";
    echo "           course, including all four reference solutions, runs on 8.4.\n";
}


// ─────────────────────────────────────────────────────────────────────────────
heading('4. Reference solutions — do they pass their own checks?');

/**
 * Every challenge that can be machine-judged ships `challenge/solution/run.php`.
 * Running all of them here is what stops the course from ever shipping a
 * solution that fails its own assertions. It is slow and it is worth it.
 */
$runners = glob($root . '/module-*/lesson-*/challenge/solution/run.php') ?: [];
sort($runners);

if ($runners === []) {
    warn('No reference solutions found yet. Expected while the course is being written.');
} else {
    $solutionFailures = [];
    foreach ($runners as $runner) {
        $label = str_replace(str_replace('\\', '/', $root) . '/', '', str_replace('\\', '/', $runner));
        [$rc, $out] = shell($php . ' ' . escapeshellarg($runner));

        if ($rc === 0 && str_contains($out, 'ACCEPTANCE: all checks passed')) {
            pass($label);
        } else {
            fail($label);
            $solutionFailures[$label] = $out;
        }
    }

    if ($solutionFailures !== []) {
        echo "\n";
        foreach ($solutionFailures as $label => $out) {
            echo "  " . str_repeat('-', 68) . "\n  {$label}\n  " . str_repeat('-', 68) . "\n";
            foreach (explode("\n", $out) as $l) { echo '  ' . $l . "\n"; }
        }
        echo "\n  A reference solution failing its own checks is a COURSE bug.\n";
        echo "  It is not your mistake and you should not try to work around it.\n";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
heading('5. Regression — does the latest code still satisfy every earlier lesson?');

/**
 * Step 4 proves each lesson is internally consistent: its own solution against
 * its own checks. That is not the guarantee this course makes.
 *
 * Classes accumulate, and some lessons change earlier ones — 2.1 gives
 * LabourLine an identity, 2.2 gives CourtesyVehicle one. The student has ONE
 * copy of each class, so what actually matters is whether the NEWEST version
 * still satisfies every EARLIER specification.
 *
 * Step 4 cannot see that: Lesson 1.1's runner loads Lesson 1.1's copy of
 * CourtesyVehicle. regression.php loads the newest copy of everything and runs
 * the old specs against it.
 */
if (!is_file($root . '/regression.php')) {
    warn('regression.php not found — skipping the accumulation check.');
} else {
    [$rcRegression, $regressionOut] = shell($php . ' ' . escapeshellarg($root . '/regression.php'));

    foreach (explode("\n", trim($regressionOut)) as $line) {
        if (preg_match('/^\s{2}Lesson\s+(\S+)\s+(PASS|FAIL)\s*$/', $line, $m) === 1) {
            $m[2] === 'PASS'
                ? pass('Lesson ' . $m[1] . ' still passes against the latest code.')
                : fail('Lesson ' . $m[1] . ' NO LONGER passes against the latest code.');
        }
    }

    if ($rcRegression !== 0) {
        echo "\n";
        foreach (explode("\n", $regressionOut) as $line) {
            echo '  ' . $line . "\n";
        }
        echo "\n";
        echo "  A later lesson has broken an earlier lesson's contract. The fix belongs\n";
        echo "  in the LATER lesson — the earlier specification is what is being\n";
        echo "  protected. Run  php regression.php  for the detail.\n";
    }
}


// ─────────────────────────────────────────────────────────────────────────────
heading('6. Composer dependencies (needed from Module 3 onwards)');

$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    warn('vendor/ not found. Modules 0, 1 and 2 need nothing — they are plain PHP.');
    echo "           Run  composer install  before starting Module 3.\n";
} else {
    pass('vendor/autoload.php present.');
    require_once $autoload;

    foreach ([
        'PHPUnit'    => \PHPUnit\Framework\TestCase::class,
        'PHP-DI'     => \DI\ContainerBuilder::class,
        'Slim'       => \Slim\Factory\AppFactory::class,
        'Slim PSR-7' => \Slim\Psr7\Factory\ServerRequestFactory::class,
    ] as $name => $class) {
        class_exists($class) ? pass("{$name} installed.") : fail("{$name} missing — run: composer install");
    }

    extension_loaded('pdo_sqlite')
        ? pass('pdo_sqlite extension loaded (Module 3 integration tests need it).')
        : warn('pdo_sqlite not loaded. Modules 0-2 are fine; Module 3 will need it.');
}


// ─────────────────────────────────────────────────────────────────────────────
heading('Summary');

if ($failures === 0 && $warnings === 0) {
    echo "  All green. Open README.md and start with Module 0, Lesson 0.1.\n\n";
    exit(0);
}

if ($failures === 0) {
    echo "  {$warnings} warning(s), 0 failures.\n";
    echo "  You can start Module 0 now; resolve the warnings before Module 3.\n\n";
    exit(0);
}

echo "  {$failures} failure(s), {$warnings} warning(s).\n";
echo "  Fix the failures above before going further.\n\n";
exit(1);
