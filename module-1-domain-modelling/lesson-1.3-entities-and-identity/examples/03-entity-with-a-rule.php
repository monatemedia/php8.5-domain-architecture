<?php
declare(strict_types=1);

/**
 * Example 03 — The value knows the value; the entity knows the change
 * ====================================================================
 *
 * INVARIANT I4 (DOMAIN.md): an odometer reading may never decrease for a given
 * vehicle.
 *
 * Read that sentence carefully. It is not a rule about a reading — it is a rule
 * about a SEQUENCE of readings, and a single reading has no sequence. So it
 * cannot live on OdometerReading, and it must live on Vehicle.
 *
 * That split is the pattern for most interesting entity rules, and Lesson 1.4
 * is entirely about telling the two apart.
 *
 *     php 03-entity-with-a-rule.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 70) . "\n  {$title}\n" . str_repeat('=', 70) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// The VALUE knows what a valid reading is.
// ═══════════════════════════════════════════════════════════════════════════

final readonly class OdometerReading
{
    private const int MAX_PLAUSIBLE_KM = 2_000_000;

    private function __construct(public int $kilometres) {}

    public static function of(int $kilometres): self
    {
        if ($kilometres < 0) {
            throw new InvalidArgumentException(
                "An odometer reading cannot be negative; got {$kilometres}."
            );
        }

        // A sanity bound, not a business rule. Its job is to catch a
        // transposed digit at the service desk, not to define what is possible.
        if ($kilometres > self::MAX_PLAUSIBLE_KM) {
            throw new InvalidArgumentException(sprintf(
                'An odometer reading of %s km is not plausible. Check for a typo.',
                number_format($kilometres)
            ));
        }

        return new self($kilometres);
    }

    public function isLessThan(self $other): bool
    {
        return $this->kilometres < $other->kilometres;
    }

    public function equals(self $other): bool
    {
        return $this->kilometres === $other->kilometres;
    }

    public function __toString(): string
    {
        return number_format($this->kilometres) . ' km';
    }
}


// ═══════════════════════════════════════════════════════════════════════════
// The ENTITY knows what a valid CHANGE is.
// ═══════════════════════════════════════════════════════════════════════════

final readonly class VehicleId
{
    public function __construct(public string $value) {}

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

final class Vehicle
{
    /** @var list<OdometerReading> newest last */
    private array $readingHistory;

    public function __construct(
        private readonly VehicleId $id,
        private string             $registration,
        private OdometerReading    $odometer,
    ) {
        $this->readingHistory = [$odometer];
    }

    public function id(): VehicleId
    {
        return $this->id;
    }

    public function registration(): string
    {
        return $this->registration;
    }

    public function odometer(): OdometerReading
    {
        return $this->odometer;
    }

    /**
     * INVARIANT I4. The reading itself is already known to be valid — it is an
     * OdometerReading, so it cannot be negative or absurd. What is being
     * checked here is the TRANSITION.
     */
    public function recordOdometer(OdometerReading $reading): void
    {
        if ($reading->isLessThan($this->odometer)) {
            throw new DomainException(sprintf(
                'Vehicle %s is on %s; %s would be a decrease. An odometer that goes '
                . 'backwards means a replaced instrument cluster or a data-entry error, '
                . 'and both need a human.',
                $this->id,
                $this->odometer,
                $reading
            ));
        }

        $this->odometer          = $reading;
        $this->readingHistory[]  = $reading;
    }

    public function reRegister(string $registration): void
    {
        $this->registration = $registration;
    }

    /** Identity only. */
    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }

    /** @return list<OdometerReading> */
    public function readingHistory(): array
    {
        // A copy. Handing out the internal array would let a caller append to
        // it, and everything above would become decorative. Module 2 has a
        // great deal more to say about this.
        return $this->readingHistory;
    }
}


// ═══════════════════════════════════════════════════════════════════════════
section('The value refuses impossible values');

$valueAttempts = [
    'OdometerReading::of(-1)'         => static fn() => OdometerReading::of(-1),
    'OdometerReading::of(9_000_000)'  => static fn() => OdometerReading::of(9_000_000),
    'OdometerReading::of(0)'          => static fn() => OdometerReading::of(0),
    'OdometerReading::of(84_500)'     => static fn() => OdometerReading::of(84_500),
];

foreach ($valueAttempts as $label => $attempt) {
    try {
        $attempt();
        echo "  [built]   {$label}\n";
    } catch (Throwable $e) {
        echo "  [refused] {$label}\n";
        echo "            " . $e->getMessage() . "\n";
    }
}

echo "\n  Note that 1 km is a perfectly valid reading. Nothing about the VALUE\n";
echo "  1 is wrong.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('The entity refuses impossible changes');

$vehicle = new Vehicle(
    new VehicleId('VEH-4471'),
    'AB12CDE',
    OdometerReading::of(84_500)
);

echo "  Vehicle {$vehicle->id()} is on {$vehicle->odometer()}\n\n";

$changeAttempts = [
    'recordOdometer(1)        — a valid reading, an invalid change' => static fn() => OdometerReading::of(1),
    'recordOdometer(84_499)   — one kilometre backwards'            => static fn() => OdometerReading::of(84_499),
    'recordOdometer(84_500)   — the same reading again'             => static fn() => OdometerReading::of(84_500),
    'recordOdometer(121_400)  — forwards'                           => static fn() => OdometerReading::of(121_400),
];

foreach ($changeAttempts as $label => $makeReading) {
    try {
        $vehicle->recordOdometer($makeReading());
        echo "  [accepted] {$label}\n";
    } catch (DomainException $e) {
        echo "  [refused]  {$label}\n";
    }
}

echo "\n  Vehicle {$vehicle->id()} is now on {$vehicle->odometer()}\n";

echo "\n  The same reading again is ACCEPTED — 'may never decrease' is not\n";
echo "  'must always increase'. A vehicle in for two jobs on the same day has\n";
echo "  not moved, and a model that refused that would send somebody to the\n";
echo "  service desk to invent a number.\n\n";
echo "  Read your invariants literally. The difference between < and <= has\n";
echo "  gone to production more than once.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('The split, stated');

echo "  OdometerReading  knows what a valid READING is\n";
echo "                     not negative, not absurd\n";
echo "                     true of every reading, everywhere, forever\n\n";
echo "  Vehicle          knows what a valid CHANGE is\n";
echo "                     not lower than the last one for THIS vehicle\n";
echo "                     meaningless without a previous reading to compare to\n\n";

echo "  The test: can you decide the rule while holding ONLY this object?\n\n";
echo "    Yes  -> it belongs on this object\n";
echo "    No   -> it belongs on whatever holds the context you need\n\n";

echo "  Applied here: holding an OdometerReading of 1, is it valid? Yes,\n";
echo "  trivially — you need no other information. Holding the same reading,\n";
echo "  is it a valid NEXT reading? Unanswerable without the vehicle.\n\n";

echo "  Lesson 1.4 turns that test into a procedure and applies it to a class\n";
echo "  with checks scattered across four places.\n\n";
