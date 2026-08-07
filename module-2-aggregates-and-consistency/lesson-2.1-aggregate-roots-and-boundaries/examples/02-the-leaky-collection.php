<?php
declare(strict_types=1);

/**
 * Example 02 — The leaky collection
 * ==================================
 *
 * The most common way an aggregate that looks correct enforces nothing.
 *
 * Every guard is in place. The status machine is right. The lines are private.
 * And one accessor — the most innocuous method in the class — hands out
 * handles to the entities inside, and everything above becomes decorative.
 *
 *     php 02-the-leaky-collection.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

function money(int $cents): string
{
    return number_format($cents / 100, 2);
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — Mutable contents. Everything else is correct.
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — A well-guarded aggregate with mutable lines');

final class MutableLine
{
    public function __construct(
        public string $description,
        public int    $cents,
    ) {}
}

final class LeakyJobCard
{
    /** @var list<MutableLine> */
    private array $lines = [];

    private bool $invoiced = false;

    public function __construct(private readonly string $reference) {}

    /** INVARIANT I6: no line may be added to an invoiced job card. */
    public function addLine(MutableLine $line): void
    {
        if ($this->invoiced) {
            throw new DomainException("Job card {$this->reference} is invoiced. Lines are fixed.");
        }
        $this->lines[] = $line;
    }

    public function invoice(): void
    {
        $this->invoiced = true;
    }

    public function totalCents(): int
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += $line->cents;
        }
        return $total;
    }

    /** The innocuous accessor. Somebody needs to render the lines. */
    public function lines(): array
    {
        return $this->lines;
    }

    public function reference(): string
    {
        return $this->reference;
    }
}

$leaky = new LeakyJobCard('JC-1001');
$leaky->addLine(new MutableLine('Diagnostic', 4500));
$leaky->addLine(new MutableLine('Front pads', 8200));
$leaky->invoice();

printf("  %s invoiced at %s\n", $leaky->reference(), money($leaky->totalCents()));

echo "\n  The guard works — adding a line is refused:\n\n";
try {
    $leaky->addLine(new MutableLine('Late part', 3000));
    echo "  [!!] accepted\n";
} catch (DomainException $e) {
    echo "  [ok] refused: " . $e->getMessage() . "\n";
}

echo "\n  Now the same outcome, through the accessor:\n\n";
$lines = $leaky->lines();
$lines[1]->cents = 34000;

printf("  %s now totals %s.\n", $leaky->reference(), money($leaky->totalCents()));

echo "\n  An invoiced job card changed its total by 258.00 after invoicing.\n";
echo "  Nothing threw. addLine() was never called. Every guard in the class\n";
echo "  is still exactly as written and still correct — and irrelevant.\n";

echo "\n  Why the array copy did not save it — the array copy is real:\n\n";
$lines[] = new MutableLine('Appended to my own copy', 1);
printf("    my copy now holds      %d lines\n", count($lines));
printf("    the aggregate holds    %d lines\n", count($leaky->lines()));
echo "\n    So the ARRAY was genuinely copied. But an array of objects copies\n";
echo "    the handles, not the objects — and both arrays point at the same\n";
echo "    two lines. PHP protected the shape of the collection and nothing at\n";
echo "    all about its contents.\n";

echo "\n  Lesson 1.3 relied on exactly this and said so: returning the reading\n";
echo "  history was safe BECAUSE OdometerReading is immutable. That was not a\n";
echo "  detail. It was the whole reason it was safe.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — Immutable contents
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — The same aggregate, immutable lines');

final readonly class SafeLine
{
    public function __construct(
        public string $description,
        public int    $cents,
    ) {
        if ($cents < 0) {
            throw new InvalidArgumentException('A line cannot be negative.');
        }
    }
}

final class SafeJobCard
{
    /** @var list<SafeLine> */
    private array $lines = [];

    private bool $invoiced = false;

    public function __construct(private readonly string $reference) {}

    public function addLine(SafeLine $line): void
    {
        if ($this->invoiced) {
            throw new DomainException("Job card {$this->reference} is invoiced. Lines are fixed.");
        }
        $this->lines[] = $line;
    }

    public function invoice(): void
    {
        $this->invoiced = true;
    }

    public function totalCents(): int
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += $line->cents;
        }
        return $total;
    }

    /**
     * Safe to hand out, and safe for a specific reason: the contents cannot be
     * changed. Not because the array is copied — that was true in part 1 too.
     *
     * @return list<SafeLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function reference(): string
    {
        return $this->reference;
    }
}

$safe = new SafeJobCard('JC-2001');
$safe->addLine(new SafeLine('Diagnostic', 4500));
$safe->addLine(new SafeLine('Front pads', 8200));
$safe->invoice();

printf("  %s invoiced at %s\n\n", $safe->reference(), money($safe->totalCents()));

$attacks = [
    'Mutate a line through the accessor' => static function () use ($safe): void {
        $copy = $safe->lines();
        $copy[1]->cents = 34000;
    },
    'Add a line after invoicing'         => static function () use ($safe): void {
        $safe->addLine(new SafeLine('Late part', 3000));
    },
    'Reach the private array'            => static function () use ($safe): void {
        $stolen = $safe->lines;
    },
];

foreach ($attacks as $label => $attack) {
    try {
        $attack();
        echo "  [!!] {$label} — succeeded.\n";
    } catch (Throwable $e) {
        echo "  [ok] {$label}\n";
        echo "       " . $e::class . ': ' . $e->getMessage() . "\n";
    }
}

printf("\n  Total is still %s.\n", money($safe->totalCents()));


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — "But I need to change a line"
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — Changing a line, when lines cannot change');

echo "  The objection writes itself: the pads were quoted wrong and somebody\n";
echo "  has to correct them. If a line is immutable, how?\n\n";
echo "  You remove it and add another — through the root, which is where the\n";
echo "  rules live.\n\n";

final readonly class IdentifiedLine
{
    public function __construct(
        public string $id,
        public string $description,
        public int    $cents,
    ) {}
}

final class RevisableJobCard
{
    /** @var array<string, IdentifiedLine> */
    private array $lines = [];

    private bool $invoiced = false;

    public function __construct(private readonly string $reference) {}

    public function addLine(IdentifiedLine $line): void
    {
        $this->assertOpen();
        $this->lines[$line->id] = $line;
    }

    public function removeLine(string $lineId): void
    {
        $this->assertOpen();

        if (!isset($this->lines[$lineId])) {
            throw new DomainException("Job card {$this->reference} has no line {$lineId}.");
        }

        unset($this->lines[$lineId]);
    }

    public function invoice(): void
    {
        $this->invoiced = true;
    }

    public function totalCents(): int
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += $line->cents;
        }
        return $total;
    }

    /** @return list<IdentifiedLine> */
    public function lines(): array
    {
        return array_values($this->lines);
    }

    private function assertOpen(): void
    {
        if ($this->invoiced) {
            throw new DomainException("Job card {$this->reference} is invoiced. Lines are fixed.");
        }
    }
}

$revisable = new RevisableJobCard('JC-3001');
$revisable->addLine(new IdentifiedLine('L1', 'Diagnostic', 4500));
$revisable->addLine(new IdentifiedLine('L2', 'Front pads', 8200));
printf("  Before: %s\n", money($revisable->totalCents()));

$revisable->removeLine('L2');
$revisable->addLine(new IdentifiedLine('L3', 'Front pads (revised)', 9400));
printf("  After:  %s\n", money($revisable->totalCents()));

echo "\n  Two operations instead of one assignment. That sounds heavy-handed\n";
echo "  until you notice what it bought:\n\n";
echo "    - Both operations go through the root, so both are checked.\n";
echo "    - After invoicing, BOTH are refused. A mutable line would only have\n";
echo "      had one path to guard, and the guard was on the wrong one.\n";
echo "    - The correction is visible as a correction — a removal and an\n";
echo "      addition — rather than a number quietly changing.\n\n";

echo "  Refusing both after invoicing:\n\n";
$revisable->invoice();
foreach ([
    'removeLine after invoicing' => static fn() => $revisable->removeLine('L1'),
    'addLine after invoicing'    => static fn() => $revisable->addLine(new IdentifiedLine('L4', 'Late', 100)),
] as $label => $attempt) {
    try {
        $attempt();
        echo "  [!!] {$label} — succeeded.\n";
    } catch (DomainException $e) {
        echo "  [ok] {$label}\n";
    }
}

echo "\n  Note the lines are keyed by identity. That is why they need a LineId,\n";
echo "  and it is why LabourLine stops being a value object in the challenge —\n";
echo "  removing ONE PARTICULAR line is not something you can ask of a value.\n\n";
