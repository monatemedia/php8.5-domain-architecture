<?php
declare(strict_types=1);

/**
 * Example 02 — Domain service or application service?
 * ====================================================
 *
 * The line most codebases smear, and the one that decides which directory a
 * class lives in.
 *
 * Part 1 sorts eight candidates by one question.
 * Part 2 runs both kinds side by side, so the difference is visible rather
 * than asserted.
 *
 *     php 02-domain-or-application.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — Eight candidates, one question');

echo "  THE QUESTION: if you deleted the database, would this class still make\n";
echo "  sense to somebody who runs the business?\n\n";

$candidates = [
    ['Decide if a job card qualifies for a courtesy car', 'domain',      'a job card and a threshold. Nothing loaded.'],
    ['Apply a tax rate to an amount',                     'neither',     'a method on TaxRate. Two values, both in hand.'],
    ['Load a job card, price it, save it',                'application', 'it loads and saves. That is orchestration.'],
    ['Work out which courtesy vehicles are free',         'neither',     'CourtesyFleet already knows. It is a method on the aggregate.'],
    ['Split an invoice across two cost centres',          'domain',      'a real operation, over domain objects, belonging to neither centre.'],
    ['Send the invoice email',                            'application', 'and barely that — it is infrastructure with a wrapper.'],
    ['Decide whether two job cards are duplicates',       'domain',      'two of the same aggregate. Classic shape.'],
    ['Fetch the tax rate from config',                    'application', 'config is the outside world.'],
];

printf("  %-50s %-12s\n", 'Candidate', 'Kind');
echo '  ' . str_repeat('-', 74) . "\n";
foreach ($candidates as [$what, $kind, $why]) {
    printf("  %-50s %-12s\n", $what, strtoupper($kind));
    echo "      {$why}\n";
}

$counts = array_count_values(array_column($candidates, 1));
echo "\n  ";
foreach ($counts as $kind => $n) {
    echo strtoupper($kind) . ": {$n}   ";
}
echo "\n\n  'Neither' is a real answer and it is the one to reach for first. Two of\n";
echo "  eight plausible services turned out to be methods on something that\n";
echo "  already existed.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — Both kinds, side by side');

final class Card
{
    public function __construct(
        public readonly string $reference,
        public readonly int    $totalMinor,
    ) {}
}

/**
 * DOMAIN service. Takes domain objects. Decides. Depends on nothing.
 */
final readonly class Eligibility
{
    public function __construct(private int $minimumMinor) {}

    public function isEligible(Card $card): bool
    {
        return $card->totalMinor >= $this->minimumMinor;
    }
}

/**
 * APPLICATION service. Takes an identifier. Loads, decides, acts, announces.
 * Lives OUTSIDE domain/, and the capstone's import-scanning test would fail if
 * it did not.
 */
final class AllocateCourtesyVehicle
{
    /** @param array<string, Card> $cards a stand-in repository */
    public function __construct(
        private readonly array       $cards,
        private readonly Eligibility $eligibility,
    ) {}

    public function __invoke(string $reference): string
    {
        // 1. LOAD — infrastructure
        $card = $this->cards[$reference] ?? throw new RuntimeException("No job card {$reference}.");

        // 2. DECIDE — the domain. One line, and it is the only line that
        //    contains a business rule.
        if (!$this->eligibility->isEligible($card)) {
            return "refused: {$reference} does not qualify";
        }

        // 3. ACT and 4. ANNOUNCE — infrastructure again
        return "allocated a courtesy vehicle to {$reference}";
    }
}

$repository = [
    'JC-1001' => new Card('JC-1001', 22500),
    'JC-2002' => new Card('JC-2002', 4500),
];

$eligibility = new Eligibility(15000);
$allocate    = new AllocateCourtesyVehicle($repository, $eligibility);

foreach (['JC-1001', 'JC-2002'] as $reference) {
    echo "  " . $allocate($reference) . "\n";
}

echo "\n  Look at what each class needed to be constructed:\n\n";
printf("    Eligibility             %s\n", 'a threshold');
printf("    AllocateCourtesyVehicle %s\n", 'a repository, plus the domain service');

echo "\n  That difference is the whole distinction. One of them can be built in\n";
echo "  a test with a number. The other cannot be built at all without\n";
echo "  infrastructure, which is exactly why it does not belong in domain/.\n";

echo "\n  And count the business rules in AllocateCourtesyVehicle: one line,\n";
echo "  delegated. Everything else is load, guard, return. That is what an\n";
echo "  application service SHOULD look like — thin, and boring, and holding\n";
echo "  no rules of its own.\n";

echo "\n  When it stops looking like that — when the orchestration grows an if\n";
echo "  statement about the business — the rule has escaped from the domain,\n";
echo "  and Lesson 1.1's second-caller problem starts again from the top.\n\n";
