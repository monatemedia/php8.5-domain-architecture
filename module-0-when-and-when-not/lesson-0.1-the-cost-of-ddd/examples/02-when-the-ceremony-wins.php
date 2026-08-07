<?php
declare(strict_types=1);

/**
 * Example 02 — When the ceremony starts to win
 * =============================================
 *
 * Example 01 ended with tactical DDD costing four types to enforce one rule,
 * and said plainly that on that evidence it is not worth it. It is not. One
 * rule never justifies a domain layer.
 *
 * This example does the thing that actually decides the argument: it changes
 * the rules, three times, the way a business does — and counts what has to be
 * edited each time.
 *
 * TWO HONESTY NOTES, because this example could easily be rigged.
 *
 *   1. The call-site counts below are a MODEL, not a measurement. They encode
 *      one assumption: that in Active Record and transaction-script codebases,
 *      the places that touch a rule grow as features are added, whereas in a
 *      domain model they do not, because there is only one way in. That
 *      assumption is the claim being made. If you think it is wrong for your
 *      codebase, the model is wrong for your codebase, and you should say so.
 *
 *   2. The crossover point depends entirely on how many rules you have. The
 *      script prints where it lands and invites you to change the inputs. Do
 *      change them. A number you have pushed around is worth more than one you
 *      have read.
 *
 *     php 02-when-the-ceremony-wins.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 70) . "\n  {$title}\n" . str_repeat('=', 70) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — The three rule changes, as a business would phrase them
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — Three changes, phrased the way they arrive');

$changes = [
    'R1' => 'A job card cannot be invoiced unless it is complete.',
    'R2' => 'A job card cannot be completed while additional work awaits authorisation.',
    'R3' => 'Labour bills in tenths of an hour, rounded up, minimum five tenths per line.',
];

foreach ($changes as $id => $text) {
    echo "  {$id}  {$text}\n";
}

echo "\n  None of these is exotic. All three came from someone in the workshop,\n";
echo "  and all three will change again within two years.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — Where each rule has to be enforced, per architecture
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — Places that must be edited for each change');

/**
 * The model. Read it, and disagree with it if you like.
 *
 * 'sites' is how many distinct places touch the relevant state today.
 * 'growth' is how many new ones appear per feature added afterwards.
 */
$architectures = [
    'A — Active Record' => [
        'sites'  => 4,   // controller, cron, admin action, import script
        'growth' => 1.0, // every new entry point touches the model directly
        'note'   => 'assignment is legal everywhere, so every entry point is a site',
    ],
    'B — Transaction script' => [
        'sites'  => 3,   // the use-case script, the cron, the admin action
        'growth' => 0.6, // some new features reuse an existing script; many do not
        'note'   => 'the rule is in a function, so only callers who use it are covered',
    ],
    'C — Tactical DDD' => [
        'sites'  => 1,   // the method on the aggregate
        'growth' => 0.0, // new entry points must go through the same method
        'note'   => 'there is no other way to change the state',
    ],
];

printf("  %-24s %-8s %-10s %s\n", 'Architecture', 'Sites', 'Growth', 'Why');
echo '  ' . str_repeat('-', 90) . "\n";
foreach ($architectures as $name => $a) {
    printf("  %-24s %-8d %-10.1f %s\n", $name, $a['sites'], $a['growth'], $a['note']);
}

echo "\n  'Sites' is the number of places a rule change has to be applied.\n";
echo "  'Growth' is how many new such places each additional feature creates.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — Total edits over time
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — Cumulative edits as the system grows');

// The unit throughout is ONE EDIT: find the place, change it, test it, review it.
//
// Up-front cost is charged in the same unit, so the domain layer is not silently
// free. Six types is not six edits — it is the types, plus the mapping layer,
// plus their tests, plus the team conversation about where things go. Sixty is a
// deliberately unflattering estimate. Lower it and watch the crossover move.
$setupCost = [
    'A — Active Record'      => 0,
    'B — Transaction script' => 4,
    'C — Tactical DDD'       => 60,
];

$ruleChangesPerYear = 4;
$featuresPerYear    = 6;
$years              = 4;

printf("  Assuming %d rule changes and %d new features per year.\n\n", $ruleChangesPerYear, $featuresPerYear);

printf("  %-24s", 'Architecture');
for ($y = 1; $y <= $years; $y++) {
    printf(" %8s", "yr {$y}");
}
echo "\n  " . str_repeat('-', 24 + $years * 9) . "\n";

/** @var array<string, list<float>> $totals */
$totals = [];

foreach ($architectures as $name => $a) {
    $running = (float) $setupCost[$name];
    $sites   = (float) $a['sites'];
    $row     = [];

    for ($y = 1; $y <= $years; $y++) {
        // Each rule change must be applied at every current site.
        $running += $ruleChangesPerYear * $sites;
        // Then the system grows, adding sites for next year.
        $sites   += $featuresPerYear * $a['growth'];
        $row[]    = $running;
    }

    $totals[$name] = $row;

    printf("  %-24s", $name);
    foreach ($row as $v) {
        printf(" %8.0f", $v);
    }
    echo "\n";
}

echo "\n";

// Where does C overtake A?
$crossover = null;
for ($y = 0; $y < $years; $y++) {
    if ($totals['C — Tactical DDD'][$y] < $totals['A — Active Record'][$y]) {
        $crossover = $y + 1;
        break;
    }
}

if ($crossover === null) {
    echo "  On these inputs, the domain layer never pays for itself.\n";
    echo "  That is a real outcome and you should be willing to reach it.\n";
} else {
    echo "  On these inputs, the domain layer costs more until year {$crossover},\n";
    echo "  and less after it.\n";
}

echo "\n  Things worth changing at the top of Part 3 and re-running:\n";
echo "    \$ruleChangesPerYear = 1   — a stable business. The layer never pays.\n";
echo "    \$ruleChangesPerYear = 12  — a volatile one. It pays almost at once.\n";
echo "    \$setupCost['C ...'] = 20  — a team that has done this before.\n";
echo "    \$years = 2                — a system with a two-year horizon.\n";
echo "\n  The crossover moves a long way, and that movement IS the lesson. The\n";
echo "  answer is a function of how volatile and how tangled your rules are,\n";
echo "  and of how long the system lives — not a property of the patterns.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 4 — What the model does not capture
// ═══════════════════════════════════════════════════════════════════════════
section('Part 4 — What this model leaves out, in both directions');

$against = [
    'Every read of the code pays the indirection, not just every edit.',
    'The mapping layer is ongoing work that appears in no column above.',
    'Onboarding is slower, and that cost recurs on every hire.',
    'Reporting queries do not fit the model and need a second read path.',
];

$forIt = [
    'An edit missed in an Active Record codebase is a production bug, not an edit.',
    'The counts above assume every site is FOUND. Frequently one is not.',
    'A domain model can be tested in milliseconds; the alternatives usually cannot.',
    'Rules readable by the business get corrected by the business, before release.',
];

echo "  Understating the cost of the domain layer:\n";
foreach ($against as $line) {
    echo "    - {$line}\n";
}

echo "\n  Understating the cost of the alternatives:\n";
foreach ($forIt as $line) {
    echo "    - {$line}\n";
}

echo "\n";
echo "  A model that flattered one side would be worth nothing. This one is\n";
echo "  wrong in both directions, and the honest conclusion is narrower than\n";
echo "  either camp usually admits:\n\n";
echo "    Tactical DDD wins when rules are numerous, overlapping, volatile, and\n";
echo "    costly to get wrong. It loses otherwise. Most software is otherwise.\n\n";
