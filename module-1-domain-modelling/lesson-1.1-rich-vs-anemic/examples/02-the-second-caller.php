<?php
declare(strict_types=1);

/**
 * Example 02 — The second caller
 * ===============================
 *
 * The anemic model from example 01 does not fail on the day it is written. It
 * fails at the second caller, and every one of the four callers below is a
 * reasonable thing for a competent developer to write under real pressure.
 *
 * That is the point. None of these is carelessness. Each one is a local
 * decision that is correct locally.
 *
 *     php 02-the-second-caller.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 70) . "\n  {$title}\n" . str_repeat('=', 70) . "\n\n";
}

function describe(CourtesyVehicle $v): string
{
    return sprintf(
        '%s  allocated=%-5s  jobCard=%s',
        $v->fleetCode,
        var_export($v->allocated, true),
        $v->allocatedToJobCard ?? '(none)'
    );
}


// ── The model and service from example 01, unchanged ────────────────────────

final class CourtesyVehicle
{
    public bool    $allocated          = false;
    public ?string $allocatedToJobCard = null;

    public function __construct(public string $fleetCode) {}
}

final class CourtesyVehicleAlreadyOut extends RuntimeException {}

final class CourtesyVehicleService
{
    public function allocate(CourtesyVehicle $vehicle, string $jobCardReference): void
    {
        if ($vehicle->allocated) {
            throw new CourtesyVehicleAlreadyOut("{$vehicle->fleetCode} is already out.");
        }
        $vehicle->allocated          = true;
        $vehicle->allocatedToJobCard = $jobCardReference;
    }
}


// ═══════════════════════════════════════════════════════════════════════════
section('Caller 1 — the one the rule was written for');

$service = new CourtesyVehicleService();
$a = new CourtesyVehicle('CV-01');
$service->allocate($a, 'JC-1001');
echo "  " . describe($a) . "\n";
echo "\n  Correct. This is the caller everybody had in mind.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Caller 2 — the bulk import');

echo "  Ticket: \"Import 4,000 courtesy vehicle allocations from the old system.\"\n";
echo "  Calling the service per row took eleven minutes. This took nine seconds.\n\n";

$imported = [];
foreach ([['CV-02', 'JC-2001'], ['CV-03', 'JC-2002'], ['CV-03', 'JC-2003']] as [$code, $ref]) {
    $v = $imported[$code] ?? new CourtesyVehicle($code);
    $v->allocated          = true;         // ← the rule is not here
    $v->allocatedToJobCard = $ref;
    $imported[$code]       = $v;
}

foreach ($imported as $v) {
    echo "  " . describe($v) . "\n";
}
echo "\n  CV-03 was allocated twice. The second allocation silently replaced the\n";
echo "  first, and JC-2002 now believes it has a courtesy vehicle that is out\n";
echo "  on JC-2003.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Caller 3 — the admin fix-it screen');

echo "  Support call: \"CV-04 is showing as out but it's on the forecourt.\"\n";
echo "  The service refuses to release it, because as far as it knows it IS out.\n";
echo "  So the admin screen sets the field.\n\n";

$c = new CourtesyVehicle('CV-04');
$c->allocated          = true;
$c->allocatedToJobCard = 'JC-3001';
echo "  Before: " . describe($c) . "\n";

$c->allocated = false;                     // ← releases it, leaves the reference
echo "  After:  " . describe($c) . "\n";

echo "\n  Available, and still pointing at JC-3001. Not a state the business has\n";
echo "  a name for. It will now be saved, and loaded by code that trusts it.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Caller 4 — the second service');

echo "  A fleet team joins and writes their own allocation path. They implement\n";
echo "  the rule too — slightly differently, because they read the ticket and\n";
echo "  not the service.\n\n";

final class FleetService
{
    public function assign(CourtesyVehicle $vehicle, string $jobCardReference): void
    {
        // Their reading: "a vehicle can't be reassigned to the SAME job card."
        if ($vehicle->allocatedToJobCard === $jobCardReference) {
            throw new RuntimeException('Already on that job card.');
        }
        $vehicle->allocated          = true;
        $vehicle->allocatedToJobCard = $jobCardReference;
    }
}

$d = new CourtesyVehicle('CV-05');
$fleet = new FleetService();
$fleet->assign($d, 'JC-4001');
echo "  " . describe($d) . "\n";
$fleet->assign($d, 'JC-4002');             // the original service would have refused
echo "  " . describe($d) . "\n";

echo "\n  Two services, two rules, one object. Which one is correct is now a\n";
echo "  question about the business, and it will be asked during an incident.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Caller 5 — the test factory');

echo "  Not production code, and arguably the worst of the five.\n\n";

/** A helper written to make tests readable. */
function aVehicleOut(string $code): CourtesyVehicle
{
    $v = new CourtesyVehicle($code);
    $v->allocated = true;                  // no job card set — nobody noticed
    return $v;
}

$e = aVehicleOut('CV-06');
echo "  " . describe($e) . "\n";

echo "\n  Every test using this factory now runs against a state that cannot\n";
echo "  occur in production. The tests pass. They are testing a different\n";
echo "  system.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('The count');

echo "  Callers that enforce the rule:      1 of 5\n";
echo "  Callers that were unreasonable:     0 of 5\n";
echo "  Lines of code that could prevent\n";
echo "  any of callers 2 to 5:              0\n\n";

echo "  The rule was never enforced. It was OBSERVED, by whoever remembered.\n\n";
echo "  Notice also that no amount of code review fixes this. Each of those\n";
echo "  four changes looks fine on its own diff; the problem is only visible\n";
echo "  if you already know the rule exists and go looking for it.\n\n";
echo "  Example 03 moves the rule one place to the left, and all four callers\n";
echo "  stop being possible.\n\n";
