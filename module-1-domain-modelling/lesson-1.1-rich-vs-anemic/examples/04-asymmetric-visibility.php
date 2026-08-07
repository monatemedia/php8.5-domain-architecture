<?php
declare(strict_types=1);

/**
 * Example 04 — The language tools, and their limits
 * ==================================================
 *
 * Four ways to protect state, from weakest to strongest, with what each one
 * actually stops.
 *
 * The reason to know all four is that they are not interchangeable. Entities
 * change over time and cannot be readonly; value objects must be. Picking the
 * wrong one produces either a model that cannot express its own lifecycle, or
 * one that protects nothing.
 *
 *     php 04-asymmetric-visibility.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 70) . "\n  {$title}\n" . str_repeat('=', 70) . "\n\n";
}

/** Run a mutation and report whether the language stopped it. */
function attempt(string $label, callable $fn): void
{
    try {
        $fn();
        echo "  [ allowed ] {$label}\n";
    } catch (Throwable $e) {
        echo "  [ blocked ] {$label}\n";
        echo "              " . $e::class . ': ' . $e->getMessage() . "\n";
    }
}


// ═══════════════════════════════════════════════════════════════════════════
section('1. Public property — protects nothing');

final class V1
{
    public bool $allocated = false;
}

$v1 = new V1();
attempt('$v1->allocated = true', static function () use ($v1): void {
    $v1->allocated = true;
});
echo "\n  Read anywhere, write anywhere. Every invariant is a suggestion.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('2. Private property + getter — the pre-8.4 answer');

final class V2
{
    private bool $allocated = false;

    public function allocate(): void
    {
        $this->allocated = true;
    }

    public function isAllocated(): bool
    {
        return $this->allocated;
    }
}

$v2 = new V2();
attempt('$v2->allocated = true', static function () use ($v2): void {
    /** @phpstan-ignore-next-line — deliberately writing an inaccessible property */
    $v2->allocated = true;
});
$v2->allocate();
echo "  Read via getter: " . var_export($v2->isAllocated(), true) . "\n";
echo "\n  Correct, and it is what you write on PHP 8.3 and below. The cost is\n";
echo "  a getter per property, which is exactly the boilerplate that made\n";
echo "  people reach for public properties in the first place.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('3. Asymmetric visibility (PHP 8.4) — read out, write in');

final class V3
{
    public private(set) bool $allocated = false;

    public function allocate(): void
    {
        $this->allocated = true;
    }
}

$v3 = new V3();
attempt('$v3->allocated = true', static function () use ($v3): void {
    $v3->allocated = true;
});
$v3->allocate();
echo "  Read directly: " . var_export($v3->allocated, true) . "  (no getter needed)\n";
echo "\n  Same guarantee as option 2, none of the boilerplate. For entities,\n";
echo "  this is the default choice from 8.4 onwards.\n";

echo "\n  Also available: protected(set), for state a subclass may change.\n";
echo "  Use it rarely — a domain model with an inheritance hierarchy deep\n";
echo "  enough to need it is usually modelling a database, not a business.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('4. readonly (PHP 8.1) — write once, at construction');

final class V4
{
    public function __construct(public readonly string $fleetCode) {}
}

$v4 = new V4('CV-01');
attempt('$v4->fleetCode = "CV-02"', static function () use ($v4): void {
    $v4->fleetCode = 'CV-02';
});
echo "  Read: {$v4->fleetCode}\n";
echo "\n  For state that is fixed at construction. Note it blocks writes even\n";
echo "  from INSIDE the class after construction — which is why it is wrong\n";
echo "  for anything with a lifecycle.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('5. readonly class (PHP 8.2) — for value objects only');

final readonly class V5
{
    public function __construct(
        public int    $amountMinor,
        public string $currency,
    ) {}

    /**
     * PHP 8.5 clone-with. Note it is a CALL, clone($obj, [...]), not a `with`
     * keyword — there is no `with` keyword in PHP, and material claiming
     * otherwise is describing a proposal rather than the language.
     *
     * This must live on the class: a readonly property can only be replaced by
     * clone-with from inside the declaring class.
     */
    #[\NoDiscard('Returns a new instance — the original is unchanged')]
    public function withAmount(int $amountMinor): static
    {
        return clone($this, ['amountMinor' => $amountMinor]);
    }
}

$v5 = new V5(4500, 'EUR');
attempt('$v5->amountMinor = 9999', static function () use ($v5): void {
    $v5->amountMinor = 9999;
});

$changed = $v5->withAmount(9999);
printf("  Original: %d %s\n", $v5->amountMinor, $v5->currency);
printf("  Copy:     %d %s   (currency carried over automatically)\n", $changed->amountMinor, $changed->currency);

echo "\n  Lesson 1.2 builds this out properly. What matters here is the split:\n";
echo "  readonly class for values, asymmetric visibility for entities.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Choosing');

$table = [
    ['Situation', 'Use'],
    ['Fixed at construction, entity', 'public readonly'],
    ['Changes over the object\'s life', 'public private(set)'],
    ['Value object — never changes', 'final readonly class'],
    ['Needs to be read but not written', 'public private(set)'],
    ['Genuinely public data, no rules (a DTO)', 'public'],
];

foreach ($table as $i => [$situation, $use]) {
    printf("  %-42s %s\n", $situation, $use);
    if ($i === 0) {
        echo '  ' . str_repeat('-', 68) . "\n";
    }
}

echo "\n  The last row is not a trap. A DTO carrying data across a boundary has\n";
echo "  no invariants to protect, and wrapping it in ceremony is the opposite\n";
echo "  error. See section 7 of the README.\n\n";
echo "  NONE of this is what makes a model rich. A class with private(set) on\n";
echo "  every property and an allocate() method that sets them without checking\n";
echo "  anything is an anemic model with better syntax. The language protects\n";
echo "  the rule you wrote; it does not write it.\n\n";
