<?php
declare(strict_types=1);

/**
 * Example 02 — Invariant I10, and the hole nothing in Module 1 closes
 * ===================================================================
 *
 * I10: a courtesy vehicle is on at most one open job card AT ANY TIME.
 *
 * You built a CourtesyVehicle in Lesson 1.1 that refuses a second allocation.
 * That looks like I10. Part 1 shows that it is not, using nothing more exotic
 * than loading the same record twice.
 *
 * Part 2 closes the hole by putting the rule inside a boundary that can see
 * enough to enforce it, and then counts what that cost.
 *
 *     php 02-the-rule-that-spans-two.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// Lesson 1.1's class, unchanged.
// ═══════════════════════════════════════════════════════════════════════════

final class CourtesyVehicle
{
    private ?string $allocatedToJobCard = null;

    public function __construct(private readonly string $fleetCode) {}

    public function fleetCode(): string
    {
        return $this->fleetCode;
    }

    public function isAvailable(): bool
    {
        return $this->allocatedToJobCard === null;
    }

    public function allocatedToJobCard(): ?string
    {
        return $this->allocatedToJobCard;
    }

    public function allocateTo(string $jobCardReference): void
    {
        if (!$this->isAvailable()) {
            throw new DomainException(
                "{$this->fleetCode} is already out on {$this->allocatedToJobCard}."
            );
        }
        $this->allocatedToJobCard = $jobCardReference;
    }

    public function returnToFleet(): void
    {
        if ($this->isAvailable()) {
            throw new DomainException("{$this->fleetCode} is not out.");
        }
        $this->allocatedToJobCard = null;
    }
}

/** A store that hands out a FRESH object each time, which is what a real one does. */
final class VehicleStore
{
    /** @var array<string, ?string> fleetCode => job card it is out on */
    private array $rows = ['CV-01' => null, 'CV-02' => null, 'CV-03' => null];

    public function load(string $fleetCode): CourtesyVehicle
    {
        $vehicle = new CourtesyVehicle($fleetCode);

        if ($this->rows[$fleetCode] !== null) {
            $vehicle->allocateTo($this->rows[$fleetCode]);
        }

        return $vehicle;
    }

    public function save(CourtesyVehicle $vehicle): void
    {
        $this->rows[$vehicle->fleetCode()] = $vehicle->allocatedToJobCard();
    }

    /** @return list<string> fleet codes appearing against more than one job card */
    public function report(): array
    {
        $out = [];
        foreach ($this->rows as $code => $jobCard) {
            $out[] = sprintf('%s -> %s', $code, $jobCard ?? '(available)');
        }
        return $out;
    }
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — The guard that is not I10
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — One object obeys the rule');

$one = new CourtesyVehicle('CV-01');
$one->allocateTo('JC-1001');
echo "  CV-01 allocated to JC-1001.\n";

try {
    $one->allocateTo('JC-4471');
    echo "  [!!] second allocation succeeded\n";
} catch (DomainException $e) {
    echo "  [ok] second allocation refused: " . $e->getMessage() . "\n";
}

echo "\n  That is a real rule and it works. It is just not I10.\n";


section('Part 1b — Two objects, both obeying it');

$store = new VehicleStore();

echo "  Two requests arrive within a second of each other. Each loads the\n";
echo "  courtesy vehicle it wants — separately, as any repository would.\n\n";

$copyA = $store->load('CV-01');       // request for JC-1001
$copyB = $store->load('CV-01');       // request for JC-4471

printf("  Request A loaded CV-01, available: %s\n", $copyA->isAvailable() ? 'yes' : 'no');
printf("  Request B loaded CV-01, available: %s\n", $copyB->isAvailable() ? 'yes' : 'no');
printf("  Same object?  %s\n\n", $copyA === $copyB ? 'yes' : 'NO');

$copyA->allocateTo('JC-1001');
echo "  A: allocateTo('JC-1001')  -> ok\n";

$copyB->allocateTo('JC-4471');
echo "  B: allocateTo('JC-4471')  -> ok\n\n";

$store->save($copyA);
$store->save($copyB);

echo "  The fleet, afterwards:\n\n";
foreach ($store->report() as $row) {
    echo "    {$row}\n";
}

echo "\n  Two customers were both told they had CV-01. One of them will arrive\n";
echo "  to find it gone.\n\n";
echo "  Every object obeyed its rule. Neither guard was wrong. Nothing threw.\n";
echo "  The invariant is about THE WORLD AT AN INSTANT, and no object that can\n";
echo "  only see itself is in a position to make a statement about the world.\n";

echo "\n  Note what did NOT cause this:\n\n";
foreach ([
    'a missing guard'          => 'the guard is there and it fired correctly on one object',
    'a race condition in PHP'  => 'nothing here is concurrent; it is two sequential loads',
    'a bad repository'         => 'returning a fresh object per load is correct behaviour',
    'a careless developer'     => 'every line of this is what you would write',
] as $suspect => $why) {
    printf("    %-24s %s\n", $suspect, $why);
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — A boundary that can see enough
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — Put the rule where it can be enforced');

/**
 * The fleet owns every vehicle, so there is exactly ONE instance of each — and
 * that, rather than any cleverness, is what closes the hole.
 *
 * Note it hands out fleet codes, never CourtesyVehicle objects. Handing out a
 * mutable member would let a caller allocate behind the fleet's back, which is
 * Lesson 2.1's leaky collection with I10 as the casualty.
 */
final class CourtesyFleet
{
    /** @var array<string, CourtesyVehicle> */
    private array $vehicles = [];

    public function addVehicle(string $fleetCode): void
    {
        if (isset($this->vehicles[$fleetCode])) {
            throw new DomainException("{$fleetCode} is already in the fleet.");
        }
        $this->vehicles[$fleetCode] = new CourtesyVehicle($fleetCode);
    }

    public function allocate(string $fleetCode, string $jobCardReference): void
    {
        if (!isset($this->vehicles[$fleetCode])) {
            throw new DomainException("{$fleetCode} is not in this fleet.");
        }

        // The mirror rule: one courtesy vehicle per job card. This one needs to
        // see every vehicle, which is exactly why it lives here.
        foreach ($this->vehicles as $vehicle) {
            if ($vehicle->allocatedToJobCard() === $jobCardReference) {
                throw new DomainException(sprintf(
                    'Job card %s already has %s out.',
                    $jobCardReference,
                    $vehicle->fleetCode()
                ));
            }
        }

        // I10 itself is still enforced by the vehicle. The fleet's contribution
        // is not a new check — it is that there is only one of each vehicle for
        // the check to run against.
        $this->vehicles[$fleetCode]->allocateTo($jobCardReference);
    }

    /** @return list<string> */
    public function availableFleetCodes(): array
    {
        $out = [];
        foreach ($this->vehicles as $code => $vehicle) {
            if ($vehicle->isAvailable()) {
                $out[] = $code;
            }
        }
        return $out;
    }

    /** @return list<string> */
    public function report(): array
    {
        $out = [];
        foreach ($this->vehicles as $code => $vehicle) {
            $out[] = sprintf('%s -> %s', $code, $vehicle->allocatedToJobCard() ?? '(available)');
        }
        return $out;
    }
}

$fleet = new CourtesyFleet();
foreach (['CV-01', 'CV-02', 'CV-03'] as $code) {
    $fleet->addVehicle($code);
}

echo "  The same two requests, both going through the fleet:\n\n";

$fleet->allocate('CV-01', 'JC-1001');
echo "  A: allocate CV-01 to JC-1001  -> ok\n";

try {
    $fleet->allocate('CV-01', 'JC-4471');
    echo "  [!!] B: succeeded\n";
} catch (DomainException $e) {
    echo "  [ok] B: refused — " . $e->getMessage() . "\n";
}

echo "\n  And the mirror rule, which nothing before this could enforce either:\n\n";
try {
    $fleet->allocate('CV-02', 'JC-1001');
    echo "  [!!] JC-1001 got a second courtesy vehicle\n";
} catch (DomainException $e) {
    echo "  [ok] refused — " . $e->getMessage() . "\n";
}

echo "\n  The fleet:\n\n";
foreach ($fleet->report() as $row) {
    echo "    {$row}\n";
}
printf("\n  Available: %s\n", implode(', ', $fleet->availableFleetCodes()));


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — What it cost
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — The bill');

$ledger = [
    'Instances of each vehicle' => 'exactly one, which is the whole fix',
    'Lock scope per allocation' => 'the ENTIRE fleet, including cars nobody touched',
    'Rows loaded per allocation'=> 'every vehicle in the fleet',
    'Concurrent allocations'    => 'serialised, even for two different cars',
];

foreach ($ledger as $item => $value) {
    printf("  %-28s %s\n", $item, $value);
}

echo "\n  That second row is the large-aggregate failure from Lesson 2.1 §2 —\n";
echo "  chosen deliberately this time, rather than stumbled into. The whole\n";
echo "  question is whether the bill is affordable, and the answer is a NUMBER\n";
echo "  THAT HAS NOTHING TO DO WITH THE CODE:\n\n";

$scenarios = [
    ['Independent workshop', 12, 8, 'fine — contention is theoretical'],
    ['Regional group',       200, 150, 'uncomfortable at 9am'],
    ['National rental',      10000, 7000, 'broken; this is 2.1 §2 in production'],
];

printf("  %-24s %8s %10s   %s\n", 'Business', 'Vehicles', 'Per day', 'Verdict');
echo '  ' . str_repeat('-', 74) . "\n";
foreach ($scenarios as [$name, $vehicles, $perDay, $verdict]) {
    printf("  %-24s %8s %10s   %s\n", $name, number_format($vehicles), number_format($perDay), $verdict);
}

echo "\n  The same design, correct and incorrect, decided by how many cars\n";
echo "  somebody bought. You cannot read that off the model — you have to ask,\n";
echo "  and if nobody knows, say so rather than picking the sophisticated\n";
echo "  option to be safe.\n\n";
echo "  Example 03 is the other answer: let the rule hold eventually, and pay\n";
echo "  for it with a window and a phone call.\n\n";
