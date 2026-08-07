<?php
declare(strict_types=1);

/**
 * lib/acceptance.php — how a challenge decides whether you have finished
 * =======================================================================
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * The previous course judged a challenge by comparing the text your script
 * printed against an "Expected Output" block written by hand in CHALLENGE.md.
 * That fails in two directions at once. Reformat a line and a correct solution
 * is marked wrong; write the block from imagination and a wrong solution is
 * marked right. Several of them were written from imagination.
 *
 * So this course judges differently. Every challenge ships a fixed list of
 * assertions in `challenge/checks.php`. You do not edit that file. It runs
 * against your code, prints one line per assertion, and finishes with a single
 * machine-readable sentinel:
 *
 *     ACCEPTANCE: all checks passed
 *
 * `check.php` looks for that sentinel and nothing else. Your `echo` statements,
 * your formatting, your debugging output — none of it can fail you, and none of
 * it can fool the checker either.
 *
 * READ THE CHECKS BEFORE YOU START
 * ---------------------------------
 * `checks.php` is not hidden from you and it is not meant to be. It is the
 * specification, stated precisely. Read it first. Working out what a system must
 * do from its assertions is a skill in itself, and it is the same skill you use
 * when you inherit a codebase with a test suite and no documentation.
 *
 * WHAT IT LOOKS LIKE IN USE
 * -------------------------
 *     require_once __DIR__ . '/../../../lib/acceptance.php';
 *
 *     acceptance('Lesson 1.2 — Money');
 *
 *     check('Money rejects a negative amount',
 *         fn() => throws(InvalidArgumentException::class, fn() => Money::of(-1, 'EUR')));
 *
 *     check('Two Money objects with equal parts are equal',
 *         fn() => Money::of(500, 'EUR')->equals(Money::of(500, 'EUR')));
 *
 *     acceptance_report();
 */

if (!function_exists('acceptance')) {

    /** @var list<array{label:string,passed:bool,note:string}> */
    $GLOBALS['__acceptance_results'] = [];
    $GLOBALS['__acceptance_title']   = 'Acceptance';

    /**
     * Open an acceptance run. Call once, at the top.
     */
    function acceptance(string $title): void
    {
        $GLOBALS['__acceptance_title']   = $title;
        $GLOBALS['__acceptance_results'] = [];

        echo "\n" . str_repeat('=', 72) . "\n";
        echo "  {$title}\n";
        echo str_repeat('=', 72) . "\n\n";
    }

    /**
     * Assert one thing.
     *
     * `$test` may be a bool or a callable returning bool. A callable is
     * preferred: it is evaluated inside a try/catch, so an assertion whose
     * subject class does not exist yet reports "Class not found" against that
     * one line instead of killing the whole run with a fatal error. Early in a
     * challenge that is the difference between one useful failure and no output
     * at all.
     */
    function check(string $label, mixed $test): bool
    {
        // Deliberately `mixed` rather than `callable|bool`. PHP does not permit
        // `callable` inside a union type — its meaning is context-dependent —
        // and writing one is a fatal error at compile time, which would take
        // the whole harness down rather than failing one check.
        $note   = '';
        $passed = false;

        try {
            $passed = is_callable($test) ? (bool) $test() : (bool) $test;
        } catch (\Throwable $e) {
            $passed = false;
            $note   = $e::class . ': ' . $e->getMessage();
        }

        $GLOBALS['__acceptance_results'][] = [
            'label'  => $label,
            'passed' => $passed,
            'note'   => $note,
        ];

        printf("  %s  %s\n", $passed ? '[ PASS ]' : '[ FAIL ]', $label);
        if (!$passed && $note !== '') {
            echo "            └─ {$note}\n";
        }

        return $passed;
    }

    /**
     * True when `$fn` throws `$expected` (or a subclass of it).
     *
     * The single most common assertion in this course. A domain model is judged
     * far more by what it refuses than by what it accepts, so most challenges
     * assert refusal more often than they assert success.
     *
     * Deliberately strict about the exception TYPE. A model that rejects a
     * negative price by throwing `\Error` because you divided by zero has not
     * enforced an invariant; it has crashed at the right moment by luck.
     */
    function throws(string $expected, callable $fn): bool
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            if ($e instanceof $expected) {
                return true;
            }

            // The WRONG exception type. Rather than returning false and leaving
            // you with a bare FAIL, throw something descriptive: check() catches
            // it and prints it, so you get "expected DomainException, got
            // TypeError: ..." instead of having to guess.
            throw new \RuntimeException(
                sprintf(
                    'expected %s, got %s — %s',
                    $expected,
                    $e::class,
                    $e->getMessage()
                ),
                0,
                $e
            );
        }

        return false;
    }

    /**
     * True when `$fn` completes without throwing anything at all.
     *
     * The mirror of `throws()`, and worth asserting explicitly. A constructor
     * that rejects every input passes all your refusal tests.
     */
    function succeeds(callable $fn): bool
    {
        try {
            $fn();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * True when the given class exists AND is in the file you were asked to put
     * it in. Used where the challenge is partly about placement — Module 3 cares
     * a great deal about which layer a class lives in, and a class in the wrong
     * directory is the failure being tested for.
     *
     * ONE EXCEPTION, and it is the reason this is a function rather than a
     * one-liner: a class loaded from a `challenge/solution/` directory always
     * passes. The reference implementation is judged by the same checks file as
     * your work, and it cannot also be sitting in `domain/workshop/` — that is
     * where YOUR version goes. Without this exception every placement check
     * would fail for the reference implementation, `verify.php` would report a
     * course bug on every run, and the one signal that matters would be noise.
     */
    function declared_in(string $class, string $pathFragment): bool
    {
        if (!class_exists($class) && !interface_exists($class) && !enum_exists($class)) {
            return false;
        }

        $file = (new \ReflectionClass($class))->getFileName();
        if ($file === false) {
            return false;
        }

        $normalised = str_replace('\\', '/', $file);

        if (str_contains($normalised, '/challenge/solution/')) {
            return true;
        }

        return str_contains($normalised, str_replace('\\', '/', $pathFragment));
    }

    /**
     * True when the given class's source contains no float arithmetic.
     *
     * Money must never touch a float — 0.1 + 0.2 is not 0.3, and on a ledger
     * that becomes an amount somebody reconciles by hand every month.
     *
     * Comments and doc blocks are stripped before the check, deliberately: it
     * is float ARITHMETIC that is banned, not the word. A class that explains
     * in a doc block why it avoids floats should not fail for saying so.
     */
    function no_float_in(string $class): bool
    {
        if (!class_exists($class) && !enum_exists($class)) {
            return false;
        }

        $file = (new \ReflectionClass($class))->getFileName();
        if ($file === false) {
            return false;
        }

        $stripped = '';
        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $stripped .= is_array($token) ? $token[1] : $token;
        }

        return preg_match('/\bfloat\b/i', $stripped) !== 1
            && preg_match('/\d+\.\d+/', $stripped) !== 1;
    }

    /**
     * Print the summary, emit the sentinel, and exit.
     *
     * Exit code is 0 on a clean run and 1 otherwise, so this works from a shell
     * as well as from `check.php`.
     */
    function acceptance_report(): never
    {
        /** @var list<array{label:string,passed:bool,note:string}> $results */
        $results = $GLOBALS['__acceptance_results'];

        $total  = count($results);
        $failed = count(array_filter($results, static fn(array $r): bool => !$r['passed']));

        echo "\n" . str_repeat('-', 72) . "\n";

        if ($total === 0) {
            echo "  No checks ran. That is a broken challenge file, not a pass.\n";
            echo "ACCEPTANCE: no checks ran\n\n";
            exit(1);
        }

        if ($failed === 0) {
            echo "  {$total} of {$total} checks passed.\n";
            echo "ACCEPTANCE: all checks passed\n\n";
            exit(0);
        }

        $passed = $total - $failed;
        echo "  {$passed} of {$total} checks passed.\n\n";
        echo "  Still failing:\n";
        foreach ($results as $r) {
            if (!$r['passed']) {
                echo "    - {$r['label']}\n";
                if ($r['note'] !== '') {
                    echo "      {$r['note']}\n";
                }
            }
        }
        echo "\nACCEPTANCE: {$failed} of {$total} checks failed\n\n";
        exit(1);
    }
}
