<?php
declare(strict_types=1);

/**
 * run-tests.php — PHPUnit runner, for tests you add
 * ==================================================
 *
 * THE COURSE SHIPS NO PHPUNIT TEST FILES. Run this today and it will correctly
 * report that it found nothing.
 *
 * Every challenge is judged by assertions in `challenge/checks.php`, including
 * Lesson 3.4, which is about testing persistence and does its contract test in
 * the same harness as everything else. One mechanism that runs on every
 * `verify.php` is worth more than two that each cover half the course.
 *
 * This file is kept because the machinery below is not obvious and you will
 * want it the moment you write a test of your own — see the two notes on
 * process isolation and file naming.
 *
 *     php run-tests.php                # every test file in the course
 *     php run-tests.php 3.4            # just Lesson 3.4
 *     php run-tests.php module-3       # just Module 3
 *     php run-tests.php --starter      # include challenge/starter tests too
 *
 * WHY NOT JUST `vendor/bin/phpunit`
 * ----------------------------------
 * Lessons deliberately reuse class names — `JobCard`, `Money`, `InMemoryJobCards`
 * appear in several places, because each lesson should read standalone and
 * because the same class is written more than once as the model improves. PHP
 * cannot hold two classes of the same name in one process, so a single PHPUnit
 * run over the whole course dies with "Cannot declare class X, name already in
 * use". This runner gives every file its own process. Slower, always correct.
 *
 * PHPUnit 11 also resolves a test class from the FILE NAME. That is fine for
 * MoneyTest.php but not for 01-first-contract-test.php, where it hunts for a
 * class called "01-first-contract-test" and reports "No tests executed". The
 * workaround below stages a temporary copy named after the class, runs that, and
 * deletes it — the copy is a sibling of the original so that `__DIR__` still
 * resolves to the lesson folder.
 *
 * Nothing in Modules 0, 1 or 2 needs this. Those challenges are plain scripts
 * judged by their acceptance checks; `php check.php` covers them.
 */

$root    = __DIR__;
$phpunit = $root . '/vendor/bin/phpunit';

$argsIn         = array_slice($argv, 1);
$includeStarter = in_array('--starter', $argsIn, true)
               || in_array('--only-starter', $argsIn, true);
$onlyStarter    = in_array('--only-starter', $argsIn, true);
$filters        = array_values(array_filter($argsIn, static fn(string $a): bool => !str_starts_with($a, '--')));

// ── Collect candidate test files ────────────────────────────────────────────
$testFiles = [];
foreach (glob($root . '/module-*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($moduleDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        /** @var SplFileInfo $f */
        if ($f->getExtension() !== 'php') { continue; }
        $path = str_replace('\\', '/', $f->getPathname());

        $isStarter = str_contains($path, '/challenge/starter/');
        if (!$includeStarter && $isStarter) { continue; }
        if ($onlyStarter && !$isStarter)    { continue; }

        $src = (string) file_get_contents($f->getPathname());
        if (!str_contains($src, 'extends TestCase')) { continue; }

        $testFiles[] = $path;
    }
}
sort($testFiles);

if ($filters !== []) {
    $testFiles = array_values(array_filter($testFiles, static function (string $path) use ($filters): bool {
        foreach ($filters as $needle) {
            if (stripos($path, $needle) !== false) { return true; }
        }
        return false;
    }));
}

if ($testFiles === []) {
    // Not an error while the course is young — Modules 0-2 have no PHPUnit files.
    echo "No PHPUnit test files found" . ($filters !== [] ? ' matching ' . implode(', ', $filters) : '') . ".\n";
    echo "Modules 0, 1 and 2 are judged by their acceptance checks instead. Run: php check.php\n";
    exit(0);
}

if (!is_file($phpunit)) {
    fwrite(STDERR, "PHPUnit not found.\nRun 'composer install' from the course root first.\n");
    exit(1);
}

// ── Run each file in its own process ────────────────────────────────────────
$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php85domain_phpunit';
@mkdir($tmpDir, 0777, true);

echo "Running " . count($testFiles) . " test file(s), one process each.\n";
echo str_repeat('-', 72) . "\n";

$passedFiles  = 0;
$failedFiles  = [];
$skippedFiles = [];
$tempCopies   = [];

// If this run dies partway through, do not leave stray <Class>.php files behind.
register_shutdown_function(static function () use (&$tempCopies): void {
    foreach ($tempCopies as $stray) { @unlink($stray); }
});

/**
 * Concrete TestCase subclasses declared in a file.
 *
 * Uses the tokenizer rather than a regex. Matching "\PHPUnit\Framework\TestCase"
 * inside a PHP string literal needs enough backslash escaping that it is a
 * reliable way to end up with a pattern that silently matches nothing.
 *
 * @return list<string>
 */
function concreteTestClasses(string $path): array {
    $classes = [];
    $tokens  = token_get_all((string) file_get_contents($path));
    $count   = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_CLASS) { continue; }

        $isAbstract = false;
        for ($b = $i - 1; $b >= 0; $b--) {
            if (!is_array($tokens[$b])) { break; }
            if ($tokens[$b][0] === T_ABSTRACT) { $isAbstract = true; break; }
            if (!in_array($tokens[$b][0], [T_WHITESPACE, T_FINAL, T_READONLY, T_COMMENT, T_DOC_COMMENT], true)) { break; }
        }

        $n = $i + 1;
        while ($n < $count && is_array($tokens[$n]) && $tokens[$n][0] === T_WHITESPACE) { $n++; }
        if ($n >= $count || !is_array($tokens[$n]) || $tokens[$n][0] !== T_STRING) { continue; }
        $name = $tokens[$n][1];

        $parent  = '';
        $seenExt = false;
        for ($k = $n + 1; $k < $count; $k++) {
            $tok = $tokens[$k];
            if (!is_array($tok)) { if ($tok === '{') { break; } continue; }
            if ($tok[0] === T_EXTENDS)    { $seenExt = true; continue; }
            if ($tok[0] === T_IMPLEMENTS) { break; }
            if ($seenExt && in_array($tok[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                $parent .= $tok[1];
            }
        }

        if (!$isAbstract && str_ends_with($parent, 'TestCase')) {
            $classes[] = $name;
        }
    }
    return $classes;
}

foreach ($testFiles as $path) {
    $label = str_replace(str_replace('\\', '/', $root) . '/', '', $path);
    printf("%-72s ", strlen($label) > 72 ? '...' . substr($label, -69) : $label);

    $classes = concreteTestClasses($path);
    if ($classes === []) {
        echo "SKIP (no concrete TestCase class)\n";
        $skippedFiles[] = $label;
        continue;
    }

    // PHPUnit wants the class PHYSICALLY DECLARED in the file it is handed — a
    // shim that require()s the real file is reported as "does not extend
    // TestCase" even when it plainly does.
    $basename = pathinfo($path, PATHINFO_FILENAME);
    $targets  = [];
    foreach ($classes as $class) {
        if ($class === $basename) { $targets[$class] = [$path, false]; continue; }

        $sibling = dirname($path) . DIRECTORY_SEPARATOR . $class . '.php';
        if (file_exists($sibling)) { $targets[$class] = [$sibling, false]; continue; }

        if (!@copy($path, $sibling)) {
            echo "FAIL (could not stage {$class})\n";
            $failedFiles[$label] = "Could not create temporary copy at {$sibling}";
            continue 2;
        }
        $tempCopies[]    = $sibling;
        $targets[$class] = [$sibling, true];
    }

    $combined   = '';
    $anyFailure = false;

    foreach ($targets as $class => [$runFile, $isTemp]) {
        $cfg = $tmpDir . DIRECTORY_SEPARATOR . 'phpunit_' . md5($runFile) . '.xml';
        file_put_contents($cfg, sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<phpunit bootstrap="%s" colors="false" cacheResult="false"' . "\n"
            . '         beStrictAboutOutputDuringTests="false"' . "\n"
            . '         failOnWarning="false" failOnNotice="false" failOnDeprecation="false">' . "\n"
            . '  <testsuites><testsuite name="single"><file>%s</file></testsuite></testsuites>' . "\n"
            . '</phpunit>' . "\n",
            htmlspecialchars($root . '/vendor/autoload.php', ENT_XML1),
            htmlspecialchars($runFile, ENT_XML1)
        ));

        $one = []; $rc = 0;
        exec(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phpunit)
            . ' --configuration ' . escapeshellarg($cfg)
            . ' --colors=never --do-not-cache-result 2>&1',
            $one,
            $rc
        );

        @unlink($cfg);
        if ($isTemp) {
            @unlink($runFile);
            $tempCopies = array_values(array_diff($tempCopies, [$runFile]));
        }

        $text = implode("\n", $one);
        // "No tests executed" is a runner problem, not a pass.
        if ($rc !== 0 || str_contains($text, 'No tests executed')) { $anyFailure = true; }
        $combined .= ($combined === '' ? '' : "\n") . "--- {$class} ---\n" . $text;
    }

    if (!$anyFailure) {
        echo "PASS\n";
        $passedFiles++;
    } else {
        echo "FAIL\n";
        $failedFiles[$label] = $combined;
    }
}

echo str_repeat('-', 72) . "\n";
printf(
    "%d passed, %d failed, %d skipped, %d total.\n",
    $passedFiles, count($failedFiles), count($skippedFiles), count($testFiles)
);

if ($skippedFiles !== []) {
    echo "\nSkipped (no concrete TestCase class — check these really are not tests):\n";
    foreach ($skippedFiles as $l) { echo "  - {$l}\n"; }
}

if ($failedFiles !== []) {
    foreach ($failedFiles as $l => $output) {
        echo "\n" . str_repeat('=', 72) . "\n{$l}\n" . str_repeat('=', 72) . "\n";
        echo $output . "\n";
    }
    exit(1);
}

// Nothing failing is only good news if something actually ran.
if ($passedFiles === 0) {
    echo "\nNO TESTS EXECUTED — that is a runner failure, not a pass.\n";
    exit(1);
}

echo "\nAll green.\n";
exit(0);
