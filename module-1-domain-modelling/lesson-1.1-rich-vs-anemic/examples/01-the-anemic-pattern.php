<?php
declare(strict_types=1);

/**
 * Example 01 — The anemic pattern, used correctly
 * ================================================
 *
 * This example does NOT show the anemic model failing. It shows it working, so
 * that you can see why it survives code review.
 *
 * Read it as if you had just opened an unfamiliar codebase. There is nothing
 * wrong here that you could point at in a pull request.
 *
 *     php 01-the-anemic-pattern.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 70) . "\n  {$title}\n" . str_repeat('=', 70) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// The model: a record.
// ═══════════════════════════════════════════════════════════════════════════

final class CourtesyVehicle
{
    public bool    $allocated          = false;
    public ?string $allocatedToJobCard = null;

    public function __construct(public string $fleetCode) {}
}


// ═══════════════════════════════════════════════════════════════════════════
// The behaviour: a service.
// ═══════════════════════════════════════════════════════════════════════════

final class CourtesyVehicleAlreadyOut extends RuntimeException {}
final class CourtesyVehicleNotOut     extends RuntimeException {}

final class CourtesyVehicleService
{
    public function allocate(CourtesyVehicle $vehicle, string $jobCardReference): void
    {
        if ($vehicle->allocated) {
            throw new CourtesyVehicleAlreadyOut(
                "{$vehicle->fleetCode} is already out on {$vehicle->allocatedToJobCard}."
            );
        }
        $vehicle->allocated          = true;
        $vehicle->allocatedToJobCard = $jobCardReference;
    }

    public function returnToFleet(CourtesyVehicle $vehicle): void
    {
        if (!$vehicle->allocated) {
            throw new CourtesyVehicleNotOut("{$vehicle->fleetCode} is not out.");
        }
        $vehicle->allocated          = false;
        $vehicle->allocatedToJobCard = null;
    }

    public function isAvailable(CourtesyVehicle $vehicle): bool
    {
        return !$vehicle->allocated;
    }
}


// ═══════════════════════════════════════════════════════════════════════════
section('Used exactly as intended');

$service = new CourtesyVehicleService();
$cv      = new CourtesyVehicle('CV-01');

echo "  Available? " . ($service->isAvailable($cv) ? 'yes' : 'no') . "\n";

$service->allocate($cv, 'JC-1001');
echo "  Allocated to {$cv->allocatedToJobCard}\n";
echo "  Available? " . ($service->isAvailable($cv) ? 'yes' : 'no') . "\n";

try {
    $service->allocate($cv, 'JC-1002');
} catch (CourtesyVehicleAlreadyOut $e) {
    echo "  Refused: " . $e->getMessage() . "\n";
}

$service->returnToFleet($cv);
echo "  Returned. Available? " . ($service->isAvailable($cv) ? 'yes' : 'no') . "\n";

try {
    $service->returnToFleet($cv);
} catch (CourtesyVehicleNotOut $e) {
    echo "  Refused: " . $e->getMessage() . "\n";
}


// ═══════════════════════════════════════════════════════════════════════════
section('What a reviewer sees');

$observations = [
    'Both rules are implemented.'                                  => true,
    'Both throw a specific, named exception.'                       => true,
    'The service has one job and is easy to test.'                  => true,
    'The model is trivially constructible in a test.'               => true,
    'It maps onto a database row with no work at all.'              => true,
    'A new developer can read the whole thing in ninety seconds.'   => true,
];

foreach ($observations as $observation => $_) {
    echo "  [ok] {$observation}\n";
}

echo "\n  Every one of those is true, and none of them is a consolation prize.\n";
echo "  For a system with two rules and one caller, this is the right answer,\n";
echo "  and Module 0 would tell you to stop here.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('The one question it cannot answer');

echo "  Hand a reviewer this object and ask: is it valid?\n\n";

$mystery = new CourtesyVehicle('CV-07');
$mystery->allocated          = true;
$mystery->allocatedToJobCard = null;

printf(
    "    fleetCode=%s  allocated=%s  allocatedToJobCard=%s\n\n",
    $mystery->fleetCode,
    var_export($mystery->allocated, true),
    var_export($mystery->allocatedToJobCard, true)
);

echo "  Allocated to nothing. The service could never have produced this, and\n";
echo "  the service is not what produced it — three lines above, an assignment\n";
echo "  did.\n\n";
echo "  Nothing threw. Nothing warned. The object is in a state the business\n";
echo "  does not have a name for, and it will now be saved to a database and\n";
echo "  loaded back by code that trusts it.\n\n";
echo "  Example 02 shows the four completely ordinary ways this happens on a\n";
echo "  real project.\n\n";
