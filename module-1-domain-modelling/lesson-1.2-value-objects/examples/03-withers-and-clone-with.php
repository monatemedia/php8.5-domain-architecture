<?php
declare(strict_types=1);

/**
 * Example 03 — Withers, clone with, and #[\NoDiscard]
 * ====================================================
 *
 * Immutability means "change" produces a new instance. This is how you write
 * that in PHP 8.5, and what goes wrong in each of the older ways.
 *
 * REQUIRES PHP 8.5. clone($obj, [...]) and #[\NoDiscard] do not parse below it.
 *
 *     php 03-withers-and-clone-with.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 70) . "\n  {$title}\n" . str_repeat('=', 70) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
section('1. The manual wither — correct, and fragile');

final readonly class MoneyV1
{
    public function __construct(
        public int    $amountMinor,
        public string $currency,
        public string $rounding = 'half-up',
    ) {}

    public function withAmount(int $amountMinor): self
    {
        // Every property, listed by hand. Add a fourth and every wither in the
        // class must be updated — and the compiler will not remind you, because
        // a forgotten property just silently reverts to its default.
        return new self($amountMinor, $this->currency, $this->rounding);
    }
}

$v1 = new MoneyV1(4500, 'EUR', 'bankers');
$v1b = $v1->withAmount(9999);
printf("  original: %d %s (%s)\n", $v1->amountMinor, $v1->currency, $v1->rounding);
printf("  copy:     %d %s (%s)\n", $v1b->amountMinor, $v1b->currency, $v1b->rounding);

echo "\n  Correct today. The failure mode is a future edit: somebody adds a\n";
echo "  fourth property, updates two of the three withers, and the third one\n";
echo "  silently resets it to its default on every call.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('2. PHP 8.5 clone with');

final readonly class MoneyV2
{
    public function __construct(
        public int    $amountMinor,
        public string $currency,
        public string $rounding = 'half-up',
    ) {}

    /**
     * Only the CHANGED property appears. Everything else is carried over, so
     * adding a fourth property requires no change here at all.
     *
     * Note this is a CALL — clone($this, [...]) — not a `with` keyword. There
     * is no `with` keyword in PHP.
     */
    #[\NoDiscard('Returns a new Money — the original is unchanged')]
    public function withAmount(int $amountMinor): self
    {
        return clone($this, ['amountMinor' => $amountMinor]);
    }

    #[\NoDiscard('Returns a new Money — the original is unchanged')]
    public function withCurrency(string $currency): self
    {
        return clone($this, ['currency' => $currency]);
    }
}

$v2  = new MoneyV2(4500, 'EUR', 'bankers');
$v2b = $v2->withAmount(9999);
$v2c = $v2->withCurrency('GBP');

printf("  original: %d %s (%s)\n", $v2->amountMinor, $v2->currency, $v2->rounding);
printf("  amount:   %d %s (%s)\n", $v2b->amountMinor, $v2b->currency, $v2b->rounding);
printf("  currency: %d %s (%s)\n", $v2c->amountMinor, $v2c->currency, $v2c->rounding);

echo "\n  'bankers' carried through both withers without being mentioned in\n";
echo "  either of them.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('3. Why the wither must live INSIDE the class');

echo "  A readonly property can only be replaced by clone-with from inside the\n";
echo "  declaring class. From outside:\n\n";

try {
    $outside = clone($v2, ['amountMinor' => 1]);
    printf("  [!!] Succeeded: %d — that would defeat readonly entirely.\n", $outside->amountMinor);
} catch (Throwable $e) {
    echo "  [ok] " . $e::class . ': ' . $e->getMessage() . "\n";
}

echo "\n  Which is exactly right, and it is why every wither in this course is a\n";
echo "  method on the class rather than a helper function beside it.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('4. The bug #[\\NoDiscard] exists to catch');

echo "  The characteristic immutability mistake:\n\n";
echo "      \$price->withAmount(9999);     // does nothing\n\n";
echo "  Correct code that achieves nothing. It reads exactly like the mutable\n";
echo "  version somebody was used to writing, and it passes review because\n";
echo "  there is nothing visibly wrong with it.\n\n";

$price = new MoneyV2(4500, 'EUR');

echo "  Discarding the result now (watch for a warning above the next line):\n\n";
$price->withAmount(9999);

printf("\n  \$price is still %d. The call did exactly what it said and nothing\n", $price->amountMinor);
echo "  happened, because the new object was thrown away.\n\n";
echo "  #[\\NoDiscard] makes PHP say so. Without it, this is a silent no-op\n";
echo "  that can survive for years.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('5. clone with and property hooks');

/**
 * Property hooks are PHP 8.4. The `set` hook runs on the value supplied by
 * clone-with, so normalisation and validation still apply to the copy — a
 * wither cannot be used to smuggle a bad value past the rules.
 */
final class RegistrationV3
{
    public string $value {
        set(string $raw) => self::normalise($raw);
    }

    public function __construct(string $raw)
    {
        $this->value = $raw;      // the hook runs here too
    }

    private static function normalise(string $raw): string
    {
        $normalised = strtoupper(str_replace([' ', '-'], '', trim($raw)));

        if ($normalised === '') {
            throw new InvalidArgumentException('A registration cannot be empty.');
        }

        return $normalised;
    }
}

$reg = new RegistrationV3('  ab-12-cde ');
printf("  Constructed from '  ab-12-cde ' -> '%s'\n", $reg->value);

$copy = clone($reg, ['value' => ' xy 98 zab ']);
printf("  clone-with ' xy 98 zab '        -> '%s'\n", $copy->value);

echo "\n  The hook ran on the cloned value. Try to smuggle an empty one:\n\n";
try {
    clone($reg, ['value' => '   ']);
    echo "  [!!] Accepted. The hook was bypassed.\n";
} catch (Throwable $e) {
    echo "  [ok] " . $e::class . ': ' . $e->getMessage() . "\n";
}

echo "\n  Note this class is NOT readonly — property hooks and readonly do not\n";
echo "  combine, because a set hook is a write. That is a real trade-off: hooks\n";
echo "  give you normalisation on every assignment, readonly gives you a\n";
echo "  guarantee that there is only one. The challenge uses readonly plus a\n";
echo "  static factory, which gets both.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Summary');

$rows = [
    ['Approach', 'Use when'],
    ['new self(...) by hand', 'PHP 8.4 or below, or very few properties'],
    ['clone($this, [...])', 'PHP 8.5. Default choice for withers.'],
    ['#[\\NoDiscard]', 'On every wither, always'],
    ['Property hooks (set)', 'Normalisation on assignment, when readonly is not wanted'],
    ['readonly + static factory', 'Value objects. What the challenge uses.'],
];

foreach ($rows as $i => [$approach, $when]) {
    printf("  %-28s %s\n", $approach, $when);
    if ($i === 0) {
        echo '  ' . str_repeat('-', 68) . "\n";
    }
}

echo "\n";
