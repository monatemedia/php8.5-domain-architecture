<?php
declare(strict_types=1);

/**
 * Example 03 — Rung 5, costed for two applications
 * =================================================
 *
 * One pays for a separate domain layer. One does not. The difference is not
 * how big they are, and it is not how much money they handle.
 *
 *     php 03-theatre-or-worth-it.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

/**
 * Module 0's signals, plus the two that only appear inside a legacy codebase.
 *
 * @return array{score:int, hits:list<string>}
 */
function score(array $answers): array
{
    $signals = [
        'invariants spanning entities'        => 2,
        'a lifecycle with illegal transitions'=> 2,
        'rules the business argues about'     => 2,
        'calculations with legal consequences'=> 2,
        'domain and schema have diverged'     => 3,
        'a mapper already exists, by hand'    => 3,
        'the schema IS the model'             => -3,
        'CRUD with validation'                => -3,
        'no test suite to lean on'            => -2,
    ];

    $total = 0;
    $hits  = [];

    foreach ($answers as $signal => $present) {
        if ($present) {
            $total += $signals[$signal];
            $hits[] = sprintf('%+d  %s', $signals[$signal], $signal);
        }
    }

    return ['score' => $total, 'hits' => $hits];
}

function verdict(int $score): string
{
    return match (true) {
        $score >= 6  => 'rung 5 is worth costing properly',
        $score >= 2  => 'rungs 1-4, and revisit in a year',
        default      => 'rungs 1-4. Rung 5 would be theatre.',
    };
}


// ═══════════════════════════════════════════════════════════════════════════
section('Application A — a workshop management system, 90k lines');

$a = score([
    'invariants spanning entities'         => true,
    'a lifecycle with illegal transitions' => true,
    'rules the business argues about'      => true,
    'calculations with legal consequences' => true,
    'domain and schema have diverged'      => true,
    'a mapper already exists, by hand'     => true,
    'the schema IS the model'              => false,
    'CRUD with validation'                 => false,
    'no test suite to lean on'             => true,
]);

foreach ($a['hits'] as $hit) {
    echo "      {$hit}\n";
}

printf("\n      score %d  ->  %s\n", $a['score'], verdict($a['score']));

echo "\n  Note the last signal: no test suite. It does NOT flip the answer, but\n";
echo "  it changes the ORDER. Rung 5 without characterisation tests is a\n";
echo "  rewrite wearing a plan. Start at rungs 1-3, write the tests on the way\n";
echo "  up, and let those make rung 5 possible. That is Lesson 5.2.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Application B — an internal booking tool, 140k lines');

$b = score([
    'invariants spanning entities'         => false,
    'a lifecycle with illegal transitions' => true,
    'rules the business argues about'      => false,
    'calculations with legal consequences' => false,
    'domain and schema have diverged'      => false,
    'a mapper already exists, by hand'     => false,
    'the schema IS the model'              => true,
    'CRUD with validation'                 => true,
    'no test suite to lean on'             => false,
]);

foreach ($b['hits'] as $hit) {
    echo "      {$hit}\n";
}

printf("\n      score %d  ->  %s\n", $b['score'], verdict($b['score']));

echo "\n  It is LARGER than application A and has a better test suite. Neither\n";
echo "  is a signal. A form goes in, a row comes out, and one status field has\n";
echo "  a lifecycle — which rungs 1 and 2 handle in an afternoon.\n\n";
echo "  Build rung 5 here and you get four layers of indirection around an\n";
echo "  UPDATE statement, plus two vocabularies in every code review. Module\n";
echo "  0's warning, arriving inside an existing codebase where it is much\n";
echo "  harder to hear — because by now somebody has read a book.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('What is NOT on the list');

$notSignals = [
    'the codebase is large',
    'the codebase is old',
    'it handles money',
    'the team wants to learn DDD',
    'a conference talk was persuasive',
    'the framework is unfashionable',
];

foreach ($notSignals as $item) {
    printf("      %s\n", $item);
}

echo "\n  Every one of those has been used to justify a domain layer, and not\n";
echo "  one of them predicts whether the layer will pay for itself.\n\n";
echo "  'It handles money' is the most persuasive and the most misleading:\n";
echo "  handling money is a reason for VALUE OBJECTS, which is rung 3 and\n";
echo "  costs almost nothing. It is not by itself a reason for rung 5.\n\n";
echo "  RULE: the signal is always about the RULES, never about the size, the\n";
echo "  age, the stack or the team's reading list.\n\n";
