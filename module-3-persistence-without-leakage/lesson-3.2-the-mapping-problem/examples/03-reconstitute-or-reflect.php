<?php
declare(strict_types=1);

/**
 * Example 03 — Two answers, two costs
 * ====================================
 *
 * Neither is free. This runs both, then breaks both in the way each is
 * uniquely vulnerable to.
 *
 *     php 03-reconstitute-or-reflect.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// A: a named factory in the domain.
// ═══════════════════════════════════════════════════════════════════════════

final class WithFactory
{
    private string $status = 'booked';
    private int    $total  = 0;

    private function __construct(public readonly string $reference) {}

    public static function open(string $reference): self
    {
        return new self($reference);
    }

    /** The hole, with a name on it. */
    public static function reconstitute(string $reference, string $status, int $total): self
    {
        $object = new self($reference);
        $object->status = $status;
        $object->total  = $total;
        return $object;
    }

    public function status(): string { return $this->status; }
    public function total(): int     { return $this->total; }
}


// ═══════════════════════════════════════════════════════════════════════════
// B: pristine domain, reflection in the mapper.
// ═══════════════════════════════════════════════════════════════════════════

final class Pristine
{
    private string $reference;
    private string $status = 'booked';
    private int    $total  = 0;

    private function __construct(string $reference)
    {
        $this->reference = $reference;
    }

    public static function open(string $reference): self
    {
        return new self($reference);
    }

    public function reference(): string { return $this->reference; }
    public function status(): string    { return $this->status; }
    public function total(): int        { return $this->total; }
}

/** INFRASTRUCTURE. It knows the private property names, which is the trade. */
final class ReflectingMapper
{
    /** @param array<string, mixed> $row */
    public function fromRow(array $row): Pristine
    {
        $reflection = new ReflectionClass(Pristine::class);

        /** @var Pristine $object */
        $object = $reflection->newInstanceWithoutConstructor();

        // EVERY property has to be written by hand, including ones with
        // defaults, because newInstanceWithoutConstructor() skips property
        // initialisers as well as the constructor body.
        foreach (['reference' => 'reference', 'status' => 'status', 'total' => 'total'] as $property => $column) {
            $reflection->getProperty($property)->setValue($object, $row[$column]);
        }

        return $object;
    }
}


// ═══════════════════════════════════════════════════════════════════════════
section('Both work');

$row = ['reference' => 'JC-1001', 'status' => 'invoiced', 'total' => 12700];

$a = WithFactory::reconstitute($row['reference'], $row['status'], $row['total']);
$b = (new ReflectingMapper())->fromRow($row);

printf("  named factory   %s  %s  %d\n", $a->reference, $a->status(), $a->total());
printf("  reflection      %s  %s  %d\n", $b->reference(), $b->status(), $b->total());

echo "\n  Identical results. The difference is entirely in what each one is\n";
echo "  vulnerable to.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('How the named factory breaks');

echo "  It is public, and PHP has no friend classes. Application code can call\n";
echo "  it, and nothing objects:\n\n";

$nonsense = WithFactory::reconstitute('JC-9999', 'invoiced', 0);
printf("    WithFactory::reconstitute('JC-9999', 'invoiced', 0)\n");
printf("      -> an invoiced job card with a zero total. No exception.\n");

echo "\n  Nothing in the language can prevent that. What is available:\n\n";
echo "    - a name that says what it is for\n";
echo "    - a doc block saying who may call it\n";
echo "    - a review that asks why anything but a mapper is calling it\n\n";
echo "  That is genuinely the whole list.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('How reflection breaks');

echo "  It depends on PRIVATE PROPERTY NAMES — the most private thing a class\n";
echo "  has. Somebody renames one, which every IDE will do silently:\n\n";

$renamed = new class {
    private string $currentStatus = 'booked';   // was $status
};

try {
    (new ReflectionClass($renamed::class))->getProperty('status');
    echo "  [!!] still found it\n";
} catch (ReflectionException $e) {
    echo "  [breaks] " . $e->getMessage() . "\n";
}

echo "\n  Note WHEN that happens: not at compile time, not in the IDE, not in a\n";
echo "  static analyser that only sees the domain. At runtime, on load, in\n";
echo "  production — because the coupling is a string, and nothing declares it.\n";

echo "\n  It also needs newInstanceWithoutConstructor(), which skips property\n";
echo "  initialisers, so any property with a default has to be set by hand too.\n";
echo "  Miss one and you get an uninitialised typed property error somewhere\n";
echo "  else entirely.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Choosing');

$table = [
    ['', 'Named factory', 'Reflection'],
    ['Domain stays clean',     'no — a public hole', 'yes'],
    ['Mapper coupled to',      'a public signature', 'private property names'],
    ['Breaks when',            'somebody misuses it', 'somebody renames a property'],
    ['Found by',               'code review',        'production, on load'],
    ['Speed',                  'ordinary',           'slower'],
    ['Refactoring safety',     'the IDE renames it', 'the IDE breaks it silently'],
];

foreach ($table as $i => [$row0, $row1, $row2]) {
    printf("  %-22s %-22s %s\n", $row0, $row1, $row2);
    if ($i === 0) { echo '  ' . str_repeat('-', 74) . "\n"; }
}

echo "\n  This course takes the named factory, for the row fourth from the\n";
echo "  bottom: a hole with a name on it is found by review, and a coupling\n";
echo "  nothing declares is found by an incident.\n\n";
echo "  The other answer is defensible — it is what Doctrine does, and it is\n";
echo "  why Doctrine can hydrate entities with private constructors. If a\n";
echo "  mature ORM is already doing the reflection, let it.\n\n";
echo "  What is NOT defensible is the third option nobody lists: public\n";
echo "  setters. That undoes every invariant in the model permanently, for\n";
echo "  every caller, forever — and it is what most codebases actually do.\n\n";
