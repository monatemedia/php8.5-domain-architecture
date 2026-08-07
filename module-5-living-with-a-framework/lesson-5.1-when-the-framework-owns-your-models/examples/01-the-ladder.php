<?php
declare(strict_types=1);

/**
 * Example 01 — Five rungs, and what each one costs
 * =================================================
 *
 * One model, improved four times. After each step: what is now impossible that
 * was possible before, and what it cost to get there.
 *
 *     php 01-the-ladder.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

function attempt(string $label, callable $fn): void
{
    try {
        $fn();
        printf("      %-46s allowed\n", $label);
    } catch (Throwable $e) {
        printf("      %-46s REFUSED (%s)\n", $label, (new ReflectionClass($e))->getShortName());
    }
}

/** The miniature ORM, as in challenge/given/. */
abstract class Record
{
    public function __construct(protected array $attributes = []) {}

    public function __get(string $n): mixed { return $this->attributes[$n] ?? null; }

    public function __set(string $n, mixed $v): void { $this->attributes[$n] = $v; }

    public function toRow(): array { return $this->attributes; }
}

function aRow(string $status = 'booked'): array
{
    return ['id' => 'JC-1042', 'status' => $status, 'total_cents' => 24500, 'currency' => 'EUR'];
}


// ═══════════════════════════════════════════════════════════════════════════
section('Rung 0 — where you found it');

final class JobCardV0 extends Record {}

echo "  The model is a row. Every rule lives in a service, and every service\n";
echo "  is optional.\n\n";

$v0 = new JobCardV0(aRow());

attempt('$r->status = "invoiced" (from Booked)', static function () use ($v0): void {
    $v0->status = 'invoiced';
});
attempt('$r->total_cents = -1', static function () use ($v0): void {
    $v0->total_cents = -1;
});
attempt('$r->currency = "GBP", leaving total_cents alone', static function () use ($v0): void {
    $v0->currency = 'GBP';
});

printf("\n      resulting row: %s\n", json_encode($v0->toRow()));
echo "\n  A job card that went straight from Booked to Invoiced, priced at minus\n";
echo "  one cent, in a currency nobody converted. Every one of those is a\n";
echo "  single assignment somebody wrote in a hurry.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Rung 1 — named methods. Cost: free.');

final class JobCardV1 extends Record
{
    public function invoice(): void
    {
        $this->attributes['status'] = 'invoiced';
    }
}

echo "  What changed: there is now a place for a rule to live, and the call\n";
echo "  sites are greppable. Nothing is enforced yet.\n\n";

$v1 = new JobCardV1(aRow());
attempt('$r->invoice()', static fn() => $v1->invoice());
attempt('$r->status = "invoiced"  (still open)', static function () use ($v1): void {
    $v1->status = 'invoiced';
});

echo "\n  grep -rn '->invoice()'    finds every place a job card is billed\n";
echo "  grep -rn \"status = \"      finds nothing useful, ever\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Rung 2 — the invariant moves in. Cost: cheap.');

final class JobCardV2 extends Record
{
    private const array ALLOWED = [
        'booked'      => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed'   => ['invoiced'],
        'invoiced'    => [],
        'cancelled'   => [],
    ];

    private const array GUARDED = ['status'];

    public function __set(string $n, mixed $v): void
    {
        if (in_array($n, self::GUARDED, true)) {
            throw new LogicException("{$n} is guarded.");
        }

        parent::__set($n, $v);
    }

    public function invoice(): void
    {
        $this->transitionTo('invoiced');
    }

    private function transitionTo(string $next): void
    {
        $current = (string) $this->attributes['status'];

        if (!in_array($next, self::ALLOWED[$current] ?? [], true)) {
            throw new DomainException("A job card that is {$current} cannot become {$next}.");
        }

        $this->attributes['status'] = $next;
    }
}

$v2 = new JobCardV2(aRow());
attempt('$r->invoice() from Booked', static fn() => $v2->invoice());
attempt('$r->status = "invoiced"', static function () use ($v2): void {
    $v2->status = 'invoiced';
});
attempt('$r->customer_note = "..."  (unguarded)', static function () use ($v2): void {
    $v2->customer_note = 'Ring after 5';
});

echo "\n  Both routes to an illegal status are now closed, and the columns\n";
echo "  nobody is protecting are as convenient as they were.\n\n";
echo "  Note the honest limit: `__set` is a RUNTIME check. PHP will not tell\n";
echo "  you at compile time, because a magic setter is magic. That cost does\n";
echo "  not go away until rung 5.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Rung 3 — value objects over the same columns. Cost: cheap.');

final class Cents
{
    private function __construct(public readonly int $amount, public readonly string $currency) {}

    public static function of(int $amount, string $currency): self
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }

        return new self($amount, $currency);
    }

    public function plus(self $other): self
    {
        if ($other->currency !== $this->currency) {
            throw new DomainException('Cannot add different currencies.');
        }

        return new self($this->amount + $other->amount, $this->currency);
    }
}

final class JobCardV3 extends Record
{
    private const array GUARDED = ['status', 'total_cents', 'currency'];

    public function __set(string $n, mixed $v): void
    {
        if (in_array($n, self::GUARDED, true)) {
            throw new LogicException("{$n} is guarded.");
        }

        parent::__set($n, $v);
    }

    public function total(): Cents
    {
        return Cents::of((int) $this->attributes['total_cents'], (string) $this->attributes['currency']);
    }

    public function priceAt(Cents $total): void
    {
        $this->attributes['total_cents'] = $total->amount;
        $this->attributes['currency']    = $total->currency;
    }
}

$v3 = new JobCardV3(aRow());
attempt('$r->priceAt(Cents::of(-1, "EUR"))', static fn() => $v3->priceAt(Cents::of(-1, 'EUR')));
attempt('$r->currency = "GBP"  (half a value)', static function () use ($v3): void {
    $v3->currency = 'GBP';
});
attempt('$r->total()->plus(Cents::of(100, "GBP"))', static fn() => $v3->total()->plus(Cents::of(100, 'GBP')));
attempt('$r->priceAt(Cents::of(31000, "GBP"))', static fn() => $v3->priceAt(Cents::of(31000, 'GBP')));

printf("\n      resulting row: %s\n", json_encode($v3->toRow()));

echo "\n  THE SCHEMA DID NOT MOVE. total_cents is still an integer column and\n";
echo "  currency is still three characters, so the SQL report somebody wrote\n";
echo "  last year still runs. What changed is that no PHP caller adds money\n";
echo "  with + any more.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('The ladder, priced');

$rows = [
    ['0', 'a row with public setters',        '-',          'nothing'],
    ['1', 'named methods',                    'free',       'a name, and a place for a rule'],
    ['2', 'invariants in the model',          'cheap',      'the rule is unavoidable'],
    ['3', 'value objects over columns',       'cheap',      'no floats, no half-written values'],
    ['4', 'typed exceptions + boundary',      'cheap',      '409 instead of 500'],
    ['5', 'separate domain layer + mapper',   'EXPENSIVE',  'testable with no database'],
];

printf("  %-4s %-34s %-12s %s\n", 'rung', 'what', 'cost', 'buys');
printf("  %s\n", str_repeat('-', 96));

foreach ($rows as [$n, $what, $cost, $buys]) {
    printf("  %-4s %-34s %-12s %s\n", $n, $what, $cost, $buys);
}

echo "\n  Rungs 1 to 4 need no schema change, no migration and no rewrite, and\n";
echo "  between them they capture most of what Modules 1, 2 and 4 are for.\n\n";
echo "  Rung 4 is the strange one: it costs nothing at all, because an\n";
echo "  exception tree and a boundary mapper have no opinion about\n";
echo "  persistence. Section 5 of the README, and the challenge asserts it.\n\n";
