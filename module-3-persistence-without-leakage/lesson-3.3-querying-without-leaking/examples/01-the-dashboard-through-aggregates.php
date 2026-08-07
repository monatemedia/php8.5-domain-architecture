<?php
declare(strict_types=1);

/**
 * Example 01 — The dashboard, built the only way you know so far
 * ==============================================================
 *
 * Every open job card, with its registration and total, largest first.
 *
 * Built through repositories, with the loads counted. Nothing here is badly
 * written — it is the obvious code, and the obvious code is the problem.
 *
 *     php 01-the-dashboard-through-aggregates.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// Cut-down aggregates, and repositories that count what they are asked for.
// ═══════════════════════════════════════════════════════════════════════════

final class Counter
{
    public int $jobCardLoads = 0;
    public int $vehicleLoads = 0;
    public int $lineObjects  = 0;

    public function reset(): void
    {
        $this->jobCardLoads = $this->vehicleLoads = $this->lineObjects = 0;
    }
}

final readonly class MiniLine
{
    public function __construct(public int $minor) {}
}

final class MiniJobCard
{
    /** @param list<MiniLine> $lines */
    public function __construct(
        public readonly string $id,
        public readonly string $vehicleId,
        public readonly string $status,
        private array          $lines,
    ) {}

    public function total(): int
    {
        $total = 0;
        foreach ($this->lines as $line) { $total += $line->minor; }
        return $total;
    }

    public function isOpen(): bool
    {
        return !in_array($this->status, ['invoiced', 'cancelled'], true);
    }
}

final class MiniVehicle
{
    public function __construct(
        public readonly string $id,
        public readonly string $registration,
    ) {}
}


// ═══════════════════════════════════════════════════════════════════════════
// A store with 400 job cards, each with a handful of lines.
// ═══════════════════════════════════════════════════════════════════════════

$counter = new Counter();

/** @var array<string, array{vehicle:string, status:string, lines:list<int>}> */
$jobCardRows = [];
/** @var array<string, string> */
$vehicleRows = [];

$statuses = ['booked', 'in_progress', 'awaiting_parts', 'completed', 'invoiced', 'cancelled'];

for ($i = 1; $i <= 400; $i++) {
    $jobCardId = sprintf('JC-%04d', $i);
    $vehicleId = sprintf('VEH-%04d', $i);

    $lines = [];
    for ($l = 0; $l < ($i % 5) + 1; $l++) {
        $lines[] = 1000 + (($i * 137 + $l * 91) % 9000);
    }

    $jobCardRows[$jobCardId] = [
        'vehicle' => $vehicleId,
        'status'  => $statuses[$i % 6],
        'lines'   => $lines,
    ];
    $vehicleRows[$vehicleId] = sprintf('AB%02dCD%s', $i % 100, chr(65 + ($i % 26)));
}

final class JobCardRepository
{
    public function __construct(
        private readonly array   $rows,
        private readonly Counter $counter,
    ) {}

    /** @return list<MiniJobCard> */
    public function all(): array
    {
        $out = [];

        foreach ($this->rows as $id => $row) {
            $this->counter->jobCardLoads++;

            $lines = [];
            foreach ($row['lines'] as $minor) {
                $this->counter->lineObjects++;
                $lines[] = new MiniLine($minor);
            }

            $out[] = new MiniJobCard($id, $row['vehicle'], $row['status'], $lines);
        }

        return $out;
    }
}

final class VehicleRepository
{
    public function __construct(
        private readonly array   $rows,
        private readonly Counter $counter,
    ) {}

    public function get(string $id): MiniVehicle
    {
        $this->counter->vehicleLoads++;

        return new MiniVehicle($id, $this->rows[$id]);
    }
}


// ═══════════════════════════════════════════════════════════════════════════
section('The dashboard, through the repositories');

$jobCards = new JobCardRepository($jobCardRows, $counter);
$vehicles = new VehicleRepository($vehicleRows, $counter);

$counter->reset();
$started = hrtime(true);

$dashboard = [];
foreach ($jobCards->all() as $jobCard) {
    if (!$jobCard->isOpen()) {
        continue;
    }

    $vehicle = $vehicles->get($jobCard->vehicleId);

    $dashboard[] = [
        'id'           => $jobCard->id,
        'registration' => $vehicle->registration,
        'total'        => $jobCard->total(),
    ];
}

usort($dashboard, static fn(array $a, array $b): int => $b['total'] <=> $a['total']);

$elapsedMs = (hrtime(true) - $started) / 1_000_000;

echo "  Top five:\n\n";
foreach (array_slice($dashboard, 0, 5) as $row) {
    printf("    %-10s %-10s %s\n", $row['id'], $row['registration'], number_format($row['total'] / 100, 2));
}

echo "\n  What it took:\n\n";
printf("    job cards loaded      %s\n", number_format($counter->jobCardLoads));
printf("    vehicles loaded       %s\n", number_format($counter->vehicleLoads));
printf("    line objects built    %s\n", number_format($counter->lineObjects));
printf("    rows on the screen    %s\n", number_format(count($dashboard)));
printf("    elapsed               %.1f ms\n", $elapsedMs);

$built = $counter->jobCardLoads + $counter->vehicleLoads + $counter->lineObjects;
printf("\n    objects built per screen row:  %.1f\n", $built / max(1, count($dashboard)));


// ═══════════════════════════════════════════════════════════════════════════
section('Why this is not a performance bug');

$observations = [
    'Every job card was loaded'   => 'including the invoiced and cancelled ones, which are then discarded',
    'Every line was rebuilt'      => 'to produce one number per job card',
    'A vehicle was loaded per row'=> 'to read one string off it',
    'Everything was thrown away'  => 'the screen keeps three columns',
];

foreach ($observations as $what => $why) {
    printf("  %-30s %s\n", $what, $why);
}

echo "\n  You could optimise this. Add a status filter to the repository, batch\n";
echo "  the vehicle loads, cache the totals. Each helps, and each pushes the\n";
echo "  repository a little further towards being a query builder — which is\n";
echo "  exactly what Lesson 3.1 spent a section refusing.\n";

echo "\n  THE SHAPE IS WRONG, NOT THE SPEED.\n\n";
echo "  The repository promises AGGREGATES: whole, consistent, rule-enforcing\n";
echo "  objects you are about to change. This screen changes nothing. It wants\n";
echo "  a TABLE — and it will still want a table after every optimisation.\n\n";
echo "  Example 02 asks for the same answer in the shape it actually is.\n\n";
