<?php
declare(strict_types=1);

/**
 * Example 01 — Three operations, placed three ways
 * =================================================
 *
 * The same three operations, first all shoved into services (which is what
 * most codebases look like), then placed by the four tests.
 *
 * Two of the three are not services. That ratio is roughly right.
 *
 *     php 01-three-operations.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

function money(int $minor): string
{
    return number_format($minor / 100, 2);
}


// ═══════════════════════════════════════════════════════════════════════════
// BEFORE — everything is a service, and every model is a bag of data
// ═══════════════════════════════════════════════════════════════════════════
section('Before — three services, and nothing that knows anything');

final class RateData
{
    public function __construct(public int $basisPoints) {}
}

final class JobCardData
{
    public function __construct(
        public string $reference,
        public int    $totalMinor,
        public string $status,
        public bool   $awaitingAuthorisation,
    ) {}
}

final class TaxCalculationService
{
    public function calculate(RateData $rate, int $amountMinor): int
    {
        return intdiv($amountMinor * $rate->basisPoints + 5000, 10000);
    }
}

final class JobCardAuthorisationService
{
    public function authorise(JobCardData $jobCard): void
    {
        $jobCard->awaitingAuthorisation = false;
        $jobCard->status = 'in_progress';
    }
}

final class CourtesyVehicleEligibilityService
{
    public function isEligible(JobCardData $jobCard, int $minimumMinor): bool
    {
        return $jobCard->totalMinor >= $minimumMinor && $jobCard->status !== 'cancelled';
    }
}

$rate    = new RateData(2000);
$jobCard = new JobCardData('JC-1001', 22500, 'awaiting_authorisation', true);

$tax        = new TaxCalculationService();
$authorise  = new JobCardAuthorisationService();
$eligible   = new CourtesyVehicleEligibilityService();

printf("  tax on %s at 20%%      %s\n", money($jobCard->totalMinor), money($tax->calculate($rate, $jobCard->totalMinor)));
$authorise->authorise($jobCard);
printf("  after authorising      status=%s\n", $jobCard->status);
printf("  eligible at 150.00     %s\n", $eligible->isEligible($jobCard, 15000) ? 'yes' : 'no');

echo "\n  It works. Three services, three data classes, and not one of the six\n";
echo "  can tell you anything about itself.\n\n";
echo "  Note the second service in particular: authorise() sets two public\n";
echo "  fields on a record. That is not a domain service — it is Lesson 1.1's\n";
echo "  anemic model, and calling it 'domain' does not change what it is.\n";


// ═══════════════════════════════════════════════════════════════════════════
// THE TESTS
// ═══════════════════════════════════════════════════════════════════════════
section('The four tests, applied');

$candidates = [
    [
        'op'    => 'Apply a tax rate to an amount',
        'named' => true,
        'alone' => false,   // a rate and an amount, both in hand
        'state' => true,
        'pure'  => true,
        'home'  => 'TaxRate — a VALUE OBJECT',
        'why'   => 'needs a rate and an amount and nothing else, so it belongs on the'
                 . ' value that owns the knowledge',
    ],
    [
        'op'    => 'Authorise additional work',
        'named' => true,
        'alone' => false,   // it changes ONE job card
        'state' => true,
        'pure'  => true,
        'home'  => 'JobCard — the ENTITY',
        'why'   => 'it changes that job card and only that job card. A service doing it'
                 . ' is the anemic model',
    ],
    [
        'op'    => 'Decide courtesy vehicle eligibility',
        'named' => true,
        'alone' => true,    // job card AND a negotiated threshold
        'state' => true,
        'pure'  => true,
        'home'  => 'a DOMAIN SERVICE',
        'why'   => 'needs a job card and a threshold the workshop negotiates; neither can'
                 . ' hold the other',
    ],
    [
        'op'    => 'Load a job card, check it, allocate a car',
        'named' => true,
        'alone' => true,
        'state' => true,
        'pure'  => false,   // needs repositories
        'home'  => 'an APPLICATION service — outside domain/',
        'why'   => 'it loads and saves. Delete the database and it means nothing',
    ],
];

printf("  %-38s %-6s %-6s %-6s %-6s\n", 'Operation', 'named', 'alone?', 'state-', 'pure');
printf("  %-38s %-6s %-6s %-6s %-6s\n", '', '', '', 'less', 'domain');
echo '  ' . str_repeat('-', 74) . "\n";

foreach ($candidates as $c) {
    printf(
        "  %-38s %-6s %-6s %-6s %-6s\n",
        $c['op'],
        $c['named'] ? 'yes' : 'no',
        $c['alone'] ? 'no' : 'YES',      // "belongs to one object?"
        $c['state'] ? 'yes' : 'no',
        $c['pure']  ? 'yes' : 'NO'
    );
    echo "      -> {$c['home']}\n";
    foreach (explode("\n", wordwrap($c['why'], 64)) as $line) {
        echo "         {$line}\n";
    }
    echo "\n";
}

echo "  Column 2 is 'does it belong to one object?'. Only the third and fourth\n";
echo "  rows answer no, and only the third is also pure domain. So of four\n";
echo "  plausible services, exactly ONE is a domain service.\n";


// ═══════════════════════════════════════════════════════════════════════════
// AFTER
// ═══════════════════════════════════════════════════════════════════════════
section('After — one service, and two things that know their own business');

final readonly class Rate
{
    private function __construct(public int $basisPoints) {}

    public static function ofPercent(int $percent): self
    {
        return new self($percent * 100);
    }

    /** The knowledge of how much tax a rate produces is a fact about the rate. */
    public function applyTo(int $amountMinor): int
    {
        return intdiv($amountMinor * $this->basisPoints + 5000, 10000);
    }
}

final class Card
{
    private string $status                = 'awaiting_authorisation';
    private bool   $awaitingAuthorisation = true;

    public function __construct(
        private readonly string $reference,
        private readonly int    $totalMinor,
    ) {}

    /** It changes THIS card. No service required, and none possible. */
    public function authoriseAdditionalWork(): void
    {
        if (!$this->awaitingAuthorisation) {
            throw new DomainException("{$this->reference} has nothing awaiting authorisation.");
        }
        $this->awaitingAuthorisation = false;
        $this->status                = 'in_progress';
    }

    public function status(): string    { return $this->status; }
    public function totalMinor(): int   { return $this->totalMinor; }
    public function reference(): string { return $this->reference; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
}

/** The one genuine service. Its policy is passed in; it remembers nothing. */
final readonly class Eligibility
{
    public function __construct(private int $minimumMinor) {}

    /** @return list<string> empty when it qualifies */
    public function reasonsAgainst(Card $card): array
    {
        $reasons = [];

        if ($card->isCancelled()) {
            $reasons[] = "{$card->reference()} is cancelled.";
        }

        if ($card->totalMinor() < $this->minimumMinor) {
            $reasons[] = sprintf(
                '%s comes to %s, below the %s minimum.',
                $card->reference(),
                money($card->totalMinor()),
                money($this->minimumMinor)
            );
        }

        return $reasons;
    }

    public function isEligible(Card $card): bool
    {
        return $this->reasonsAgainst($card) === [];
    }
}

$rateVo = Rate::ofPercent(20);
$card   = new Card('JC-1001', 22500);

printf("  tax on %s at %s      %s\n", money($card->totalMinor()), '20%', money($rateVo->applyTo($card->totalMinor())));

$card->authoriseAdditionalWork();
printf("  after authorising      status=%s\n", $card->status());

foreach ([15000 => 'standard', 30000 => 'raised'] as $minimum => $label) {
    $service = new Eligibility($minimum);
    printf("\n  %s minimum (%s):\n", ucfirst($label), money($minimum));
    printf("    eligible?  %s\n", $service->isEligible($card) ? 'yes' : 'no');
    foreach ($service->reasonsAgainst($card) as $reason) {
        echo "    reason:    {$reason}\n";
    }
}

echo "\n  The same threshold change that would have needed a deployment in a\n";
echo "  version with 15000 baked into the card is a constructor argument here.\n";

echo "\n  And note the reasons. 'no' sends a service adviser to guess; the\n";
echo "  sentence lets them tell the account holder something true — and lets\n";
echo "  the workshop manager say 'no, fleet accounts have no minimum', which\n";
echo "  is a missing rule found in a meeting rather than in an incident.\n\n";
