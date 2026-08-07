<?php
declare(strict_types=1);

/**
 * Example 02 — Finding out what the old code actually does
 * =========================================================
 *
 * Part 1: the legacy procedure, and what its author believed it did.
 * Part 2: the same inputs through both paths, tabulated.
 * Part 3: three divergences, and the decision for each.
 * Part 4: the commit that ships them separately, and why.
 *
 *     php 02-characterisation.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

/** The legacy service, as inherited. */
final class LegacyReservations
{
    public function reserve(array &$row, int $qty): void
    {
        $available = $row['on_hand'] - $row['reserved'];

        if ($qty > $available) {
            throw new DomainException('not enough');
        }

        $row['reserved'] += $qty;
    }

    public function release(array &$row, int $qty): void
    {
        $row['reserved'] -= $qty;
    }
}

/** The extracted aggregate, in miniature. */
final class Stock
{
    public function __construct(private int $onHand, private int $reserved) {}

    public function reserve(int $qty): void
    {
        $this->assertPositive($qty);

        if ($qty > $this->onHand - $this->reserved) {
            throw new DomainException('insufficient-stock');
        }

        $this->reserved += $qty;
    }

    public function release(int $qty): void
    {
        $this->assertPositive($qty);

        if ($qty > $this->reserved) {
            throw new DomainException('insufficient-stock');
        }

        $this->reserved -= $qty;
    }

    public function reserved(): int { return $this->reserved; }

    public function onHand(): int { return $this->onHand; }

    private function assertPositive(int $qty): void
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('invalid-quantity');
        }
    }
}

final class NewReservations
{
    public function reserve(array &$row, int $qty): void
    {
        $stock = new Stock($row['on_hand'], $row['reserved']);
        $stock->reserve($qty);
        $row['reserved'] = $stock->reserved();
        $row['on_hand']  = $stock->onHand();
    }

    public function release(array &$row, int $qty): void
    {
        $stock = new Stock($row['on_hand'], $row['reserved']);
        $stock->release($qty);
        $row['reserved'] = $stock->reserved();
        $row['on_hand']  = $stock->onHand();
    }
}

/** Run one operation and describe what came out. */
function outcome(object $service, string $method, int $onHand, int $reserved, int $qty): string
{
    $row = ['on_hand' => $onHand, 'reserved' => $reserved];

    try {
        $service->{$method}($row, $qty);
    } catch (Throwable $e) {
        return 'refused (' . $e->getMessage() . ')';
    }

    return sprintf('on_hand %d, reserved %d', $row['on_hand'], $row['reserved']);
}


// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — what its author believed');

echo "  \"reserve() refuses to promise more than is available.\"\n\n";
echo "  True, and it has been true for six years. It is also the ONLY thing\n";
echo "  that is checked, and the author did not write down which cases they\n";
echo "  had in mind when they wrote it.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — both paths, same inputs');

$legacy = new LegacyReservations();
$new    = new NewReservations();

$cases = [
    ['reserve',  10, 4,  1],
    ['reserve',  10, 4,  6],
    ['reserve',  10, 4,  7],
    ['reserve',   4, 4,  1],
    ['reserve',   0, 0,  3],
    ['release',  10, 4,  1],
    ['release',  10, 4,  4],
    ['reserve',  10, 4, -5],
    ['reserve',  10, 4,  0],
    ['release',  10, 4,  9],
];

printf("  %-10s %-14s %-30s %-30s\n", 'method', 'row / qty', 'legacy', 'new');
printf("  %s\n", str_repeat('-', 88));

$divergences = [];

foreach ($cases as [$method, $onHand, $reserved, $qty]) {
    $old = outcome($legacy, $method, $onHand, $reserved, $qty);
    $now = outcome($new, $method, $onHand, $reserved, $qty);

    $label = sprintf('%d/%d qty %d', $onHand, $reserved, $qty);
    $same  = $old === $now;

    printf("  %-10s %-14s %-30s %-30s %s\n", $method, $label, $old, $now, $same ? '' : '<-- DIFFERS');

    if (!$same) {
        $divergences[] = sprintf('%s(%d) on %d/%d', $method, $qty, $onHand, $reserved);
    }
}

printf("\n  %d of %d inputs agree. %d differ.\n", count($cases) - count($divergences), count($cases), count($divergences));

echo "\n  The seven that agree are the test. They are what lets you swap the\n";
echo "  class in a call site and sleep. Write them down BEFORE you change\n";
echo "  anything — they are worthless discovered afterwards, because by then\n";
echo "  you are testing your own assumptions.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — three decisions, not one');

$decisions = [
    [
        'case'     => 'reserve(-5)',
        'legacy'   => 'silently REDUCES the reservation',
        'relied'   => 'no — no call site passes a negative; the logs are clean',
        'decision' => 'fix. Nobody depends on it.',
    ],
    [
        'case'     => 'reserve(0)',
        'legacy'   => 'succeeds and does nothing',
        'relied'   => 'MAYBE — a nightly job reserves whatever a form returned',
        'decision' => 'fix, but check that job first. A no-op becoming an exception is a 500 at 2am.',
    ],
    [
        'case'     => 'release(9) of 4',
        'legacy'   => 'drives the column to -5',
        'relied'   => 'no, but 11 rows are ALREADY negative from it',
        'decision' => 'fix, and clean the 11 rows in the same release.',
    ],
];

foreach ($decisions as $d) {
    printf("  %s\n", $d['case']);
    printf("      legacy does      %s\n", $d['legacy']);
    printf("      anything on it?  %s\n", $d['relied']);
    printf("      decision         %s\n\n", $d['decision']);
}

echo "  Three cases, three different investigations, and one of them changed\n";
echo "  the plan. 'We found some bugs' would have hidden all of that.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Part 4 — ship them separately');

echo "  A behaviour change shipped INSIDE a refactor is how a refactor gets\n";
echo "  blamed for an outage.\n\n";
echo "      commit 1   extract PartStock, strangle reservations\n";
echo "                 -> reproduces all three legacy behaviours exactly\n";
echo "                 -> the seven agreeing cases pass. Nothing changed.\n\n";
echo "      commit 2   refuse non-positive quantities\n";
echo "                 -> one behaviour change, one line, one sentence in the\n";
echo "                    release notes\n\n";
echo "      commit 3   refuse over-release; backfill the 11 negative rows\n\n";
echo "  If commit 1 causes an incident, it is the extraction. If commit 2\n";
echo "  does, it is the quantity rule. You find out in minutes rather than\n";
echo "  bisecting a 40-file diff.\n\n";
echo "  The reference solution does all three at once, because it is a\n";
echo "  teaching exercise with no users. Yours has users.\n\n";
