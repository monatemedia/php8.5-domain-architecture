<?php
declare(strict_types=1);

/**
 * Example 01 — Two plans, quarter by quarter
 * ===========================================
 *
 * The same legacy system, the same team, the same eighteen months. One plan
 * rewrites; one strangles. The difference that matters is not the endpoint.
 *
 *     php 01-rewrite-or-strangle.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

/**
 * @param list<array{quarter:string, done:string, betterOff:bool, shippable:bool}> $plan
 */
function timeline(array $plan): void
{
    printf("  %-4s %-44s %-12s %s\n", 'Q', 'what exists at the end of it', 'better off?', 'shippable?');
    printf("  %s\n", str_repeat('-', 82));

    foreach ($plan as $step) {
        printf(
            "  %-4s %-44s %-12s %s\n",
            $step['quarter'],
            $step['done'],
            $step['betterOff'] ? 'yes' : 'NO',
            $step['shippable'] ? 'yes' : 'NO'
        );
    }
}


// ═══════════════════════════════════════════════════════════════════════════
section('Plan A — the rewrite');

timeline([
    ['quarter' => 'Q1', 'done' => 'new domain model, no persistence',        'betterOff' => false, 'shippable' => false],
    ['quarter' => 'Q2', 'done' => 'persistence, 60% of the features',        'betterOff' => false, 'shippable' => false],
    ['quarter' => 'Q3', 'done' => '80% of the features',                     'betterOff' => false, 'shippable' => false],
    ['quarter' => 'Q4', 'done' => 'the last 20%, where the rules were',      'betterOff' => false, 'shippable' => false],
    ['quarter' => 'Q5', 'done' => 'still the last 20%',                      'betterOff' => false, 'shippable' => false],
    ['quarter' => 'Q6', 'done' => 'cancelled, or shipped with a big bang',   'betterOff' => false, 'shippable' => false],
]);

echo "\n  Every row says NO in both columns, and that is the whole problem.\n";
echo "  There is no quarter in which someone can ask 'can we stop here?' and\n";
echo "  get a useful answer.\n\n";
echo "  Q4 and Q5 are where these die. The last 20% of features is where ALL\n";
echo "  the business rules live — the special case for fleet accounts in\n";
echo "  March that nobody can explain and nobody dares drop.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Plan B — the strangler');

timeline([
    ['quarter' => 'Q1', 'done' => 'PartStock extracted, reservations strangled', 'betterOff' => true, 'shippable' => true],
    ['quarter' => 'Q2', 'done' => 'picking strangled, using Q1 tests',           'betterOff' => true, 'shippable' => true],
    ['quarter' => 'Q3', 'done' => 'reprioritised — nothing done here',           'betterOff' => true, 'shippable' => true],
    ['quarter' => 'Q4', 'done' => 'job cards extracted',                         'betterOff' => true, 'shippable' => true],
    ['quarter' => 'Q5', 'done' => 'reprioritised again',                         'betterOff' => true, 'shippable' => true],
    ['quarter' => 'Q6', 'done' => 'invoicing extracted',                         'betterOff' => true, 'shippable' => true],
]);

echo "\n  Note Q3 and Q5. The work stopped for six months, twice, and NOTHING\n";
echo "  bad happened — the codebase sat in a state that is strictly better\n";
echo "  than where it started.\n\n";
echo "  That property is the argument. Not speed: plan B may well take longer\n";
echo "  to reach the same place. It is that plan B survives the thing that\n";
echo "  actually happens to your quarter, which is that priorities change.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('What each plan is betting on');

$bets = [
    ['Plan A', 'eighteen uninterrupted months', 'nobody reprioritises, nobody leaves, the last 20% is small'],
    ['Plan B', 'nothing',                       'each step is worth having on its own'],
];

foreach ($bets as [$plan, $needs, $assumes]) {
    printf("  %-8s needs: %-32s\n", $plan, $needs);
    printf("  %-8s assumes: %s\n\n", '', $assumes);
}

echo "  A strangler is NOT a slower rewrite. It is a different thing, and the\n";
echo "  difference is that at every point you can stop and still be ahead.\n\n";
echo "  That is also why it gets approved. 'We will be better off in three\n";
echo "  weeks and again in six' is a proposal. 'We will be better off in\n";
echo "  eighteen months' is a request for faith.\n\n";
