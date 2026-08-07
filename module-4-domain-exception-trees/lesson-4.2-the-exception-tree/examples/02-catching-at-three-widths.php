<?php
declare(strict_types=1);

/**
 * Example 02 — One tree, three audiences
 * =======================================
 *
 * Part 1: the same failure through three handlers, each catching at a
 *         different width, each correct for its job.
 * Part 2: clause ORDER, and the silent bug when you get it wrong.
 * Part 3: what a new leaf does to code nobody touched.
 *
 *     php 02-catching-at-three-widths.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

interface WorkshopFailure extends Throwable {}

abstract class WorkshopException extends DomainException implements WorkshopFailure {}
abstract class JobCardException extends WorkshopException
{
    public function __construct(public readonly string $jobCardId, string $message)
    {
        parent::__construct($message);
    }
}

final class JobCardNotFound       extends JobCardException {}
final class JobCardIsTerminal     extends JobCardException {}
final class OutstandingAuthorisations extends JobCardException {}

abstract class InvalidWorkshopInput extends InvalidArgumentException implements WorkshopFailure {}
final class MissingWorkDescription extends InvalidWorkshopInput {}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — Three handlers
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — three widths, three jobs');

/** A use case that can recover from exactly one failure. */
function retryingUseCase(callable $action): string
{
    try {
        $action();

        return 'done';
    } catch (JobCardNotFound $e) {
        return "reloaded {$e->jobCardId} from the archive and retried";
    }
}

/** A controller for the job-card screen. */
function jobCardController(callable $action): string
{
    try {
        $action();

        return '200';
    } catch (JobCardException $e) {
        return "422 on the job-card form for {$e->jobCardId}";
    }
}

/** The API boundary. One clause, for the whole domain. */
function apiBoundary(callable $action): string
{
    try {
        $action();

        return '200';
    } catch (WorkshopFailure $e) {
        return '4xx ' . (new ReflectionClass($e))->getShortName();
    } catch (Throwable $e) {
        return '500 ' . (new ReflectionClass($e))->getShortName();
    }
}

$situations = [
    'JobCardNotFound'          => static fn() => throw new JobCardNotFound('JC-1042', 'not there'),
    'JobCardIsTerminal'        => static fn() => throw new JobCardIsTerminal('JC-1042', 'invoiced'),
    'MissingWorkDescription'   => static fn() => throw new MissingWorkDescription('empty'),
    'PDOException'             => static fn() => throw new PDOException('connection refused'),
];

printf("  %-24s %-42s %s\n", 'thrown', 'retryingUseCase', 'apiBoundary');
printf("  %s\n", str_repeat('-', 72));

foreach ($situations as $label => $action) {
    $retry = '';

    try {
        $retry = retryingUseCase($action);
    } catch (Throwable $e) {
        $retry = 'escaped: ' . (new ReflectionClass($e))->getShortName();
    }

    printf("  %-24s %-42s %s\n", $label, $retry, apiBoundary($action));
}

echo "\n  retryingUseCase lets three of the four ESCAPE, and that is correct.\n";
echo "  It can recover from one thing. Catching more would mean pretending to\n";
echo "  handle failures it has no answer for — which is how a 500 becomes a\n";
echo "  silent 200 with no work done.\n\n";
echo "  apiBoundary handles all four, in two clauses, and the PDOException is\n";
echo "  the only 500.\n";

section('Part 1b — the middle width');

foreach (['JobCardNotFound', 'JobCardIsTerminal', 'MissingWorkDescription'] as $label) {
    $outcome = '';

    try {
        $outcome = jobCardController($situations[$label]);
    } catch (Throwable $e) {
        $outcome = 'escaped: ' . (new ReflectionClass($e))->getShortName();
    }

    printf("  %-24s -> %s\n", $label, $outcome);
}

echo "\n  MissingWorkDescription escapes the job-card controller, because it is\n";
echo "  on the OTHER branch. Whether that is right depends on the controller —\n";
echo "  but it is visible, which is the point. In shape B from example 01 it\n";
echo "  would have been invisible.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — Clause order
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — PHP takes the FIRST matching clause');

function narrowFirst(callable $action): string
{
    try {
        $action();

        return 'ok';
    } catch (JobCardNotFound) {
        return 'specific handler';
    } catch (JobCardException) {
        return 'general handler';
    }
}

function broadFirst(callable $action): string
{
    try {
        $action();

        return 'ok';
    } catch (JobCardException) {
        return 'general handler';
    } catch (JobCardNotFound) {
        return 'specific handler — UNREACHABLE';
    }
}

foreach (['JobCardNotFound', 'JobCardIsTerminal'] as $label) {
    printf(
        "  %-20s narrowFirst: %-20s broadFirst: %s\n",
        $label,
        narrowFirst($situations[$label]),
        broadFirst($situations[$label])
    );
}

echo "\n  broadFirst's second clause can never run. PHP does not warn, PHPStan\n";
echo "  at default levels does not warn, and the tests pass — because the\n";
echo "  general handler does something reasonable.\n\n";
echo "  The symptom in production is not an error. It is a specific recovery\n";
echo "  path that silently stopped happening.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — What a new leaf does
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — adding a leaf, six months later');

final class JobCardLockedByAnotherUser extends JobCardException {}

$newLeaf = static fn() => throw new JobCardLockedByAnotherUser('JC-1042', 'Priya has it open');

printf("  apiBoundary        -> %s\n", apiBoundary($newLeaf));
printf("  jobCardController  -> %s\n", jobCardController($newLeaf));

$escaped = 'no';

try {
    retryingUseCase($newLeaf);
} catch (Throwable $e) {
    $escaped = 'yes — ' . (new ReflectionClass($e))->getShortName();
}

printf("  retryingUseCase    -> escaped: %s\n", $escaped);

echo "\n  Nobody edited any of those three handlers. Two of them handle the new\n";
echo "  failure correctly and the third correctly refuses to.\n\n";
echo "  THAT is what the intermediate node bought. In a flat hierarchy,\n";
echo "  jobCardController would have needed a new clause — and the way you\n";
echo "  find out it did not get one is a customer report.\n\n";
