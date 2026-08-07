<?php
declare(strict_types=1);

/**
 * Example 01 — Everything that is in the way
 * ===========================================
 *
 * A cut-down aggregate with the same five defences the real JobCard has. Then
 * an attempt to rebuild one from a row, using only the public API.
 *
 * Every attempt fails, and each failure is a decision from an earlier module
 * working exactly as designed.
 *
 *     php 01-five-things-in-the-way.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// The aggregate, with Modules 1 and 2 applied properly.
// ═══════════════════════════════════════════════════════════════════════════

final readonly class MiniLineId
{
    private function __construct(public string $value) {}

    public static function generate(): self
    {
        return new self('LN-' . strtoupper(bin2hex(random_bytes(4))));
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }
}

final readonly class MiniLine
{
    private function __construct(
        public MiniLineId $id,
        public string     $description,
        public int        $minor,
    ) {}

    /** Mints a NEW identity. That is correct for creation and fatal for loading. */
    public static function create(string $description, int $minor): self
    {
        if (trim($description) === '') {
            throw new InvalidArgumentException('A line needs a description.');
        }

        return new self(MiniLineId::generate(), $description, $minor);
    }
}

final class MiniJobCard
{
    /** @var array<string, MiniLine> */
    private array $lines = [];

    private string $status = 'booked';

    /** @var list<string> */
    private array $recordedEvents = [];

    /** PRIVATE. Nothing outside may build one. */
    private function __construct(public readonly string $reference) {}

    /** The only public way in — and it announces a business event. */
    public static function open(string $reference): self
    {
        $jobCard = new self($reference);
        $jobCard->recordedEvents[] = "JobCardOpened({$reference})";

        return $jobCard;
    }

    public function addLine(MiniLine $line): void
    {
        if ($this->status === 'invoiced') {
            throw new DomainException('Invoiced. Lines are fixed.');
        }
        $this->lines[$line->id->value] = $line;
    }

    public function startWork(): void { $this->transitionTo('in_progress'); }
    public function complete(): void  { $this->transitionTo('completed'); }
    public function invoice(): void   { $this->transitionTo('invoiced'); }

    private function transitionTo(string $next): void
    {
        $allowed = [
            'booked'      => ['in_progress'],
            'in_progress' => ['completed'],
            'completed'   => ['invoiced'],
            'invoiced'    => [],
        ];

        if (!in_array($next, $allowed[$this->status], true)) {
            throw new DomainException("{$this->status} cannot become {$next}.");
        }

        $this->status = $next;
    }

    public function status(): string { return $this->status; }
    public function total(): int
    {
        $t = 0;
        foreach ($this->lines as $line) { $t += $line->minor; }
        return $t;
    }

    /** @return list<MiniLine> */
    public function lines(): array { return array_values($this->lines); }

    /** @return list<string> */
    public function releaseEvents(): array
    {
        $e = $this->recordedEvents;
        $this->recordedEvents = [];
        return $e;
    }
}


// ═══════════════════════════════════════════════════════════════════════════
section('The row we have to turn back into that');

$row = [
    'reference' => 'JC-1001',
    'status'    => 'invoiced',
];

$lineRows = [
    ['line_id' => 'LN-AAAA', 'description' => 'Diagnostic', 'minor' => 4500],
    ['line_id' => 'LN-BBBB', 'description' => 'Front pads', 'minor' => 8200],
];

foreach ($row as $k => $v) { printf("  %-12s %s\n", $k, $v); }
echo "\n";
foreach ($lineRows as $l) {
    printf("  line  %-9s %-12s %d\n", $l['line_id'], $l['description'], $l['minor']);
}

echo "\n  An invoiced job card, two lines, total 12700. Now rebuild it using\n";
echo "  only what the public API offers.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Five attempts, five obstacles');

$attempts = [
    '1. new MiniJobCard(...)' => static function () use ($row): void {
        // @phpstan-ignore-next-line — deliberately calling a private constructor
        new MiniJobCard($row['reference']);
    },

    '2. MiniJobCard::open(...) — but it announces' => static function () use ($row): void {
        $jobCard = MiniJobCard::open($row['reference']);
        $events  = $jobCard->releaseEvents();

        throw new RuntimeException(
            'loaded a job card and it announced: ' . implode(', ', $events)
        );
    },

    '3. MiniLine::create() — but the id changes' => static function () use ($lineRows): void {
        $line = MiniLine::create($lineRows[0]['description'], $lineRows[0]['minor']);

        if ($line->id->value !== $lineRows[0]['line_id']) {
            throw new RuntimeException(
                "stored id was {$lineRows[0]['line_id']}, rebuilt id is {$line->id->value}"
            );
        }
    },

    '4. Replay the lifecycle to reach "invoiced"' => static function () use ($row): void {
        $jobCard = MiniJobCard::open($row['reference']);
        $jobCard->releaseEvents();
        $jobCard->startWork();
        $jobCard->complete();
        $jobCard->invoice();
        // It worked, but see the note below.
        throw new RuntimeException('reached invoiced by replaying three transitions that already happened');
    },

    '5. Set the status directly' => static function () use ($row): void {
        $jobCard = MiniJobCard::open($row['reference']);
        // @phpstan-ignore-next-line — deliberately writing a private property
        $jobCard->status = $row['status'];
    },
];

foreach ($attempts as $label => $attempt) {
    try {
        $attempt();
        echo "  [!!] {$label}\n       succeeded, with no complaint\n";
    } catch (Throwable $e) {
        echo "  [blocked] {$label}\n";
        echo "            " . $e::class . ': ' . $e->getMessage() . "\n";
    }
    echo "\n";
}


// ═══════════════════════════════════════════════════════════════════════════
section('What each obstacle actually is');

$obstacles = [
    ['Private constructor',        'Lesson 1.2', 'so nothing can build one that skipped the factory'],
    ['open() records an event',    'Lesson 2.3', 'opening is a business event'],
    ['create() mints an id',       'Lesson 2.1', 'lines need identity so one can be removed'],
    ['Guarded transitions',        'Lesson 2.1', 'I9, in one table'],
    ['Private state, readonly',    'Lesson 1.1', 'nothing changes behind the aggregate\'s back'],
];

printf("  %-26s %-12s %s\n", 'Obstacle', 'From', 'Why it exists');
echo '  ' . str_repeat('-', 74) . "\n";
foreach ($obstacles as [$what, $from, $why]) {
    printf("  %-26s %-12s %s\n", $what, $from, $why);
}

echo "\n  Not one of them is a mistake. Each was argued for, and each is still\n";
echo "  right. Together they mean THE PUBLIC API CANNOT SAY 'THIS ALREADY\n";
echo "  EXISTS' — and that is the entire mapping problem.\n";

echo "\n  Attempt 4 deserves a second look, because it SUCCEEDED and is still\n";
echo "  wrong:\n\n";
echo "    - It replays three transitions that happened weeks ago, so any\n";
echo "      events they record fire again on every load.\n";
echo "    - It only works because 'invoiced' happens to be reachable. A job\n";
echo "      card stored as 'cancelled' cannot be reached this way from every\n";
echo "      state, and one stored in a status a later version REMOVED cannot\n";
echo "      be reached at all.\n";
echo "    - It is O(lifecycle) on every read.\n\n";
echo "  A thing that works for the common case and is unfixable for the rest\n";
echo "  is worse than a thing that does not work, because it ships.\n";

echo "\n  The answer is a second way in, for objects that already exist:\n";
echo "  reconstitution. Example 02 shows what happens without it.\n\n";
