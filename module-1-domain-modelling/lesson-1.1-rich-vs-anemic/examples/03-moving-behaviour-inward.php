<?php
declare(strict_types=1);

/**
 * Example 03 — The same model, rich
 * ==================================
 *
 * The five callers from example 02, run against a model that owns its own
 * rules. Four of them become impossible: not "refused at runtime" but
 * unwritable, because there is no property to assign.
 *
 * Count what actually changed. One class, no interface, no factory, no
 * repository, no abstract base. Moving a rule inward is a small move, and doing
 * only the small move is usually correct.
 *
 *     php 03-moving-behaviour-inward.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 70) . "\n  {$title}\n" . str_repeat('=', 70) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// The model, now with its rules
// ═══════════════════════════════════════════════════════════════════════════

final class CourtesyVehicleAlreadyOut extends DomainException
{
    public static function on(string $fleetCode, ?string $jobCard): self
    {
        return new self("{$fleetCode} is already out on " . ($jobCard ?? 'an unknown job card') . '.');
    }
}

final class CourtesyVehicleNotOut extends DomainException
{
    public static function on(string $fleetCode): self
    {
        return new self("{$fleetCode} is not out.");
    }
}

final class CourtesyVehicle
{
    // PHP 8.4 asymmetric visibility: read anywhere, write only in here.
    public private(set) bool    $allocated          = false;
    public private(set) ?string $allocatedToJobCard = null;

    public function __construct(public readonly string $fleetCode)
    {
        if (trim($fleetCode) === '') {
            throw new InvalidArgumentException('A courtesy vehicle needs a fleet code.');
        }
    }

    public function allocateTo(string $jobCardReference): void
    {
        if (trim($jobCardReference) === '') {
            throw new InvalidArgumentException('A job card reference is required.');
        }
        if ($this->allocated) {
            throw CourtesyVehicleAlreadyOut::on($this->fleetCode, $this->allocatedToJobCard);
        }
        $this->allocated          = true;
        $this->allocatedToJobCard = $jobCardReference;
    }

    public function returnToFleet(): void
    {
        if (!$this->allocated) {
            throw CourtesyVehicleNotOut::on($this->fleetCode);
        }
        $this->allocated          = false;
        $this->allocatedToJobCard = null;
    }

    public function isAvailable(): bool
    {
        return !$this->allocated;
    }
}


// ═══════════════════════════════════════════════════════════════════════════
section('Caller 1 — unchanged, and shorter');

$v = new CourtesyVehicle('CV-01');
echo "  Available? " . ($v->isAvailable() ? 'yes' : 'no') . "\n";
$v->allocateTo('JC-1001');
echo "  Out on {$v->allocatedToJobCard}. Available? " . ($v->isAvailable() ? 'yes' : 'no') . "\n";
$v->returnToFleet();
echo "  Returned. Available? " . ($v->isAvailable() ? 'yes' : 'no') . "\n";

echo "\n  No service object. The caller states intent; the vehicle decides.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Callers 2 to 5 — the four bypasses, retried');

$bypasses = [
    'Bulk import assigns the field directly' => static function (): void {
        $x = new CourtesyVehicle('CV-02');
        $x->allocated = true;
    },
    'Admin screen releases without clearing the reference' => static function (): void {
        $x = new CourtesyVehicle('CV-04');
        $x->allocateTo('JC-3001');
        $x->allocated = false;
    },
    'Second service implements the rule differently' => static function (): void {
        $x = new CourtesyVehicle('CV-05');
        $x->allocateTo('JC-4001');
        $x->allocatedToJobCard = 'JC-4002';
    },
    'Test factory builds an impossible state' => static function (): void {
        $x = new CourtesyVehicle('CV-06');
        $x->allocated = true;
    },
    'Double allocation through the proper method' => static function (): void {
        $x = new CourtesyVehicle('CV-07');
        $x->allocateTo('JC-5001');
        $x->allocateTo('JC-5002');
    },
];

foreach ($bypasses as $label => $attempt) {
    try {
        $attempt();
        echo "  [!!] {$label}\n       SUCCEEDED — that would be a hole in the model.\n";
    } catch (Throwable $e) {
        echo "  [ok] {$label}\n";
        echo "       " . $e::class . ': ' . $e->getMessage() . "\n";
    }
}

echo "\n  The first four are Errors, not exceptions. That is a stronger\n";
echo "  guarantee than a thrown DomainException: there is no property to\n";
echo "  assign, so the bulk import cannot be written that way at all. The\n";
echo "  developer hits it while typing, not in production.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('The one question, answered');

echo "  Hand a reviewer this object and ask: is it valid?\n\n";

$sample = new CourtesyVehicle('CV-09');
$sample->allocateTo('JC-9001');

printf(
    "    fleetCode=%s  allocated=%s  allocatedToJobCard=%s\n\n",
    $sample->fleetCode,
    var_export($sample->allocated, true),
    var_export($sample->allocatedToJobCard, true)
);

echo "  Yes. Not \"probably\", not \"depends where it came from\" — yes, because\n";
echo "  the only two operations that can change those fields both leave them\n";
echo "  consistent, and there is no third.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('What it cost');

$ledger = [
    ['Classes before', '2  (model + service)'],
    ['Classes after',  '1  (+ 2 exception types)'],
    ['New interfaces', '0'],
    ['New factories',  '0'],
    ['New repositories', '0'],
    ['Callers that must change', 'all of them — and that is the win'],
];

foreach ($ledger as [$label, $value]) {
    printf("  %-26s %s\n", $label, $value);
}

echo "\n  That last row is the honest cost. Every existing caller must be found\n";
echo "  and rewritten, and on a large codebase that is real work — which is why\n";
echo "  this move is cheap at the start of a project and expensive in year\n";
echo "  three. Lesson 5.2 is about doing it to a codebase you cannot stop.\n\n";
echo "  Notice what did NOT appear: no interface, no abstract class, no\n";
echo "  factory, no repository, no aggregate. Those come later, if the rules\n";
echo "  justify them. Moving a rule inward is one small move on its own.\n\n";
