<?php
declare(strict_types=1);

/**
 * Example 03 — What leaks when a repository returns data
 * =======================================================
 *
 * A repository that returns rows has not inverted anything. It is a data
 * access object with a better name, and this example runs the consequence.
 *
 *     php 03-rows-or-aggregates.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — Returning rows');

/** @var list<array{id:string, status:string, lines:list<array{minor:int}>}> */
$storage = [
    ['id' => 'JC-1001', 'status' => 'completed', 'lines' => [['minor' => 4500], ['minor' => 8200]]],
];

final class RowRepository
{
    /** @param list<array<string, mixed>> $rows */
    public function __construct(private readonly array $rows) {}

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        foreach ($this->rows as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }
        throw new RuntimeException("No job card {$id}.");
    }
}

$rows = new RowRepository($storage);

echo "  Three callers, each doing the same job:\n\n";

// Caller A — the invoice screen
$row     = $rows->get('JC-1001');
$totalA  = 0;
foreach ($row['lines'] as $line) {
    $totalA += $line['minor'];
}
printf("    invoice screen   total = %d, invoiceable = %s\n",
    $totalA, $row['status'] === 'completed' ? 'yes' : 'no');

// Caller B — the nightly export, written six months later by somebody else
$row    = $rows->get('JC-1001');
$totalB = array_sum(array_column($row['lines'], 'minor'));
printf("    nightly export   total = %d, invoiceable = %s\n",
    $totalB, in_array($row['status'], ['completed', 'invoiced'], true) ? 'yes' : 'no');

// Caller C — the API, written by a third person
$row    = $rows->get('JC-1001');
$totalC = 0;
foreach ($row['lines'] as $line) {
    $totalC += $line['minor'];
}
printf("    the API          total = %d, invoiceable = %s\n",
    $totalC, $row['status'] !== 'cancelled' ? 'yes' : 'no');

echo "\n  Three answers to 'is this invoiceable', and they do not agree.\n\n";
echo "  Nobody was careless. Each caller had to decide what the data MEANS,\n";
echo "  because the repository handed over data and not a job card — so the\n";
echo "  rule was never in one place to be got right once.\n";

echo "\n  And note the totals DO agree, which is the trap: two of the three\n";
echo "  behaviours are identical, so a spot check finds nothing.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — Returning aggregates');

final class MiniJobCard
{
    /** @param list<int> $lineMinors */
    public function __construct(
        private readonly string $reference,
        private readonly string $status,
        private readonly array  $lineMinors,
    ) {}

    public function totalMinor(): int
    {
        return array_sum($this->lineMinors);
    }

    /** The rule. Once, here, where the data is. */
    public function isInvoiceable(): bool
    {
        return $this->status === 'completed';
    }

    public function reference(): string
    {
        return $this->reference;
    }
}

final class AggregateRepository
{
    /** @param array<string, MiniJobCard> $jobCards */
    public function __construct(private readonly array $jobCards) {}

    public function get(string $id): MiniJobCard
    {
        return $this->jobCards[$id] ?? throw new DomainException("No job card {$id}.");
    }
}

$aggregates = new AggregateRepository([
    'JC-1001' => new MiniJobCard('JC-1001', 'completed', [4500, 8200]),
]);

foreach (['invoice screen', 'nightly export', 'the API'] as $caller) {
    $jobCard = $aggregates->get('JC-1001');
    printf("    %-16s total = %d, invoiceable = %s\n",
        $caller, $jobCard->totalMinor(), $jobCard->isInvoiceable() ? 'yes' : 'no');
}

echo "\n  Three callers, one answer, and none of them had to know what\n";
echo "  'completed' means. There is nowhere to disagree.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('The rule');

echo "  THE REPOSITORY IS THE LAST PLACE THAT KNOWS ABOUT ROWS.\n\n";
echo "  Everything above it speaks in aggregates. That one sentence is most of\n";
echo "  what 'without leakage' means in this module's title.\n\n";

$costs = [
    'Rows out'       => 'every caller writes hydration; every caller may write it differently',
    'Arrays out'     => 'same, plus no type ever helps you',
    'DTOs out'       => 'better, and the rules still live outside — an anemic model by post',
    'Aggregates out' => 'the rule is in one place, and the type system carries it',
];

foreach ($costs as $shape => $consequence) {
    printf("  %-16s %s\n", $shape, $consequence);
}

echo "\n  The third row is the interesting one. A DTO looks like progress —\n";
echo "  typed, named, no arrays — and it moves nothing. The behaviour is still\n";
echo "  outside, so it is Lesson 1.1's anemic model with a delivery mechanism.\n\n";
