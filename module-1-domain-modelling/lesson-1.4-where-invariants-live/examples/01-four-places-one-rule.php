<?php
declare(strict_types=1);

/**
 * Example 01 — One rule, four homes, four blast radii
 * ====================================================
 *
 * The rule: labour is billed in whole tenths of an hour, rounded up.
 *
 * Enforced four ways, then attacked from five directions. Count which attacks
 * each version survives.
 *
 *     php 01-four-places-one-rule.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

/** ceil($minutes / 6) without a float ever appearing. */
function tenthsFor(int $minutes): int
{
    return intdiv($minutes + 5, 6);
}


// ═══════════════════════════════════════════════════════════════════════════
// HOME 1 — the API boundary
// ═══════════════════════════════════════════════════════════════════════════
section('Home 1 — the API boundary');

final class BoundaryLine
{
    public function __construct(public int $tenths) {}
}

/** The rule lives here, in the controller. */
function apiAddLabour(array $request): BoundaryLine
{
    $minutes = (int) ($request['minutes'] ?? 0);
    if ($minutes < 0) {
        throw new InvalidArgumentException('Minutes cannot be negative.');
    }
    return new BoundaryLine(tenthsFor($minutes));
}

$viaApi = apiAddLabour(['minutes' => 7]);
echo "  Through the API: 7 minutes -> {$viaApi->tenths} tenths  (correct)\n";

$viaCron = new BoundaryLine(1);          // the nightly import
echo "  Through the cron: 7 minutes -> {$viaCron->tenths} tenths  (rounded DOWN)\n";

echo "\n  Holds for: requests through this controller.\n";
echo "  Fails for: the cron, the console command, the migration, the second\n";
echo "             controller somebody adds next year.\n";


// ═══════════════════════════════════════════════════════════════════════════
// HOME 2 — a service
// ═══════════════════════════════════════════════════════════════════════════
section('Home 2 — a service');

final class ServiceLine
{
    public int $tenths = 0;
}

final class LabourService
{
    public function record(ServiceLine $line, int $minutes): void
    {
        if ($minutes < 0) {
            throw new InvalidArgumentException('Minutes cannot be negative.');
        }
        $line->tenths = tenthsFor($minutes);
    }
}

$line    = new ServiceLine();
$service = new LabourService();
$service->record($line, 7);
echo "  Through the service:  7 minutes -> {$line->tenths} tenths  (correct)\n";

$bypass = new ServiceLine();
$bypass->tenths = 1;                     // the bulk import, again
echo "  Direct assignment:                {$bypass->tenths} tenths  (rounded DOWN)\n";

echo "\n  Better: one home, and it is findable. Still fails for anyone who does\n";
echo "  not call the service — which, on Lesson 1.1's count, was four callers\n";
echo "  out of five.\n";


// ═══════════════════════════════════════════════════════════════════════════
// HOME 3 — a setter
// ═══════════════════════════════════════════════════════════════════════════
section('Home 3 — a setter on the model');

final class SetterLine
{
    private int $tenths = 0;

    public function setMinutes(int $minutes): void
    {
        if ($minutes < 0) {
            throw new InvalidArgumentException('Minutes cannot be negative.');
        }
        $this->tenths = tenthsFor($minutes);
    }

    public function tenths(): int
    {
        return $this->tenths;
    }
}

$setter = new SetterLine();
$setter->setMinutes(7);
echo "  Through the setter:   7 minutes -> {$setter->tenths()} tenths  (correct)\n";

$unset = new SetterLine();
echo "  Never set at all:                  {$unset->tenths()} tenths\n";

echo "\n  The property is private, so direct assignment is gone. But look at the\n";
echo "  second line: an object that has never been through the setter is\n";
echo "  perfectly constructible, and it is billing zero.\n\n";
echo "  A setter can only protect a CHANGE. It cannot protect the moment the\n";
echo "  object came into existence, which is the moment most objects are wrong.\n";


// ═══════════════════════════════════════════════════════════════════════════
// HOME 4 — the constructor
// ═══════════════════════════════════════════════════════════════════════════
section('Home 4 — the constructor');

final readonly class ConstructedLine
{
    private function __construct(public int $tenths) {}

    public static function fromMinutes(int $minutes): self
    {
        if ($minutes < 0) {
            throw new InvalidArgumentException('Minutes cannot be negative.');
        }
        return new self(tenthsFor($minutes));
    }
}

echo "  fromMinutes(7):  " . ConstructedLine::fromMinutes(7)->tenths . " tenths\n";

$attacks = [
    'Construct one with the wrong value directly' => static function (): void {
        new ConstructedLine(1);
    },
    'Construct one with no value at all' => static function (): void {
        // @phpstan-ignore-next-line — deliberately calling with no arguments
        ConstructedLine::fromMinutes();
    },
    'Change it after construction' => static function (): void {
        $line = ConstructedLine::fromMinutes(7);
        $line->tenths = 1;
    },
    'Clone it and substitute the value' => static function (): void {
        $line = ConstructedLine::fromMinutes(7);
        $copy = clone($line, ['tenths' => 1]);
        echo "         (produced {$copy->tenths})\n";
    },
];

echo "\n";
foreach ($attacks as $label => $attack) {
    try {
        $attack();
        echo "  [!!] {$label} — succeeded.\n";
    } catch (Throwable $e) {
        echo "  [ok] {$label}\n";
        echo "       " . $e::class . "\n";
    }
}


// ═══════════════════════════════════════════════════════════════════════════
section('The table, filled in by running it');

$rows = [
    ['Home',         'Holds for',                      'Fails for'],
    ['API boundary', 'requests through it',            'cron, console, migration, next controller'],
    ['Service',      'callers who remember it',        'import, admin screen, second service, tests'],
    ['Setter',       'callers using that setter',      'construction, hydration, clone'],
    ['Constructor',  'everything',                     '-'],
];

foreach ($rows as $i => [$home, $holds, $fails]) {
    printf("  %-14s %-32s %s\n", $home, $holds, $fails);
    if ($i === 0) {
        echo '  ' . str_repeat('-', 90) . "\n";
    }
}

echo "\n  Only the last row has an empty right-hand column, and that is the\n";
echo "  whole argument. A rule in the constructor holds for code that has not\n";
echo "  been written yet, which is where most of your future bugs live.\n\n";
echo "  Two caveats, so this is not read as absolutism:\n\n";
echo "    - The constructor covers construction. State that CHANGES also needs\n";
echo "      a named operation with the same guard — Lesson 1.3's\n";
echo "      recordOdometer() is that.\n\n";
echo "    - The boundary may still validate, for the MESSAGE. That is one rule\n";
echo "      and one presentation of it, not two homes. Section 5 of the README\n";
echo "      gives the test that tells them apart.\n\n";
