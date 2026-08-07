<?php
declare(strict_types=1);

/**
 * Example 03 — Relocating scattered checks
 * =========================================
 *
 * A labour line whose rules are enforced in four places, none of which is the
 * object itself. Then the same thing with each check moved to the place that
 * owns it.
 *
 * Count the checks before and after. The number goes DOWN, which is the part
 * people do not expect: "validate everywhere" is not more thorough, it is the
 * same rule copied until the copies disagree.
 *
 *     php 03-relocating-the-checks.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// BEFORE
// ═══════════════════════════════════════════════════════════════════════════
section('BEFORE — nine checks in four places, and two of them disagree');

/** Anemic. Knows nothing about itself. */
final class BeforeLabourLine
{
    public string $description  = '';
    public int    $minutes      = 0;
    public int    $rateMinor    = 0;
    public int    $totalMinor   = 0;
}

/** Check 1, 2, 3 — at the boundary. */
function beforeController(array $request): BeforeLabourLine
{
    if (trim((string) ($request['description'] ?? '')) === '') {
        throw new InvalidArgumentException('Description is required.');
    }
    if ((int) ($request['minutes'] ?? 0) < 0) {
        throw new InvalidArgumentException('Minutes cannot be negative.');
    }
    if ((int) ($request['rate'] ?? 0) < 0) {
        throw new InvalidArgumentException('Rate cannot be negative.');
    }

    $line              = new BeforeLabourLine();
    $line->description = trim((string) $request['description']);
    $line->minutes     = (int) $request['minutes'];
    $line->rateMinor   = (int) $request['rate'];

    return $line;
}

/** Checks 4, 5, 6, 7 — in the service, and check 6 disagrees with the boundary. */
final class BeforeLabourService
{
    public function price(BeforeLabourLine $line): void
    {
        if ($line->description === '') {
            throw new InvalidArgumentException('Description is required.');       // 4 — duplicate
        }
        if ($line->minutes < 0) {
            throw new InvalidArgumentException('Minutes cannot be negative.');    // 5 — duplicate
        }
        if ($line->rateMinor <= 0) {
            throw new InvalidArgumentException('Rate must be positive.');         // 6 — DISAGREES
        }

        $tenths = intdiv($line->minutes + 5, 6);
        if ($tenths < 5) {
            $tenths = 5;                                                          // 7 — the minimum
        }

        $line->totalMinor = $tenths * $line->rateMinor;
    }
}

/** Checks 8, 9 — in the report, because nobody trusts the data. */
function beforeRender(BeforeLabourLine $line): string
{
    if ($line->totalMinor < 0) {
        return '[invalid line]';                                                  // 8
    }
    if ($line->description === '') {
        return '[unnamed line]';                                                  // 9
    }
    return sprintf('%s: %d', $line->description, $line->totalMinor);
}

$service = new BeforeLabourService();

echo "  Through the whole chain:\n\n";
$ok = beforeController(['description' => 'Diagnostic', 'minutes' => 27, 'rate' => 900]);
$service->price($ok);
echo "    " . beforeRender($ok) . "\n";

echo "\n  Now the disagreement between check 3 and check 6. A goodwill line at\n";
echo "  a rate of zero — the workshop does this for regular customers:\n\n";

$goodwill = beforeController(['description' => 'Goodwill check-over', 'minutes' => 27, 'rate' => 0]);
echo "    Boundary (check 3, rate >= 0):  accepted\n";
try {
    $service->price($goodwill);
    echo "    Service  (check 6, rate > 0):   accepted\n";
} catch (InvalidArgumentException $e) {
    echo "    Service  (check 6, rate > 0):   REFUSED — " . $e->getMessage() . "\n";
}

echo "\n  Two checks for one rule, and they do not agree. Which is right is now\n";
echo "  a question about the business, and it will be asked during an incident\n";
echo "  by somebody who has to read all nine checks to find the two that\n";
echo "  matter.\n";

echo "\n  And the object is still constructible in nonsense:\n\n";
$nonsense = new BeforeLabourLine();
$nonsense->minutes    = -60;
$nonsense->rateMinor  = -900;
$nonsense->totalMinor = 54000;
printf(
    "    description='%s'  minutes=%d  rate=%d  total=%d\n",
    $nonsense->description,
    $nonsense->minutes,
    $nonsense->rateMinor,
    $nonsense->totalMinor
);
echo "\n  Nine checks, and none of them ran.\n";


// ═══════════════════════════════════════════════════════════════════════════
// AFTER
// ═══════════════════════════════════════════════════════════════════════════
section('AFTER — four checks, each in the only place it can be reached');

/** The value knows what a valid quantity of labour is. */
final readonly class AfterLabourUnits
{
    private const int MINIMUM_TENTHS = 5;

    private function __construct(public int $tenths) {}

    public static function fromMinutes(int $minutes): self
    {
        if ($minutes < 0) {                                                       // check 1
            throw new InvalidArgumentException("Minutes cannot be negative; got {$minutes}.");
        }

        // Rounded UP: intdiv($m + 5, 6) is ceil($m / 6) with no float involved.
        $tenths = intdiv($minutes + 5, 6);

        return new self(max($tenths, self::MINIMUM_TENTHS));
    }

    public function __toString(): string
    {
        return sprintf('%d.%d h', intdiv($this->tenths, 10), $this->tenths % 10);
    }
}

/** The value knows what a valid amount of money is. */
final readonly class AfterMoney
{
    private function __construct(public int $minor) {}

    public static function of(int $minor): self
    {
        if ($minor < 0) {                                                         // check 2
            throw new InvalidArgumentException("Money cannot be negative; got {$minor}.");
        }
        return new self($minor);
    }

    public function times(int $multiplier): self
    {
        return new self($this->minor * $multiplier);
    }

    public function __toString(): string
    {
        return sprintf('%d.%02d', intdiv($this->minor, 100), $this->minor % 100);
    }
}

/** The line knows what a valid line is, and computes rather than stores. */
final readonly class AfterLabourLine
{
    private function __construct(
        public string           $description,
        public AfterLabourUnits $units,
        public AfterMoney       $ratePerTenth,
    ) {}

    public static function create(string $description, AfterLabourUnits $units, AfterMoney $ratePerTenth): self
    {
        $description = trim($description);

        if ($description === '') {                                                // check 3
            throw new InvalidArgumentException('A labour line needs a description.');
        }

        return new self($description, $units, $ratePerTenth);
    }

    /**
     * DERIVED, not stored. There is no moment at which the total disagrees with
     * the parts, because there is no stored total to disagree.
     *
     * Check 4 is the absence of a check: nothing can make this negative,
     * because AfterMoney refuses negatives and AfterLabourUnits refuses
     * negative minutes.
     */
    public function total(): AfterMoney
    {
        return $this->ratePerTenth->times($this->units->tenths);
    }

    public function render(): string
    {
        return sprintf('%s: %s at %s = %s', $this->description, $this->units, $this->ratePerTenth, $this->total());
    }
}

$line = AfterLabourLine::create(
    'Diagnostic',
    AfterLabourUnits::fromMinutes(27),
    AfterMoney::of(150)
);

echo "  " . $line->render() . "\n";

echo "\n  The goodwill line, at a rate of zero:\n\n";
$goodwillAfter = AfterLabourLine::create(
    'Goodwill check-over',
    AfterLabourUnits::fromMinutes(27),
    AfterMoney::of(0)
);
echo "  " . $goodwillAfter->render() . "\n";

echo "\n  Accepted, once, unambiguously. There is exactly one place that decides\n";
echo "  whether a zero rate is allowed, and it says yes.\n";

echo "\n  The five-tenth minimum, applied:\n\n";
foreach ([0, 6, 27, 30, 31, 60] as $minutes) {
    printf("    %3d minutes -> %s\n", $minutes, AfterLabourUnits::fromMinutes($minutes));
}

echo "\n  And the attacks:\n\n";
$attacks = [
    'Negative minutes'                => static fn() => AfterLabourUnits::fromMinutes(-60),
    'Negative rate'                   => static fn() => AfterMoney::of(-900),
    'Empty description'               => static fn() => AfterLabourLine::create('', AfterLabourUnits::fromMinutes(27), AfterMoney::of(150)),
    'Whitespace description'          => static fn() => AfterLabourLine::create('   ', AfterLabourUnits::fromMinutes(27), AfterMoney::of(150)),
    'Change the total after the fact' => static function () use ($line): void {
        $line->description = 'tampered';
    },
];

foreach ($attacks as $label => $attack) {
    try {
        $attack();
        echo "  [!!] {$label} — succeeded.\n";
    } catch (Throwable $e) {
        echo "  [ok] {$label}  (" . $e::class . ")\n";
    }
}


// ═══════════════════════════════════════════════════════════════════════════
section('The count');

printf("  %-38s %s\n", 'Checks before', '9, in 4 places, 2 of them disagreeing');
printf("  %-38s %s\n", 'Checks after', '3, in 3 places, each the only way in');
printf("  %-38s %s\n", 'Stored values that can disagree', 'before: 1 (total)   after: 0');
printf("  %-38s %s\n", 'Ways to construct an invalid line', 'before: many   after: none');

echo "\n  The check count went DOWN. That is the part people do not expect:\n";
echo "  'validate everywhere' is not more thorough, it is the same rule copied\n";
echo "  until the copies disagree — and then it is less thorough, because\n";
echo "  nobody can tell which copy is the rule.\n\n";
echo "  Note also what disappeared entirely: the stored total. A value derived\n";
echo "  on demand cannot drift from its parts, so the invariant 'the total\n";
echo "  matches the line' needs no guard at all. The cheapest invariant is one\n";
echo "  that cannot be expressed falsely.\n\n";
echo "  That has a cost, and Module 2 pays it: deriving a total across a\n";
echo "  thousand lines on every read is not free, and Lesson 2.1 revisits this\n";
echo "  exact trade-off with the opposite conclusion for a different reason.\n\n";
