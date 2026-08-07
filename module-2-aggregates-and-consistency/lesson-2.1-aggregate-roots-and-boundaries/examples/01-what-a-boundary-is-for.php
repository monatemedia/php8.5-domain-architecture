<?php
declare(strict_types=1);

/**
 * Example 01 — What a boundary is actually for
 * =============================================
 *
 * INVARIANT I5 (DOMAIN.md): a job card's total always equals the sum of its
 * lines. There is no moment at which it does not.
 *
 * Part 1 breaks it, in the most ordinary way there is.
 * Part 2 fixes it with a boundary, and counts what the boundary cost.
 * Part 3 shows the boundary drawn too wide, which is the more common error and
 * the one that fails slowly.
 *
 *     php 01-what-a-boundary-is-for.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

function money(int $cents): string
{
    return number_format($cents / 100, 2);
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — No boundary. Two things that must agree, and can be changed apart.
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — No boundary');

final class LooseLine
{
    public function __construct(
        public string $description,
        public int    $cents,
    ) {}
}

final class LooseJobCard
{
    /** @var list<LooseLine> */
    public array $lines = [];

    public int $totalCents = 0;

    public function __construct(public string $reference) {}
}

$loose = new LooseJobCard('JC-1001');
$loose->lines[]    = new LooseLine('Diagnostic', 4500);
$loose->lines[]    = new LooseLine('Front pads', 8200);
$loose->totalCents = 12700;

printf("  %s  lines=%d  total=%s\n", $loose->reference, count($loose->lines), money($loose->totalCents));
echo "  Consistent. Somebody remembered to update both.\n";

echo "\n  Now a perfectly ordinary edit — the pads were quoted wrong:\n\n";
$loose->lines[1]->cents = 9400;

$sum = array_sum(array_map(static fn(LooseLine $l): int => $l->cents, $loose->lines));
printf("  stored total: %s\n", money($loose->totalCents));
printf("  sum of lines: %s\n", money($sum));
printf("  agree? %s\n", $loose->totalCents === $sum ? 'yes' : 'NO');

echo "\n  One line changed, in one place, by somebody who had no reason to think\n";
echo "  about a total. Nothing threw. The job card will be invoiced for 127.00\n";
echo "  against lines adding to 139.00, and the twelve pounds will be found by\n";
echo "  whoever reconciles at month end.\n";

echo "\n  Note what the bug is NOT: it is not that the developer forgot. It is\n";
echo "  that there EXISTS a way to change one without the other.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — A boundary. One way in, and the total cannot be stated separately.
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — A boundary');

final readonly class Line
{
    public function __construct(
        public string $description,
        public int    $cents,
    ) {
        if ($cents < 0) {
            throw new InvalidArgumentException('A line cannot be negative.');
        }
    }
}

final class JobCard
{
    /** @var list<Line> */
    private array $lines = [];

    public function __construct(private readonly string $reference) {}

    public function addLine(Line $line): void
    {
        $this->lines[] = $line;
    }

    /**
     * DERIVED. There is no stored total, so there is nothing to disagree with
     * the lines. The invariant is not enforced — it is unrepresentable.
     */
    public function totalCents(): int
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += $line->cents;
        }
        return $total;
    }

    public function lineCount(): int
    {
        return count($this->lines);
    }

    public function reference(): string
    {
        return $this->reference;
    }
}

$card = new JobCard('JC-2001');
$card->addLine(new Line('Diagnostic', 4500));
$card->addLine(new Line('Front pads', 8200));

printf("  %s  lines=%d  total=%s\n", $card->reference(), $card->lineCount(), money($card->totalCents()));

echo "\n  Every route to the state that broke part 1:\n\n";

$attacks = [
    'Read the internal lines array' => static function () use ($card): void {
        $stolen = $card->lines;
    },
    'Append to the lines array'     => static function () use ($card): void {
        $card->lines[] = new Line('Smuggled', 1);
    },
    'Mutate a line'                 => static function (): void {
        $line = new Line('Front pads', 8200);
        $line->cents = 9400;
    },
];

foreach ($attacks as $label => $attack) {
    try {
        $attack();
        echo "  [!!] {$label} — succeeded.\n";
    } catch (Throwable $e) {
        echo "  [ok] {$label}\n";
        echo "       " . $e::class . "\n";
    }
}

// The total is not on this list, because there is nothing to attack.
printf(
    "\n  And a stored total cannot be corrupted, because there is no stored\n"
    . "  total: property_exists(JobCard, 'totalCents') is %s.\n",
    var_export(property_exists(JobCard::class, 'totalCents'), true)
);
echo "  Part 1's bug is not guarded against here. It has no way to be spelled.\n";

echo "\n  What the boundary cost:\n\n";
echo "    - You can no longer read \$card->lines. Anything that wants the lines\n";
echo "      asks the root, and the root decides what to hand over.\n";
echo "    - Changing a line means removing it and adding another, because the\n";
echo "      lines are immutable.\n";
echo "    - The total is recomputed on every read rather than stored.\n\n";
echo "  All three are real costs. Section 6 of the README argues the third one\n";
echo "  is closer than it looks, and that what makes it safe here is that Money\n";
echo "  is immutable — not that deriving is inherently safe.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — The boundary drawn too wide
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — The boundary drawn too wide');

echo "  The instinct after part 2 is to put more inside. An account holder has\n";
echo "  job cards, so surely the account holder is the root?\n\n";

final class WideAccountHolder
{
    /** @var list<JobCard> */
    private array $jobCards = [];

    public function __construct(private readonly string $name) {}

    public function addJobCard(JobCard $jobCard): void
    {
        $this->jobCards[] = $jobCard;
    }

    public function jobCardCount(): int
    {
        return count($this->jobCards);
    }

    public function name(): string
    {
        return $this->name;
    }
}

$fleet = new WideAccountHolder('Northgate Couriers Ltd');
for ($i = 1; $i <= 2400; $i++) {
    $jc = new JobCard(sprintf('JC-%04d', $i));
    $jc->addLine(new Line('Service', 18000));
    $fleet->addJobCard($jc);
}

printf("  %s has %s job cards.\n\n", $fleet->name(), number_format($fleet->jobCardCount()));

$costs = [
    'Loading one job card'    => 'loads all ' . number_format($fleet->jobCardCount()) . ' of them',
    'Two clerks, two jobs'    => 'same aggregate, so the transaction covers both — they collide',
    'Adding a line'           => 'locks every job card this account holder has ever had',
    'Making it smaller later' => 'everything depends on the shape by then',
];

foreach ($costs as $what => $consequence) {
    printf("  %-26s %s\n", $what, $consequence);
}

echo "\n  This works perfectly in development with forty rows. It is discovered\n";
echo "  in production, by which time the shape is load-bearing.\n";

echo "\n  THE QUESTION THAT WOULD HAVE PREVENTED IT:\n\n";
echo "    If these two changed a second apart, would the business be harmed?\n\n";

$pairs = [
    'A job card and its lines'         => ['harmful',  'for that second the total is wrong'],
    'A job card and its vehicle'       => ['harmless', 'a mileage correction is not a billing event'],
    'A job card and the account holder'=> ['harmless', 'a billing address can change mid-repair'],
    'Two job cards, same account'      => ['harmless', 'they have nothing to do with each other'],
];

foreach ($pairs as $pair => [$verdict, $why]) {
    printf("    %-36s %-9s %s\n", $pair, $verdict, $why);
}

echo "\n  Only the first is harmful, so only the first is one aggregate.\n\n";
echo "  Note that every answer in that table came from the business, not from\n";
echo "  the code. \"Would the business be harmed\" is a question you ask a\n";
echo "  person — and if you cannot find the person, you are guessing.\n\n";
