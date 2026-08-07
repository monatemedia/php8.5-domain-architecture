<?php
declare(strict_types=1);

/**
 * Example 03 — The shared-model failure, step by step
 * ====================================================
 *
 * The most common way a DDD project goes wrong. Not a technical failure — a
 * vocabulary failure, and it starts with a decision that looks obviously
 * correct at the time.
 *
 * Part 1 walks the sequence and counts the damage. Part 2 runs the wreckage
 * against the three invariants it was supposed to protect, which is where the
 * abstract argument becomes a set of failing checks.
 *
 *     php 03-the-shared-model-failure.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — The sequence
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — Eight steps to a god object');

/**
 * Each step is the model as it stands, expressed as field => nullable.
 * A field is nullable exactly when some context that uses this class does not
 * need it — which is the mechanism by which unification destroys invariants.
 *
 * @var list<array{step:string, fields:array<string,bool>, note:string}>
 */
$steps = [
    [
        'step'   => '1. Three separate models',
        'fields' => ['registration' => false, 'odometer' => false, 'openFaults' => false, 'currentBay' => true],
        'note'   => 'Workshop\\Vehicle only. Accounts and Parts have their own, each complete.',
    ],
    [
        'step'   => '2. "This is duplication" — unify into a shared package',
        'fields' => ['registration' => false, 'odometer' => false, 'openFaults' => false, 'currentBay' => true],
        'note'   => 'The decision is made. Nothing has broken yet, which is why it survives review.',
    ],
    [
        'step'   => '3. Accounts only has a registration',
        'fields' => ['registration' => false, 'odometer' => true, 'openFaults' => true, 'currentBay' => true],
        'note'   => 'Everything Accounts does not have must now be nullable. Three fields flip.',
    ],
    [
        'step'   => '4. Parts needs fitment data',
        'fields' => [
            'registration' => false, 'odometer' => true, 'openFaults' => true, 'currentBay' => true,
            'engineCode' => true, 'modelYear' => true, 'trim' => true,
        ],
        'note'   => 'Workshop and Accounts never populate these. Three more nullable fields.',
    ],
    [
        'step'   => '5. THE INVARIANTS DIE',
        'fields' => [
            'registration' => false, 'odometer' => true, 'openFaults' => true, 'currentBay' => true,
            'engineCode' => true, 'modelYear' => true, 'trim' => true,
        ],
        'note'   => '"Odometer may not decrease" cannot be enforced on a nullable odometer.',
    ],
    [
        'step'   => '6. Parts adds fitmentList; Workshop tests break',
        'fields' => [
            'registration' => false, 'odometer' => true, 'openFaults' => true, 'currentBay' => true,
            'engineCode' => true, 'modelYear' => true, 'trim' => true, 'fitmentList' => true,
        ],
        'note'   => 'A change nobody in Workshop asked for, breaking tests nobody in Parts can read.',
    ],
    [
        'step'   => '7. Changes require three teams to agree, so they stop',
        'fields' => [
            'registration' => false, 'odometer' => true, 'openFaults' => true, 'currentBay' => true,
            'engineCode' => true, 'modelYear' => true, 'trim' => true, 'fitmentList' => true,
            'vatCode' => true, 'costCentre' => true, 'lastInvoicedAt' => true,
        ],
        'note'   => 'Nobody removes anything, because nobody can prove who is using it.',
    ],
    [
        'step'   => '8. Someone adds VehicleForAccounts alongside it',
        'fields' => [
            'registration' => false, 'odometer' => true, 'openFaults' => true, 'currentBay' => true,
            'engineCode' => true, 'modelYear' => true, 'trim' => true, 'fitmentList' => true,
            'vatCode' => true, 'costCentre' => true, 'lastInvoicedAt' => true,
        ],
        'note'   => 'Four models now, and no boundary between any of them. Back to step 1, worse.',
    ],
];

printf("  %-48s %7s %10s %9s\n", 'Step', 'Fields', 'Nullable', '% null');
echo '  ' . str_repeat('-', 78) . "\n";

foreach ($steps as $s) {
    $total    = count($s['fields']);
    $nullable = count(array_filter($s['fields']));
    $pct      = $total > 0 ? (int) round($nullable / $total * 100) : 0;

    printf("  %-48s %7d %10d %8d%%\n", $s['step'], $total, $nullable, $pct);
    echo "      " . $s['note'] . "\n";
}

echo "\n  Step 5 is the one that matters and it produces no error, no failing\n";
echo "  test and no review comment. It is a type signature getting a question\n";
echo "  mark in front of it.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — Running the wreckage
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — What the shared model can no longer promise');

/**
 * The shared model at step 7. Every field any context needs, most of them
 * nullable, and therefore no invariant enforceable on any of them.
 */
final class SharedVehicle
{
    public function __construct(
        public string  $registration,
        public ?int    $odometer      = null,
        public ?array  $openFaults    = null,
        public ?string $currentBay    = null,
        public ?string $engineCode    = null,
        public ?int    $modelYear     = null,
        public ?string $trim          = null,
        public ?array  $fitmentList   = null,
        public ?string $vatCode       = null,
        public ?string $costCentre    = null,
        public ?string $lastInvoicedAt = null,
    ) {}

    /**
     * The odometer rule, as it must now be written.
     *
     * Look at what the null check does: it silently ACCEPTS the update when the
     * odometer is unknown. It has to — Accounts constructs these objects
     * without one, and throwing would break Accounts. So the guard exists, is
     * reviewed, is tested, and does not hold.
     */
    public function recordOdometer(int $reading): void
    {
        if ($this->odometer !== null && $reading < $this->odometer) {
            throw new DomainException("Odometer cannot decrease: {$this->odometer} -> {$reading}.");
        }
        $this->odometer = $reading;
    }
}

/**
 * The workshop model from example 02, unchanged. One context, no nulls.
 */
final class WorkshopVehicle
{
    public function __construct(
        public readonly string $registration,
        private int            $odometer,
    ) {}

    public function recordOdometer(int $reading): void
    {
        if ($reading < $this->odometer) {
            throw new DomainException("Odometer cannot decrease: {$this->odometer} -> {$reading}.");
        }
        $this->odometer = $reading;
    }

    public function odometer(): int
    {
        return $this->odometer;
    }
}

$scenarios = [
    'Vehicle built by Workshop, odometer wound back' => [
        'shared'   => static fn(): SharedVehicle   => new SharedVehicle('AB12CDE', odometer: 84_500),
        'workshop' => static fn(): WorkshopVehicle => new WorkshopVehicle('AB12CDE', 84_500),
    ],
    'Vehicle built by Accounts, then reused by Workshop' => [
        'shared'   => static fn(): SharedVehicle   => new SharedVehicle('AB12CDE'),
        'workshop' => null,   // cannot happen: the constructor requires an odometer
    ],
];

foreach ($scenarios as $label => $builders) {
    echo "  {$label}\n";
    echo '  ' . str_repeat('-', 70) . "\n";

    $shared = $builders['shared']();
    try {
        $shared->recordOdometer(12_000);
        echo "    Shared model:   ACCEPTED a reading of 12,000. Odometer is now "
           . number_format((float) $shared->odometer) . ".\n";
    } catch (DomainException $e) {
        echo "    Shared model:   refused — " . $e->getMessage() . "\n";
    }

    if ($builders['workshop'] === null) {
        echo "    Workshop model: this scenario cannot occur. The constructor\n";
        echo "                    requires an odometer, so an Accounts-shaped\n";
        echo "                    vehicle cannot be built in the first place.\n\n";
        continue;
    }

    $workshop = $builders['workshop']();
    try {
        $workshop->recordOdometer(12_000);
        echo "    Workshop model: ACCEPTED. That would be a bug.\n";
    } catch (DomainException $e) {
        echo "    Workshop model: refused — " . $e->getMessage() . "\n";
    }
    echo "\n";
}


// ═══════════════════════════════════════════════════════════════════════════
section('The lesson');

echo "  The first scenario is the honest one: given the same complete data,\n";
echo "  both models enforce the rule. The shared model is not broken by being\n";
echo "  shared, in isolation.\n\n";
echo "  The second scenario is where it goes. An object built by a context that\n";
echo "  does not care about mileage flows into a context that does, and the\n";
echo "  guard waves it through — because the guard was WRITTEN to wave it\n";
echo "  through, deliberately, so that Accounts would not break.\n\n";
echo "  Nobody wrote a bug. Someone wrote a null check, which is the most\n";
echo "  ordinary line of code there is.\n\n";
echo "  THE TELL, and it arrives early:\n\n";
echo "    A class with many nullable fields, where which ones are populated\n";
echo "    depends on where the object came from.\n\n";
echo "  That is three models in a trenchcoat. If you see it, the boundary you\n";
echo "  need already exists in the business — it has simply not been drawn in\n";
echo "  the code.\n\n";
