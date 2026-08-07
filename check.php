<?php
declare(strict_types=1);

/**
 * check.php — where am I, and does everything still work?
 * ========================================================
 *
 *     php check.php                 environment, integrity, progress
 *     php check.php --all           do not stop at the first unsolved challenge
 *     php check.php --skip-verify   skip the course-wide integrity sweep (faster)
 *     php check.php --no-install    never run composer install automatically
 *
 * Three things in order, stopping at the first that is genuinely wrong:
 *
 *   1. ENVIRONMENT — is PHP 8.5 active? Are dependencies present (from Module 3)?
 *
 *   2. INTEGRITY — hands off to verify.php: syntax-checks every file, probes
 *      every language feature the lessons use, and runs every reference solution
 *      against its own checks. This answers "is the COURSE sound", not "am I".
 *
 *   3. PROGRESS — walks the lessons in course order and stops at the first one
 *      that is not yet solved, so you get one clear next action rather than a
 *      wall of failures.
 *
 *
 * HOW A CHALLENGE IS JUDGED
 * --------------------------
 * Not by comparing printed text. Every code challenge ships a fixed list of
 * assertions in `challenge/checks.php`, which prints one line per assertion and
 * ends with a sentinel. This script looks for that sentinel:
 *
 *     ACCEPTANCE: all checks passed
 *
 * Your own `echo` statements cannot fail you and cannot fool the checker.
 *
 * Some lessons have nothing a script can judge — Module 0 is entirely about
 * judgement, and its challenge is a written argument. Those carry an attestation
 * checkbox at the foot of CHALLENGE.md that you tick yourself. A script cannot
 * mark an essay and this one does not pretend to.
 *
 * Before a failing challenge is blamed on you, the REFERENCE SOLUTION is run
 * against the same checks. If that fails too, it is a course bug: you are told
 * so plainly, it is not counted against you, and it does not block you.
 */

$root = __DIR__;
$args = array_slice($argv, 1);

$noInstall  = in_array('--no-install',  $args, true);
$showAll    = in_array('--all',         $args, true);
$skipVerify = in_array('--skip-verify', $args, true);

$php = escapeshellarg(PHP_BINARY);

// ─────────────────────────────────────────────────────────────────────────────
// Presentation
// ─────────────────────────────────────────────────────────────────────────────

const MARK_DONE   = '[ done ]';
const MARK_HERE   = '[ HERE ]';
const MARK_TODO   = '[ todo ]';
const MARK_LOCKED = '[      ]';
const MARK_OK     = '[  ok  ]';
const MARK_FAIL   = '[ FAIL ]';
const MARK_WARN   = '[ warn ]';

function heading(string $text): void {
    echo "\n" . str_repeat('=', 72) . "\n  {$text}\n" . str_repeat('=', 72) . "\n\n";
}

function line(string $mark, string $text): void {
    echo "  {$mark}  {$text}\n";
}

function para(string $text, string $indent = '           '): void {
    foreach (explode("\n", wordwrap($text, 72 - strlen($indent))) as $l) {
        echo $indent . $l . "\n";
    }
}

/** Strip the course root from a path, tolerating mixed \ and / separators. */
function relPath(string $path, string $root): string {
    $p = str_replace('\\', '/', $path);
    $r = rtrim(str_replace('\\', '/', $root), '/') . '/';
    return str_starts_with($p, $r) ? substr($p, strlen($r)) : $p;
}

/** Turn "when-and-when-not" into "When and When Not". */
function prettyTitle(string $slug): string {
    $acronyms = [
        'ddd' => 'DDD', 'api' => 'API', 'di' => 'DI', 'oop' => 'OOP',
        'php' => 'PHP', 'sql' => 'SQL', 'crud' => 'CRUD', 'cqrs' => 'CQRS',
        'orm' => 'ORM', 'http' => 'HTTP', 'rfc' => 'RFC', 'vo' => 'VO',
    ];
    $small = ['vs', 'and', 'not', 'of', 'the', 'with', 'over', 'to', 'in', 'a',
              'why', 'when', 'where', 'without', 'for'];

    $out = [];
    foreach (explode('-', $slug) as $i => $w) {
        $lower = strtolower($w);
        if (isset($acronyms[$lower]))                       { $out[] = $acronyms[$lower]; }
        elseif ($i > 0 && in_array($lower, $small, true))   { $out[] = $lower; }
        else                                                { $out[] = ucfirst($lower); }
    }
    return implode(' ', $out);
}

/** Run a command, return [exitCode, combinedOutput]. */
function shell(string $cmd): array {
    $out = []; $rc = 0;
    exec($cmd . ' 2>&1', $out, $rc);
    return [$rc, implode("\n", $out)];
}

/**
 * Find an attestation checkbox for this lesson in the given markdown.
 *
 * @return ?bool null = no box present, true = ticked, false = present but not ticked
 */
function attestation(string $markdown, string $lessonId): ?bool {
    $pattern = '/^\s*-\s*\[([ xX])\]\s*\*\*Lesson\s+' . preg_quote($lessonId, '/') . '\s+complete\.\*\*/mi';
    if (!preg_match($pattern, $markdown, $m)) {
        return null;
    }
    return strtolower(trim($m[1])) === 'x';
}


// ─────────────────────────────────────────────────────────────────────────────
// 1. ENVIRONMENT
// ─────────────────────────────────────────────────────────────────────────────

heading('1. Environment');

echo "  PHP " . PHP_VERSION . "  (" . PHP_BINARY . ")\n\n";

if (PHP_VERSION_ID < 80400) {
    line(MARK_FAIL, 'PHP 8.4 or newer is required.');
    para('Property hooks and asymmetric visibility both need 8.4, and the course '
       . 'uses them from Lesson 1.1 onwards. Nothing below that will load.');
    para('');
    para('Install PHP 8.5 from Herd\'s PHP tab and set it as the global version. '
       . 'Check the GLOBAL override as well as the per-site one — a global '
       . 'override silently wins over the project setting.');
    echo "\n  Stopping here. Nothing else can be checked meaningfully.\n\n";
    exit(1);
}

if (PHP_VERSION_ID < 80500) {
    line(MARK_WARN, 'PHP ' . PHP_VERSION . ' — the course targets 8.5.');
    para('Everything in Modules 0 and 1 works on 8.4 except three example files '
       . 'whose whole purpose is to demonstrate 8.5 syntax. Every challenge and '
       . 'every reference solution runs here. You are not blocked.');
    para('');
    para('One thing to know: #[\\NoDiscard] parses on 8.4 but does nothing, so '
       . 'the discarded-wither warning in Lesson 1.2 will not fire. Read that '
       . 'section rather than running it.');
} else {
    line(MARK_OK, 'PHP ' . PHP_VERSION . ' — every lesson will run.');
}

// Composer is not needed until Module 3. Do not make it a wall on day one.
$autoload   = $root . '/vendor/autoload.php';
$needsVendor = is_dir($root . '/module-3-persistence-without-leakage')
    && glob($root . '/module-3-persistence-without-leakage/lesson-*') !== [];

if (is_file($autoload)) {
    line(MARK_OK, 'Composer dependencies installed.');
    require_once $autoload;
} elseif (!$needsVendor) {
    line(MARK_OK, 'No dependencies needed yet — Modules 0-2 are plain PHP.');
} else {
    [$rcComposer] = shell('composer --version');
    if ($noInstall || $rcComposer !== 0) {
        line(MARK_WARN, 'Dependencies not installed. Needed from Module 3 onwards.');
        para('Run: composer install');
    } else {
        line(MARK_WARN, 'Dependencies missing — installing them now.');
        echo "\n";
        passthru('composer install --no-interaction --working-dir=' . escapeshellarg($root), $rcInstall);
        echo "\n";
        is_file($autoload)
            ? line(MARK_OK, 'Dependencies installed.')
            : line(MARK_FAIL, 'composer install did not complete — read its output above.');
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// 2. COURSE INTEGRITY
// ─────────────────────────────────────────────────────────────────────────────

heading('2. Course integrity');

if ($skipVerify) {
    line(MARK_WARN, 'Skipped (--skip-verify).');
} else {
    echo "  Syntax-checking every file, probing language features, and running\n";
    echo "  every reference solution against its own checks...\n\n";

    [$rcVerify, $verifyOut] = shell($php . ' ' . escapeshellarg($root . '/verify.php'));

    if ($rcVerify === 0) {
        line(MARK_OK, 'Course files are intact and every reference solution passes.');
    } else {
        line(MARK_FAIL, 'verify.php reported problems with the course itself.');
        echo "\n" . $verifyOut . "\n";
        para('This is a problem with the course, not with your work. Your challenges '
           . 'cannot be judged fairly against a broken course, so fix these first.', '  ');
        echo "\n";
        exit(1);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// 3. PROGRESS
// ─────────────────────────────────────────────────────────────────────────────

heading('3. Your progress');

/**
 * Build the ordered lesson list from the filesystem, so this never drifts out of
 * sync with the course as modules are added.
 *
 * @return list<array{sort:int,id:string,dir:string,title:string,challenge:?string}>
 */
function discoverLessons(string $root): array {
    $lessons = [];
    foreach (glob($root . '/module-*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
        foreach (glob($moduleDir . '/lesson-*', GLOB_ONLYDIR) ?: [] as $lessonDir) {
            $slug = basename($lessonDir);
            if (!preg_match('/^lesson-(\d+)\.(\d+)-(.*)$/', $slug, $m)) {
                continue;
            }
            $lessons[] = [
                'sort'      => ((int) $m[1]) * 1000 + (int) $m[2],
                'id'        => $m[1] . '.' . $m[2],
                'dir'       => $lessonDir,
                'title'     => prettyTitle($m[3]),
                'challenge' => is_dir($lessonDir . '/challenge') ? $lessonDir . '/challenge' : null,
            ];
        }
    }
    usort($lessons, static fn(array $a, array $b): int => $a['sort'] <=> $b['sort']);
    return $lessons;
}

/**
 * Judge one lesson.
 *
 * @return array{state:string,detail:string,hint:string}
 *         state: done | todo | course-bug | none
 */
function judge(array $lesson, string $root, string $php): array {
    $dir = $lesson['challenge'];
    $id  = $lesson['id'];

    // ── Attestation lessons ────────────────────────────────────────────────
    // Module 0's challenges are written arguments. A script cannot mark those,
    // so they carry a checkbox instead. Reading lessons with no challenge folder
    // carry the same box in their README.
    $attestationFiles = [];
    if ($dir !== null) {
        $attestationFiles[] = $dir . '/CHALLENGE.md';
    }
    $attestationFiles[] = $lesson['dir'] . '/README.md';

    foreach ($attestationFiles as $md) {
        if (!is_file($md)) { continue; }

        $ticked = attestation((string) file_get_contents($md), $id);
        if ($ticked === null) { continue; }

        if ($ticked) {
            return ['state' => 'done', 'detail' => 'Marked complete in ' . basename($md) . '.', 'hint' => ''];
        }
        return [
            'state'  => 'todo',
            'detail' => 'Not yet marked complete.',
            'hint'   => 'This one cannot be judged automatically. Do the work described in '
                      . relPath($md, $root) . ', then tick the "Lesson ' . $id
                      . ' complete." box at the bottom of that file. Tick it honestly — '
                      . 'the only person the box reports to is you.',
        ];
    }

    if ($dir === null) {
        return ['state' => 'none', 'detail' => '', 'hint' => ''];
    }

    // ── Code challenges: run the starter, look for the sentinel ────────────
    $starter = $dir . '/starter.php';
    if (!is_file($starter)) {
        return ['state' => 'none', 'detail' => '', 'hint' => ''];
    }

    [$rc, $out] = shell($php . ' ' . escapeshellarg($starter));
    $rel = relPath($starter, $root);

    if ($rc === 0 && str_contains($out, 'ACCEPTANCE: all checks passed')) {
        return ['state' => 'done', 'detail' => 'All acceptance checks pass.', 'hint' => ''];
    }

    // Before blaming you: does the REFERENCE SOLUTION pass the same checks?
    $solution = $dir . '/solution/run.php';
    if (is_file($solution)) {
        [$srcRc, $solOut] = shell($php . ' ' . escapeshellarg($solution));
        if ($srcRc !== 0 || !str_contains($solOut, 'ACCEPTANCE: all checks passed')) {
            $why = '';
            foreach (explode("\n", $solOut) as $l) {
                if (str_contains($l, '[ FAIL ]') || preg_match('/(Fatal error|Parse error|Uncaught)/i', $l)) {
                    $why = trim($l);
                    break;
                }
            }
            return [
                'state'  => 'course-bug',
                'detail' => "The reference solution fails its own checks.\n"
                          . ($why !== '' ? "First failure: {$why}" : ''),
                'hint'   => 'This is a course bug, not your mistake. It is not counted and '
                          . 'it is not blocking you. Compare your work against '
                          . relPath($dir . '/solution', $root) . ' by eye and move on.',
            ];
        }
    }

    // Genuinely not done yet. Say the most useful thing available.
    $detail = 'The acceptance checks are not all passing yet.';

    foreach (explode("\n", $out) as $l) {
        if (preg_match('/(Fatal error|Parse error|Uncaught)/i', $l)) {
            $detail = trim($l);
            break;
        }
    }
    if ($detail === 'The acceptance checks are not all passing yet.') {
        $fails = [];
        foreach (explode("\n", $out) as $l) {
            if (str_contains($l, '[ FAIL ]')) {
                $fails[] = trim(str_replace('[ FAIL ]', '', $l));
            }
        }
        if ($fails !== []) {
            $shown  = array_slice($fails, 0, 3);
            $detail = count($fails) . ' check(s) failing. First: ' . $shown[0];
            foreach (array_slice($shown, 1) as $f) { $detail .= "\nAlso: " . $f; }
        }
    }

    return [
        'state'  => 'todo',
        'detail' => $detail,
        'hint'   => 'Read ' . relPath($dir . '/CHALLENGE.md', $root) . ", then run:\n"
                  . '  php ' . $rel,
    ];
}

// ── Walk the lessons ────────────────────────────────────────────────────────

$lessons   = discoverLessons($root);
$totalWork = count($lessons);

if ($totalWork === 0) {
    line(MARK_WARN, 'No lessons found. The course is still being built.');
    echo "\n";
    exit(0);
}

$completed  = 0;
$courseBugs = [];
$stopped    = null;
$reached    = false;
$lastModule = null;

foreach ($lessons as $lesson) {
    $moduleNum = (int) explode('.', $lesson['id'])[0];
    if ($moduleNum !== $lastModule) {
        if ($lastModule !== null) { echo "\n"; }
        echo "  Module {$moduleNum}\n";
        $lastModule = $moduleNum;
    }

    $label = sprintf('Lesson %-4s %s', $lesson['id'], $lesson['title']);

    if ($reached && !$showAll) {
        line(MARK_LOCKED, $label);
        continue;
    }

    $result = judge($lesson, $root, $php);

    if ($result['state'] === 'none') {
        line('[ read ]', $label . '  (nothing to check)');
        continue;
    }
    if ($result['state'] === 'done') {
        $completed++;
        line(MARK_DONE, $label);
        continue;
    }
    if ($result['state'] === 'course-bug') {
        // Deliberately NOT counted as complete — you may not have done it. But it
        // cannot be judged fairly either, so it must not block you.
        $courseBugs[$lesson['id']] = $result['detail'];
        line(MARK_WARN, $label . '  (cannot be judged — course bug, not blocking)');
        continue;
    }

    line($reached ? MARK_TODO : MARK_HERE, $label);
    if (!$reached) {
        $stopped = ['lesson' => $lesson, 'result' => $result];
        $reached = true;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Report
// ─────────────────────────────────────────────────────────────────────────────

heading('Where you are');

$pct  = (int) round($completed / $totalWork * 100);
$fill = (int) round($pct / 4);
$bar  = str_repeat('#', $fill) . str_repeat('.', 25 - $fill);

echo "  [{$bar}] {$pct}%\n";
echo "  {$completed} of {$totalWork} lessons complete.\n";

if ($courseBugs !== []) {
    echo "\n";
    line(MARK_WARN, 'Could not judge ' . count($courseBugs) . ' lesson(s): ' . implode(', ', array_keys($courseBugs)));
    para('The reference solution for these fails its own checks, so neither passing '
       . 'nor failing you would mean anything. Skipped, not counted, not blocking.');
    foreach ($courseBugs as $lid => $why) {
        echo "\n    Lesson {$lid}\n";
        foreach (explode("\n", $why) as $l) { echo '      ' . $l . "\n"; }
    }
}

if ($stopped === null) {
    echo "\n";
    line(MARK_OK, 'Every lesson is complete. That is the whole course.');
    para('Re-read COURSE_PHILOSOPHY.md. The rules should read as obvious now in a '
       . 'way they did not on day one.');
    echo "\n";
    exit(0);
}

$lesson = $stopped['lesson'];
$result = $stopped['result'];
$rel    = relPath($lesson['dir'], $root);

echo "\n";
echo "  You are here:  Lesson {$lesson['id']} — {$lesson['title']}\n";
echo "  " . str_repeat('-', 68) . "\n\n";
echo "  What is failing:\n";
para($result['detail'], '    ');

if ($result['hint'] !== '') {
    echo "\n  What to do:\n";
    para($result['hint'], '    ');
}

echo "\n  Where to look:\n";
echo "    Lesson       {$rel}/README.md\n";
if (is_file($lesson['dir'] . '/challenge/CHALLENGE.md')) {
    echo "    Challenge    {$rel}/challenge/CHALLENGE.md\n";
}
if (is_file($lesson['dir'] . '/challenge/checks.php')) {
    echo "    The spec     {$rel}/challenge/checks.php   <- read this, it is the brief stated precisely\n";
}
if (is_file($lesson['dir'] . '/quiz/QUIZ.md')) {
    echo "    Quiz         {$rel}/quiz/QUIZ.md\n";
}

echo "\n  Everything before this is done. Solve this one and re-run:\n";
echo "    php check.php\n\n";

exit(1);
