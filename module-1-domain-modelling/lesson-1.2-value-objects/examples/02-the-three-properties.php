<?php
declare(strict_types=1);

/**
 * Example 02 — The three properties, removed one at a time
 * =========================================================
 *
 * Immutability, structural equality, self-validation. Each is demonstrated by
 * building a version WITHOUT it and running the bug that follows.
 *
 * The bugs are the point. "A value object should be immutable" is a rule you
 * can recite; the running total that silently rewrites its own history is the
 * thing you remember.
 *
 *     php 02-the-three-properties.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 70) . "\n  {$title}\n" . str_repeat('=', 70) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
section('Property 1 — Immutability');

/** Mutable. Everything else about it is fine. */
final class MutableMoney
{
    public function __construct(public int $amountMinor) {}

    public function add(int $amountMinor): void
    {
        $this->amountMinor += $amountMinor;
    }
}

echo "  A running total, snapshotted after each line:\n\n";

$running   = new MutableMoney(0);
$snapshots = [];

foreach ([4500, 3000, 1250] as $line) {
    $running->add($line);
    $snapshots[] = $running;       // ← intending to record the total so far
}

foreach ($snapshots as $i => $snapshot) {
    printf("    after line %d: %d\n", $i + 1, $snapshot->amountMinor);
}

echo "\n  All three are 8750. There is only ONE object, stored three times, and\n";
echo "  every earlier 'snapshot' was rewritten by every later addition.\n\n";
echo "  The symptom in production is historical records that change when new\n";
echo "  ones are added, which is one of the hardest bugs there is to trace,\n";
echo "  because the code that appears to be wrong is correct.\n";

/** Immutable. The same loop, with operations that return new instances. */
final readonly class ImmutableMoney
{
    public function __construct(public int $amountMinor) {}

    public function plus(int $amountMinor): self
    {
        return new self($this->amountMinor + $amountMinor);
    }
}

echo "\n  The same loop, immutable:\n\n";

$runningImmutable = new ImmutableMoney(0);
$snapshotsFixed   = [];

foreach ([4500, 3000, 1250] as $line) {
    $runningImmutable = $runningImmutable->plus($line);
    $snapshotsFixed[] = $runningImmutable;
}

foreach ($snapshotsFixed as $i => $snapshot) {
    printf("    after line %d: %d\n", $i + 1, $snapshot->amountMinor);
}

echo "\n  4500, 7500, 8750. Three objects, none of which can be changed by\n";
echo "  anything that happens later.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Property 2 — Structural equality');

final readonly class Reg
{
    public function __construct(public string $value) {}
}

$a = new Reg('AB12CDE');
$b = new Reg('AB12CDE');

printf("  \$a === \$b   (identity)   %s\n", $a === $b ? 'true' : 'FALSE');
printf("  \$a ==  \$b   (structural) %s\n", $a == $b ? 'true' : 'false');

echo "\n  === is false, and === is what people reach for by habit.\n";
echo "  == happens to work here, which is worse than it failing, because it\n";
echo "  works right up until equality needs to mean something specific.\n";

echo "\n  Where == is not good enough:\n\n";

$typed   = new Reg('ab-12-cde');
$printed = new Reg('AB12CDE');

printf("    new Reg('ab-12-cde') == new Reg('AB12CDE')  ->  %s\n", $typed == $printed ? 'true' : 'FALSE');

echo "\n  Same vehicle. The business says so, the driver says so, and the\n";
echo "  language says no — because == compares the properties you happened to\n";
echo "  store, and nobody got to decide what equality MEANS.\n";

final readonly class NormalisedReg
{
    public string $value;

    public function __construct(string $raw)
    {
        $this->value = strtoupper(str_replace([' ', '-'], '', trim($raw)));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

$n1 = new NormalisedReg('ab-12-cde');
$n2 = new NormalisedReg('  AB 12 CDE  ');

printf("\n    Normalised: '%s' and '%s'\n", $n1->value, $n2->value);
printf("    equals()  ->  %s\n", $n1->equals($n2) ? 'true' : 'false');

echo "\n  An explicit equals() is not boilerplate. It is the one place where you\n";
echo "  get to say what 'the same' means for this concept.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Property 3 — Self-validation');

/** No guards. Everything else is right: immutable, has equals(). */
final readonly class UnguardedMoney
{
    public function __construct(
        public int    $amountMinor,
        public string $currency,
    ) {}

    public function equals(self $other): bool
    {
        return $this->amountMinor === $other->amountMinor
            && $this->currency === $other->currency;
    }
}

$bad = new UnguardedMoney(-9999, '');
printf("  new UnguardedMoney(-9999, '')  ->  amountMinor=%d currency='%s'\n", $bad->amountMinor, $bad->currency);

echo "\n  Constructed, immutable, comparable, and meaningless. Every consumer\n";
echo "  must now check it, which means the checks are duplicated, which means\n";
echo "  one of them will be forgotten.\n";

final readonly class GuardedMoney
{
    private function __construct(
        public int    $amountMinor,
        public string $currency,
    ) {}

    public static function of(int $amountMinor, string $currency): self
    {
        if ($amountMinor < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("'{$currency}' is not an ISO currency code.");
        }
        return new self($amountMinor, $currency);
    }
}

$attempts = [
    'GuardedMoney::of(-9999, "EUR")' => static fn() => GuardedMoney::of(-9999, 'EUR'),
    'GuardedMoney::of(4500, "")'     => static fn() => GuardedMoney::of(4500, ''),
    'GuardedMoney::of(4500, "eur")'  => static fn() => GuardedMoney::of(4500, 'eur'),
    'GuardedMoney::of(4500, "EUR")'  => static fn() => GuardedMoney::of(4500, 'EUR'),
];

echo "\n";
foreach ($attempts as $label => $attempt) {
    try {
        $attempt();
        echo "  [built]   {$label}\n";
    } catch (Throwable $e) {
        echo "  [refused] {$label}\n";
        echo "            " . $e->getMessage() . "\n";
    }
}

echo "\n  Note the private constructor plus a named static factory. It is not\n";
echo "  decoration: making __construct private means there is no way to build\n";
echo "  one that skips of(), including from inside a test or a mapper. If the\n";
echo "  constructor is public, the guards are optional.\n";

echo "\n  A second benefit, which matters more later: you can have several named\n";
echo "  factories with different rules — Money::of(), Money::zero(),\n";
echo "  Money::fromDecimalString() — where a constructor can only have one\n";
echo "  signature.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('All three, or it is not a value object');

$matrix = [
    ['Missing',              'What you get'],
    ['Immutability',         'History that rewrites itself. Invariants that hold only at construction.'],
    ['Structural equality',  'Two equal values that compare unequal. Broken lookups, broken keys.'],
    ['Self-validation',      'Invalid instances, re-checked everywhere, and one place that forgot.'],
];

foreach ($matrix as $i => [$missing, $result]) {
    printf("  %-22s %s\n", $missing, $result);
    if ($i === 0) {
        echo '  ' . str_repeat('-', 68) . "\n";
    }
}

echo "\n  A class with two of the three is a struct with a nice name.\n\n";
