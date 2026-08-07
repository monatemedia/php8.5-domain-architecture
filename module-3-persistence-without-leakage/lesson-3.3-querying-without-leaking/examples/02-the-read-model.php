<?php
declare(strict_types=1);

/**
 * Example 02 — The same answer, in the shape it actually is
 * ==========================================================
 *
 *     php 02-the-read-model.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// The read model. Scalars, formatting, nothing else.
// ═══════════════════════════════════════════════════════════════════════════

final readonly class Summary
{
    public function __construct(
        public string $jobCardId,
        public string $registration,
        public string $status,
        public int    $totalMinor,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['job_card_id'],
            (string) $row['registration'],
            (string) $row['status'],
            (int) $row['total_minor'],
        );
    }

    public function isOpen(): bool
    {
        return !in_array($this->status, ['invoiced', 'cancelled'], true);
    }

    public function formattedTotal(): string
    {
        return number_format($this->totalMinor / 100, 2);
    }
}


// ═══════════════════════════════════════════════════════════════════════════
section('The rows a single query would return');

/**
 * In production this is one SELECT with a join and a SUM, and the database
 * does the filtering and the sorting. Here the rows are handed in, so that
 * every branch is testable without a database — which is the same reason the
 * mapper in 3.2 took arrays.
 */
$rows = [];
$statuses = ['booked', 'in_progress', 'awaiting_parts', 'completed', 'invoiced', 'cancelled'];

for ($i = 1; $i <= 400; $i++) {
    $total = 0;
    for ($l = 0; $l < ($i % 5) + 1; $l++) {
        $total += 1000 + (($i * 137 + $l * 91) % 9000);
    }

    $rows[] = [
        'job_card_id'  => sprintf('JC-%04d', $i),
        'registration' => sprintf('AB%02dCD%s', $i % 100, chr(65 + ($i % 26))),
        'status'       => $statuses[$i % 6],
        'total_minor'  => $total,
    ];
}

printf("  %d rows, %d columns each. The database did the summing.\n", count($rows), count($rows[0]));


// ═══════════════════════════════════════════════════════════════════════════
section('The dashboard');

$started = hrtime(true);

$summaries = [];
foreach ($rows as $row) {
    $summary = Summary::fromRow($row);
    if ($summary->isOpen()) {
        $summaries[] = $summary;
    }
}

usort($summaries, static fn(Summary $a, Summary $b): int => $b->totalMinor <=> $a->totalMinor);

$elapsedMs = (hrtime(true) - $started) / 1_000_000;

echo "  Top five:\n\n";
foreach (array_slice($summaries, 0, 5) as $summary) {
    printf("    %-10s %-10s %s\n", $summary->jobCardId, $summary->registration, $summary->formattedTotal());
}

echo "\n  What it took:\n\n";
printf("    aggregates loaded     %d\n", 0);
printf("    line objects built    %d\n", 0);
printf("    objects built         %s (one per row)\n", number_format(count($rows)));
printf("    rows on the screen    %s\n", number_format(count($summaries)));
printf("    elapsed               %.1f ms\n", $elapsedMs);


// ═══════════════════════════════════════════════════════════════════════════
section('What the read model can do that no aggregate can');

echo "  It carries the REGISTRATION.\n\n";
echo "  JobCard holds a VehicleId, not a Vehicle — Lesson 2.1 was emphatic,\n";
echo "  and it was right: holding the object would mean loading a second\n";
echo "  aggregate on every read and locking it on every write.\n\n";
echo "  But a list on a screen has to show the registration. So the QUERY\n";
echo "  joins, and the read model carries the result.\n\n";
echo "  That single field is the whole argument for read models: the shape a\n";
echo "  screen needs and the shape a consistency boundary needs are DIFFERENT\n";
echo "  SHAPES, and serving both from one object compromises the boundary.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('And what it must never do');

echo "  It imports nothing from the domain. No Money, no JobCardId, no enum.\n";
echo "  Scalars only, and the reason is not purity:\n\n";
echo "      A read model that can reach the domain will eventually contain a\n";
echo "      rule.\n\n";
echo "  Give it a Money and somebody adds withDiscount(). Give it a\n";
echo "  JobCardStatus and somebody adds canBeInvoiced() — and now the\n";
echo "  invoicing rule lives in two places, one of which is a display object\n";
echo "  nobody regards as authoritative.\n\n";
echo "  That is Lesson 1.4's scattered guards, arriving through a door marked\n";
echo "  'presentation'.\n";

echo "\n  Note the cost this accepts: formattedTotal() here duplicates a little\n";
echo "  of Currency::format(), and isOpen() duplicates something\n";
echo "  JobCardStatus::isTerminal() already knows.\n\n";
echo "  Both are deliberate. isOpen() in particular is NOT the same question:\n";
echo "  the domain says which statuses have no successors; the dashboard says\n";
echo "  which job cards to show this morning. They agree today. If the\n";
echo "  workshop ever wants cancelled cards to linger on the list for a week,\n";
echo "  one changes and the other does not.\n\n";
echo "  Duplication between a domain rule and a display rule is often two\n";
echo "  rules that happen to agree. Merging them is how a screen preference\n";
echo "  ends up governing an invariant.\n\n";
