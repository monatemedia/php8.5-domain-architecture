<?php
declare(strict_types=1);

/**
 * Example 03 — Survey the data before you enforce anything
 * =========================================================
 *
 * Part 1: a factory that enforces the invariant. It looks right.
 * Part 2: the deployment, against real data.
 * Part 3: the factory that loads anything, plus a report.
 * Part 4: the two answers the report can give, and what each one means.
 *
 *     php 03-count-before-you-enforce.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

/**
 * A stock table, six years old. Most rows are fine.
 *
 * @return list<array{part:string, on_hand:int, reserved:int}>
 */
function theTable(): array
{
    $rows = [];

    for ($i = 1; $i <= 480; $i++) {
        $rows[] = ['part' => sprintf('P-%04d', $i), 'on_hand' => 10 + ($i % 20), 'reserved' => $i % 6];
    }

    // Eleven rows where release() went below zero, over six years.
    foreach ([17, 44, 91, 138, 202, 265, 301, 355, 402, 441, 470] as $i) {
        $rows[$i]['reserved'] = -($i % 5) - 1;
    }

    // Four rows where reserved exceeds on hand.
    foreach ([23, 156, 288, 399] as $i) {
        $rows[$i]['reserved'] = $rows[$i]['on_hand'] + 3;
    }

    return $rows;
}


// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — the factory that enforces. It looks right.');

final class StrictStock
{
    private function __construct(public readonly int $onHand, public readonly int $reserved) {}

    public static function of(int $onHand, int $reserved): self
    {
        if ($onHand < 0 || $reserved < 0) {
            throw new InvalidArgumentException('negative count');
        }

        if ($reserved > $onHand) {
            throw new DomainException('reserved exceeds on hand');
        }

        return new self($onHand, $reserved);
    }
}

echo "  It refuses to build an object that violates the invariant. That is\n";
echo "  Lesson 1.2's rule — a value object that can be constructed in an\n";
echo "  invalid state is not a value object — and in a greenfield codebase it\n";
echo "  is exactly right.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — the deployment');

$loaded = 0;
$failed = 0;
$firstFailure = null;

foreach (theTable() as $row) {
    try {
        StrictStock::of($row['on_hand'], $row['reserved']);
        $loaded++;
    } catch (Throwable $e) {
        $failed++;
        $firstFailure ??= sprintf('%s (on_hand %d, reserved %d): %s',
            $row['part'], $row['on_hand'], $row['reserved'], $e->getMessage());
    }
}

printf("  rows in the table   %d\n", count(theTable()));
printf("  loaded              %d\n", $loaded);
printf("  THREW               %d\n\n", $failed);
printf("  first failure       %s\n\n", $firstFailure);

echo "  The stock screen is now a 500 for fifteen parts, and the screen that\n";
echo "  would have told you WHICH fifteen is the one that is broken.\n\n";
echo "  Note what did not happen: nothing was corrupted, no data was lost,\n";
echo "  and the code is arguably more correct than it was. It is still a\n";
echo "  rollback, on the afternoon of the deploy, and the next attempt is a\n";
echo "  harder sell.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — load anything; report separately');

final class Stock
{
    private function __construct(public readonly int $onHand, public readonly int $reserved) {}

    /** Refuses the IMPOSSIBLE. Tolerates the merely inconsistent. */
    public static function of(int $onHand, int $reserved): self
    {
        if ($onHand < 0) {
            throw new InvalidArgumentException('on hand cannot be negative');
        }

        return new self($onHand, $reserved);
    }

    public function isConsistent(): bool
    {
        return $this->reserved >= 0 && $this->reserved <= $this->onHand;
    }
}

$survey = ['loaded' => 0, 'impossible' => 0, 'negative reserved' => 0, 'over-reserved' => 0];

foreach (theTable() as $row) {
    try {
        $stock = Stock::of($row['on_hand'], $row['reserved']);
    } catch (Throwable) {
        $survey['impossible']++;
        continue;
    }

    $survey['loaded']++;

    if (!$stock->isConsistent()) {
        $key = $stock->reserved < 0 ? 'negative reserved' : 'over-reserved';
        $survey[$key]++;
    }
}

foreach ($survey as $label => $count) {
    printf("  %-22s %d\n", $label, $count);
}

echo "\n  Every row loads. Nothing 500s. And you now have a number you can put\n";
echo "  in a message to somebody who knows the business.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Part 4 — the two answers, and what each one means');

$answers = [
    [
        'count'   => 15,
        'meaning' => 'six years of a missing floor check',
        'action'  => 'backfill the 15 rows, enforce, ship. An afternoon.',
    ],
    [
        'count'   => 40_000,
        'meaning' => 'the rule is not the rule',
        'action'  => 'STOP. Go and ask why. Do not write more code.',
    ],
];

foreach ($answers as $a) {
    printf("  %s bad rows\n", number_format($a['count']));
    printf("      means    %s\n", $a['meaning']);
    printf("      do       %s\n\n", $a['action']);
}

echo "  The second is the one worth planning for. Forty thousand rows is not\n";
echo "  a data problem; it is the business operating a rule you have not been\n";
echo "  told about — parts on order counted as reserved, or a supplier\n";
echo "  consignment model, or a branch that shares stock.\n\n";
echo "  Enforce your version of the invariant on top of that and you will be\n";
echo "  correct, and wrong.\n\n";
echo "  MOST FAILED EXTRACTIONS FAIL EXACTLY HERE, and they fail LATE — after\n";
echo "  the new path is written, when it starts throwing on data that has\n";
echo "  been fine for six years. The survey costs an hour and moves that\n";
echo "  discovery to the beginning.\n\n";
