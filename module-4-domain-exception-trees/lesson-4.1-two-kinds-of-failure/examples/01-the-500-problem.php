<?php
declare(strict_types=1);

/**
 * Example 01 — Why an application returns 500 for user error
 * ===========================================================
 *
 * Part 1: the boundary everyone writes first, and the status codes it produces.
 * Part 2: the same three failures, classified by hand.
 * Part 3: what changes when the boundary can tell them apart.
 * Part 4: the one that escapes on purpose, and why that is correct.
 *
 *     php 01-the-500-problem.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// Three failures. One of each category.
// ═══════════════════════════════════════════════════════════════════════════

/** (a) Expected. Marcus would recognise this. */
function invoiceAnIncompleteJobCard(): never
{
    throw new DomainException('Job card JC-1042 is In Progress and cannot be invoiced.');
}

/** (b) Exceptional. Nobody's rule was broken; the machinery fell over. */
function theDatabaseIsDown(): never
{
    throw new RuntimeException('SQLSTATE[HY000] [2002] Connection refused');
}

/**
 * (c) Programmer error. This is a bug in the file.
 *
 * A REAL TypeError, produced the way you would produce one by accident, rather
 * than a `new TypeError(...)` written to look like one. The difference matters:
 * `\Error` and its subclasses are what the ENGINE throws, and the whole point
 * of part 4 is how they travel.
 */
function aBugInTheMapper(): never
{
    $lines = null;

    // @phpstan-ignore-next-line  — deliberate: null has no methods.
    $lines->total();

    throw new LogicException('unreachable');
}

/** @return array<string, callable(): never> */
function theThreeFailures(): array
{
    return [
        'Invoice an incomplete job card'  => invoiceAnIncompleteJobCard(...),
        'Database refuses the connection' => theDatabaseIsDown(...),
        'Mapper calls a method on null'   => aBugInTheMapper(...),
    ];
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — The boundary everyone writes first
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — catch (Throwable) at the edge');

function handleV1(callable $action): string
{
    try {
        $action();
    } catch (\Throwable $e) {
        return '500 Internal Server Error';
    }

    return '200 OK';
}

foreach (theThreeFailures() as $label => $action) {
    printf("  %-34s -> %s\n", $label, handleV1($action));
}

echo "\n  Three different situations. One answer.\n";
echo "  The first is the system working correctly, and it is indistinguishable\n";
echo "  from a dead database. The third is a bug, now buried in a tidy 500.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — Classifying by hand
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — the two secondary tests');

$classification = [
    'Invoice an incomplete job card' => [
        'retry unchanged?'           => 'never succeeds',
        'valid alternative request?' => 'yes — complete the job first',
        'category'                   => '(a) expected      -> 409',
    ],
    'Database refuses the connection' => [
        'retry unchanged?'           => 'might succeed',
        'valid alternative request?' => 'no',
        'category'                   => '(b) exceptional   -> 500',
    ],
    'Mapper calls a method on null' => [
        'retry unchanged?'           => 'never succeeds',
        'valid alternative request?' => 'no — nothing the caller sends helps',
        'category'                   => '(c) programmer    -> fix the file',
    ],
];

foreach ($classification as $label => $answers) {
    echo "  {$label}\n";
    foreach ($answers as $question => $answer) {
        printf("      %-26s %s\n", $question, $answer);
    }
    echo "\n";
}

echo "  The third line of each block is DERIVED from the two above it.\n\n";
echo "    never succeeds + a valid alternative exists  -> (a) the caller\n";
echo "    might succeed                                -> (b) the machinery\n";
echo "    never succeeds + no alternative at all       -> (c) the code\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — A boundary that catches on type
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — catching on type');

function handleV2(callable $action): string
{
    try {
        $action();
    } catch (\DomainException $e) {
        return '409 Conflict — ' . $e->getMessage();
    } catch (\Exception $e) {
        return '500 Internal Server Error (logged: ' . $e->getMessage() . ')';
    }

    return '200 OK';
}

foreach (['Invoice an incomplete job card', 'Database refuses the connection'] as $label) {
    printf("  %-34s -> %s\n", $label, handleV2(theThreeFailures()[$label]));
}

echo "\n  Two situations, two answers, and the first one is now a sentence the\n";
echo "  service manager could have written.\n\n";
echo "  The third failure is deliberately absent from that loop. Part 4.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 4 — The one that escapes, on purpose
// ═══════════════════════════════════════════════════════════════════════════
section('Part 4 — \\Error is not an \\Exception');

echo "  What happens if the bug goes through handleV2?\n\n";

// The outer try exists ONLY so this file can finish and show you the result.
// In a real boundary there would be no outer try, and that is the correct
// design — see the note at the bottom.
try {
    echo '  ' . handleV2(theThreeFailures()['Mapper calls a method on null']) . "\n";
    echo "  ^ if you can read a status code above, something is very wrong.\n";
} catch (\Throwable $escaped) {
    printf("  It escaped both catch clauses.\n\n");
    printf("      class                  %s\n", $escaped::class);
    printf("      instanceof \\Exception  %s\n", $escaped instanceof \Exception ? 'yes' : 'NO');
    printf("      instanceof \\Error      %s\n", $escaped instanceof \Error ? 'yes' : 'NO');
    printf("      instanceof \\Throwable  %s\n", $escaped instanceof \Throwable ? 'yes' : 'NO');
    printf("      message                %s\n", $escaped->getMessage());
    printf("      thrown at              %s:%d\n", basename((string) $escaped->getFile()), $escaped->getLine());
}

echo "\n";
echo "  \\Error and \\Exception are SIBLINGS under \\Throwable. `catch (\\Exception)`\n";
echo "  therefore does not catch a TypeError, an ArgumentCountError, or a call\n";
echo "  to a method on null.\n\n";
echo "  THAT IS THE CORRECT BEHAVIOUR, and it is worth sitting with, because\n";
echo "  the instinct is to widen the clause to \\Throwable to 'be safe'.\n\n";
echo "  Widening it would turn every bug in the codebase into a 500 page and a\n";
echo "  log line. The stack trace above names the file and the line. Buried in\n";
echo "  a generic handler, that becomes 'users occasionally see an error',\n";
echo "  which is the same information with the useful part removed.\n\n";
echo "  Category (c) should be loud. Let it be loud.\n";


// ═══════════════════════════════════════════════════════════════════════════
// The point
// ═══════════════════════════════════════════════════════════════════════════
section('The point');

echo "  A boundary is only as precise as the TYPES that reach it.\n\n";
echo "  handleV1 could not have done better: everything it saw was a\n";
echo "  \\Throwable and nothing more. handleV2 does better not because it is\n";
echo "  cleverer, but because DomainException and RuntimeException are\n";
echo "  different types and somebody chose which to throw.\n\n";
echo "  So the design work is at the THROW site, not the catch site. That is\n";
echo "  the rest of this module.\n\n";
echo "  One thing handleV2 still cannot do: catch every failure THIS domain\n";
echo "  produces and no others. `\\DomainException` is not enough, because a\n";
echo "  rejected Money amount is an \\InvalidArgumentException — a sibling, not\n";
echo "  a descendant. Example 02.\n\n";
