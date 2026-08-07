<?php
declare(strict_types=1);

/**
 * Example 03 — The decision procedure, run against six briefs
 * ============================================================
 *
 * Section 8 of the README describes a ten-minute procedure. This runs it as a
 * scorer so you can see it applied consistently to six systems at once.
 *
 * READ THIS BEFORE YOU TRUST A NUMBER
 * ------------------------------------
 * The weights below were chosen by a human with opinions. They are not derived
 * from anything, they have not been validated against outcomes, and a different
 * experienced developer would pick different ones. What the scorer is good for
 * is CONSISTENCY — applying the same questions to six systems in a row surfaces
 * the ones where your instinct and your reasoning disagree, and those are the
 * interesting ones.
 *
 * The output ends with a list of the briefs where the score and the honest
 * answer come apart. Those are the point of the example. A scorer that agreed
 * with itself everywhere would be teaching you nothing.
 *
 *     php 03-the-decision-procedure.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// The signals
// ═══════════════════════════════════════════════════════════════════════════

/** @var array<string, array{weight:int, text:string}> */
$signals = [
    // Arguing FOR a domain layer
    'S1' => ['weight' =>  3, 'text' => 'An invariant spans more than one object'],
    'S2' => ['weight' =>  3, 'text' => 'The lifecycle has illegal transitions, not just states'],
    'S3' => ['weight' =>  2, 'text' => 'Non-developers argue about the rules'],
    'S4' => ['weight' =>  2, 'text' => 'Being wrong costs money, breaks a law, or faces an auditor'],
    'S5' => ['weight' =>  2, 'text' => 'Calculations whose disagreement someone reconciles by hand'],
    'S6' => ['weight' =>  1, 'text' => 'The system will outlive its framework'],

    // Arguing AGAINST
    'A1' => ['weight' => -3, 'text' => 'The database schema IS the model, and everyone is content'],
    'A2' => ['weight' => -3, 'text' => 'The work is reporting, content management, or configuration'],
    'A3' => ['weight' => -2, 'text' => 'The only rule anyone can name is "this field is required"'],
    'A4' => ['weight' => -2, 'text' => 'The team has not done this, and the deadline forbids learning'],
    'A5' => ['weight' => -2, 'text' => 'It is a thin client over somebody else\'s API'],
];

// A4 is not really a subtraction. See the note printed at the end.
const VETO_SIGNAL = 'A4';


// ═══════════════════════════════════════════════════════════════════════════
// The briefs
// ═══════════════════════════════════════════════════════════════════════════

/** @var array<string, array{brief:string, present:list<string>}> */
$systems = [
    'Workshop job cards' => [
        'brief'   => 'Vehicles booked in, work done, parts and labour billed, invoice issued. '
                   . 'Courtesy vehicles loaned out. Additional work needs authorisation.',
        'present' => ['S1', 'S2', 'S3', 'S4', 'S5'],
    ],

    'Company blog' => [
        'brief'   => 'Posts, categories, authors, a draft/published flag, an admin screen.',
        'present' => ['A1', 'A2', 'A3'],
    ],

    'Expense claims' => [
        'brief'   => 'Employees submit claims. Approval chain with limits by grade. A claim cannot '
                   . 'be approved by its own submitter. Finance exports approved claims to payroll. '
                   . 'Team is new to this and the deadline is fixed.',
        'present' => ['S1', 'S2', 'S3', 'A4'],
    ],

    'Marketing dashboard' => [
        'brief'   => 'Reads six upstream tables, aggregates by week, renders charts. Writes nothing.',
        'present' => ['A1', 'A2'],
    ],

    'Multi-country payroll' => [
        'brief'   => 'Salary, tax and contribution rules across three jurisdictions, each with its '
                   . 'own thresholds and rounding. Audited annually. Rules change every budget.',
        'present' => ['S1', 'S2', 'S3', 'S4', 'S5', 'S6'],
    ],

    'Admin settings panel' => [
        'brief'   => 'Forty key/value settings, a form, a save button, an audit log of who changed what.',
        'present' => ['A1', 'A2', 'A3'],
    ],
];


// ═══════════════════════════════════════════════════════════════════════════
// Score them
// ═══════════════════════════════════════════════════════════════════════════
section('The signals, and what each is worth');

foreach ($signals as $id => $s) {
    printf("  %-4s %+3d   %s\n", $id, $s['weight'], $s['text']);
}

echo "\n  Maximum for a domain layer: ";
echo array_sum(array_map(
    static fn(array $s): int => max(0, $s['weight']),
    $signals
));
echo "\n";


section('The six briefs, scored');

/**
 * @param list<string> $present
 * @param array<string, array{weight:int, text:string}> $signals
 */
function scoreOf(array $present, array $signals): int
{
    $total = 0;
    foreach ($present as $id) {
        $total += $signals[$id]['weight'];
    }
    return $total;
}

function recommendationFor(int $score): string
{
    return match (true) {
        $score >= 7 => 'Tactical DDD',
        $score >= 3 => 'Scoped domain model, or transaction scripts',
        default     => 'Active Record',
    };
}

/** @var array<string, array{score:int, recommendation:string, vetoed:bool}> $results */
$results = [];

foreach ($systems as $name => $system) {
    $score  = scoreOf($system['present'], $signals);
    $vetoed = in_array(VETO_SIGNAL, $system['present'], true);

    $results[$name] = [
        'score'          => $score,
        'recommendation' => recommendationFor($score),
        'vetoed'         => $vetoed,
    ];

    printf("  %s\n", $name);
    echo '  ' . str_repeat('-', 70) . "\n";
    foreach (explode("\n", wordwrap($system['brief'], 68)) as $l) {
        echo "    {$l}\n";
    }
    echo "\n    Signals present: " . implode(', ', $system['present']) . "\n";
    printf("    Score: %+d\n", $score);
    printf("    Says:  %s%s\n\n", recommendationFor($score), $vetoed ? '   (see veto note below)' : '');
}


// ═══════════════════════════════════════════════════════════════════════════
// Summary
// ═══════════════════════════════════════════════════════════════════════════
section('Summary');

printf("  %-26s %8s   %s\n", 'System', 'Score', 'Recommendation');
echo '  ' . str_repeat('-', 70) . "\n";
foreach ($results as $name => $r) {
    printf("  %-26s %+8d   %s\n", $name, $r['score'], $r['recommendation']);
}


// ═══════════════════════════════════════════════════════════════════════════
// Where the scorer is wrong
// ═══════════════════════════════════════════════════════════════════════════
section('Where this scorer is wrong, and why that matters');

echo "  1. A4 IS A VETO, NOT A SUBTRACTION.\n\n";
echo "     'Expense claims' scores in the middle band because A4 subtracted two\n";
echo "     points from an otherwise decent case. That arithmetic is nonsense. A\n";
echo "     team that cannot sustain the discipline does not get a slightly worse\n";
echo "     domain layer — it gets architecture theatre, which is worse than the\n";
echo "     Active Record version it replaced.\n\n";
echo "     The honest answer for that brief is: transaction scripts now, one\n";
echo "     small domain model around the approval rule once someone has learned\n";
echo "     the pattern on something that does not ship in March.\n\n";

echo "  2. THE SCORE IS FOR A SYSTEM. THE DECISION IS PER CONTEXT.\n\n";
echo "     'Multi-country payroll' scores at the top, but the settings screen\n";
echo "     inside it is still a settings screen. Nothing about a high score\n";
echo "     obliges you to model the whole application. The usual right answer is\n";
echo "     one carefully modelled aggregate and forty ordinary Active Record\n";
echo "     classes, and that is proportionate rather than inconsistent.\n\n";

echo "  3. THE WEIGHTS ARE OPINIONS.\n\n";
echo "     S1 and S2 are weighted 3 because in the author's experience they are\n";
echo "     the signals that predict regret most reliably. That is an anecdote\n";
echo "     with a number attached. Change them and see which briefs move — the\n";
echo "     ones that move are the ones where you were never confident anyway.\n\n";

echo "  4. NOBODY IN THE BUSINESS WAS ASKED.\n\n";
echo "     Every signal above is answerable only by talking to people who use\n";
echo "     the system. A developer scoring these alone at their desk is scoring\n";
echo "     their assumptions. That is the largest error in this file and no\n";
echo "     amount of arithmetic fixes it.\n\n";
