<?php
declare(strict_types=1);

/**
 * Example 02 — The round trip, done wrong and then right
 * =======================================================
 *
 * Part 1 maps with the ordinary factories, which is what everybody writes
 * first. It appears to work. Part 2 shows what it actually did.
 * Part 3 adds reconstitution and re-runs the same trip.
 *
 *     php 02-the-round-trip.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// A line with identity, and both ways in.
// ═══════════════════════════════════════════════════════════════════════════

final readonly class Line
{
    private function __construct(
        public string $id,
        public string $description,
        public int    $minor,
    ) {}

    /** CREATION. Mints an identity. */
    public static function create(string $description, int $minor): self
    {
        return new self('LN-' . strtoupper(bin2hex(random_bytes(4))), $description, $minor);
    }

    /** RECONSTITUTION. Accepts the identity it already had. */
    public static function reconstitute(string $id, string $description, int $minor): self
    {
        return new self($id, $description, $minor);
    }
}

final class Card
{
    /** @var array<string, Line> */
    private array $lines = [];

    /** @var list<string> */
    private array $events = [];

    private function __construct(public readonly string $reference) {}

    public static function open(string $reference): self
    {
        $card = new self($reference);
        $card->events[] = "JobCardOpened({$reference})";
        return $card;
    }

    public static function reconstitute(string $reference, array $lines): self
    {
        $card = new self($reference);
        foreach ($lines as $line) { $card->lines[$line->id] = $line; }
        return $card;                       // no event
    }

    public function addLine(Line $line): void { $this->lines[$line->id] = $line; }

    public function removeLine(string $lineId): void
    {
        if (!isset($this->lines[$lineId])) {
            throw new DomainException("No line {$lineId} on {$this->reference}.");
        }
        unset($this->lines[$lineId]);
    }

    /** @return list<Line> */
    public function lines(): array { return array_values($this->lines); }

    /** @return list<string> */
    public function releaseEvents(): array
    {
        $e = $this->events; $this->events = []; return $e;
    }
}


// ═══════════════════════════════════════════════════════════════════════════
section('The stored rows');

$row      = ['reference' => 'JC-1001'];
$lineRows = [
    ['line_id' => 'LN-AAAA', 'description' => 'Diagnostic', 'minor' => 4500],
    ['line_id' => 'LN-BBBB', 'description' => 'Front pads', 'minor' => 8200],
];

foreach ($lineRows as $l) { printf("  %-9s %-12s %d\n", $l['line_id'], $l['description'], $l['minor']); }


// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — Mapping with the ordinary factories');

$naive = Card::open($row['reference']);
foreach ($lineRows as $l) {
    $naive->addLine(Line::create($l['description'], $l['minor']));
}

echo "  Loaded. It looks completely fine:\n\n";
printf("    reference   %s\n", $naive->reference);
printf("    lines       %d\n", count($naive->lines()));
printf("    total       %d\n", array_sum(array_map(static fn(Line $l): int => $l->minor, $naive->lines())));

echo "\n  Right number of lines, right descriptions, right total. Every\n";
echo "  assertion anybody would think to write passes.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — What it actually did');

echo "  The identities:\n\n";
foreach ($naive->lines() as $i => $line) {
    printf("    stored %-9s ->  loaded %s\n", $lineRows[$i]['line_id'], $line->id);
}

echo "\n  Every line has a brand new identity. Now a user does something\n";
echo "  entirely ordinary — opens a job card and removes a line:\n\n";

try {
    $naive->removeLine('LN-AAAA');
    echo "  [!!] removed\n";
} catch (DomainException $e) {
    echo "  [FAILS] " . $e->getMessage() . "\n";
}

echo "\n  The line is right there on the screen. Its id came from the database,\n";
echo "  the user clicked it, and the aggregate says there is no such line.\n";

echo "\n  And the events:\n\n";
foreach ($naive->releaseEvents() as $event) {
    echo "    [!!] {$event}\n";
}

echo "\n  Loading a job card announced that a vehicle had been booked in. Every\n";
echo "  handler subscribed to that fires — a welcome email, an index update, a\n";
echo "  notification — every time anybody opens the page.\n";

echo "\n  NOTE WHAT MAKES THIS DANGEROUS: within a single request it works.\n";
echo "  Load, remove, save — the ids are consistent because they were minted\n";
echo "  moments ago. It only fails ACROSS requests, which is exactly what a\n";
echo "  unit test does not do.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — The same trip, with reconstitution');

$lines = [];
foreach ($lineRows as $l) {
    $lines[] = Line::reconstitute($l['line_id'], $l['description'], $l['minor']);
}
$loaded = Card::reconstitute($row['reference'], $lines);

echo "  The identities:\n\n";
foreach ($loaded->lines() as $i => $line) {
    printf("    stored %-9s ->  loaded %-9s  %s\n",
        $lineRows[$i]['line_id'], $line->id,
        $lineRows[$i]['line_id'] === $line->id ? 'same' : 'DIFFERENT');
}

echo "\n  Removing the line the user clicked:\n\n";
$loaded->removeLine('LN-AAAA');
printf("    removed. %d line(s) left.\n", count($loaded->lines()));

echo "\n  Events announced by loading:\n\n";
$events = $loaded->releaseEvents();
echo $events === [] ? "    none. Nothing happened, so nothing was announced.\n" : "    [!!] " . implode(', ', $events) . "\n";


// ═══════════════════════════════════════════════════════════════════════════
section('The difference, in one line');

echo "  create()        mints identity, announces, enforces creation rules\n";
echo "  reconstitute()  accepts identity, announces nothing, trusts the row\n\n";
echo "  They are DIFFERENT OPERATIONS that happen to produce the same type.\n";
echo "  A model offering only the first cannot be loaded, and the version that\n";
echo "  pretends otherwise passes every test written in one request.\n\n";
