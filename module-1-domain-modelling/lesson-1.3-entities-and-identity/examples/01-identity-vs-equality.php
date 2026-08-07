<?php
declare(strict_types=1);

/**
 * Example 01 — Identity versus structural equality
 * =================================================
 *
 * Two objects with identical attributes. One pair is the same thing, one pair
 * is two things. The difference is not in the attributes.
 *
 *     php 01-identity-vs-equality.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 70) . "\n  {$title}\n" . str_repeat('=', 70) . "\n\n";
}

function yn(bool $b): string
{
    return $b ? 'yes' : 'no';
}


// ═══════════════════════════════════════════════════════════════════════════
// A value object: defined entirely by its attributes.
// ═══════════════════════════════════════════════════════════════════════════

final readonly class Amount
{
    public function __construct(public int $minor) {}

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor;
    }
}


// ═══════════════════════════════════════════════════════════════════════════
// An entity: defined by identity, which survives every attribute changing.
// ═══════════════════════════════════════════════════════════════════════════

final readonly class Id
{
    public function __construct(public string $value) {}

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

final class Car
{
    public function __construct(
        private readonly Id $id,
        private string      $registration,
        private string      $colour,
        private int         $odometer,
    ) {}

    public function id(): Id
    {
        return $this->id;
    }

    public function reRegister(string $registration): void
    {
        $this->registration = $registration;
    }

    public function respray(string $colour): void
    {
        $this->colour = $colour;
    }

    public function recordOdometer(int $odometer): void
    {
        $this->odometer = $odometer;
    }

    /**
     * IDENTITY ONLY. Not the registration, not the colour, not the mileage.
     *
     * An equals() that also compared attributes would report that a vehicle
     * stopped being itself the moment somebody corrected a typo in its colour.
     */
    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }

    public function describe(): string
    {
        return sprintf(
            '%s  %s, %s, %s km',
            $this->id->value,
            $this->registration,
            $this->colour,
            number_format($this->odometer)
        );
    }
}


// ═══════════════════════════════════════════════════════════════════════════
section('Values: identical attributes means the same value');

$a = new Amount(4500);
$b = new Amount(4500);

echo "  Amount(4500) and Amount(4500)\n";
echo "    equals()          " . yn($a->equals($b)) . "\n";
echo "    same instance     " . yn($a === $b) . "\n";

echo "\n  Two objects, one value. It makes no sense to ask WHICH 45.00 this is,\n";
echo "  which is exactly why swapping one for an equal one is always safe.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Entities: identical attributes means two things');

$one = new Car(new Id('VEH-001'), 'AB12CDE', 'blue', 84_500);
$two = new Car(new Id('VEH-002'), 'AB12CDE', 'blue', 84_500);

echo "  " . $one->describe() . "\n";
echo "  " . $two->describe() . "\n\n";
echo "    equals()          " . yn($one->equals($two)) . "\n";

echo "\n  Every attribute matches. They are two vehicles, and the model says so,\n";
echo "  because the model compares identity and nothing else.\n";
echo "\n  (In reality, two vehicles with the same registration means somebody\n";
echo "  has entered one twice — but noticing that is a different job from\n";
echo "  deciding whether these two objects are the same object.)\n";


// ═══════════════════════════════════════════════════════════════════════════
section('And the other direction: every attribute changes');

$car    = new Car(new Id('VEH-003'), 'AB12CDE', 'blue', 84_500);
$before = $car->describe();

$car->reRegister('XY98ZAB');
$car->respray('silver');
$car->recordOdometer(121_400);

echo "  before:  {$before}\n";
echo "  after:   " . $car->describe() . "\n\n";
echo "    still the same vehicle?  " . yn($car->equals($car)) . "\n";

echo "\n  Nothing about it is what it was. It is the same vehicle, and every\n";
echo "  reference to it elsewhere in the system is still correct.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('What happens if you get equals() wrong');

/** An entity with a value object's equality bolted on. */
final class BadCar
{
    public function __construct(
        private readonly string $id,
        private string          $colour,
    ) {}

    public function respray(string $colour): void
    {
        $this->colour = $colour;
    }

    /** Compares attributes as well as identity. This is the bug. */
    public function equals(self $other): bool
    {
        return $this->id === $other->id
            && $this->colour === $other->colour;
    }
}

$loaded    = new BadCar('VEH-004', 'blue');
$inSession = new BadCar('VEH-004', 'blue');

echo "  Same vehicle loaded twice:      " . yn($loaded->equals($inSession)) . "\n";

$inSession->respray('silver');

echo "  ...then one copy is resprayed:  " . yn($loaded->equals($inSession)) . "\n";

echo "\n  The same vehicle is now not equal to itself, because somebody changed\n";
echo "  an attribute. Downstream this shows as duplicate rows, a cache that\n";
echo "  never hits, an in_array() that stops finding things, and a repository\n";
echo "  that saves two vehicles where there was one.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('The question that decides it');

echo "  Does it make sense to say \"this one, specifically\"?\n\n";

$candidates = [
    'A vehicle'                => 'yes — that one, in bay 3, we had it in last March',
    'Five hundred euros'       => 'no  — any five hundred euros will do',
    'A job card'               => 'yes — JC-1001, the one we are arguing about',
    'A registration number'    => 'no  — AB12CDE is AB12CDE',
    'A courtesy vehicle'       => 'yes — CV-01, the one that is out',
    'A labour charge of 0.5h'  => 'no  — half an hour is half an hour',
];

foreach ($candidates as $thing => $answer) {
    printf("    %-26s %s\n", $thing, $answer);
}

echo "\n  Yes -> entity, needs identity. No -> value object, defined by its\n";
echo "  attributes.\n\n";
echo "  Careful with the last two: a courtesy vehicle IS a vehicle, so it is an\n";
echo "  entity; the labour charge on a job card is a line, which is also an\n";
echo "  entity, but the QUANTITY on that line is a value. The question applies\n";
echo "  to the concept you are modelling, not to the sentence it appears in.\n\n";
