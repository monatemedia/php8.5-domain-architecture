<?php
declare(strict_types=1);

/**
 * Example 03 — The other answer, with its window measured
 * ========================================================
 *
 * Example 02 made I10 instantaneous by putting every courtesy vehicle inside
 * one aggregate, and the bill was a fleet-wide lock on every allocation.
 *
 * This is the alternative: allow both allocations, detect the collision, and
 * compensate. No shared lock, no bottleneck — and a window during which the
 * business rule is simply false.
 *
 * The point of this file is not that eventual consistency is clever. It is
 * that the window is REAL, the compensation has a phone call attached, and
 * neither of those is a decision a developer should make alone.
 *
 *     php 03-eventually-consistent.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// A claim, written independently by whoever is allocating.
// ═══════════════════════════════════════════════════════════════════════════

final readonly class Claim
{
    public function __construct(
        public string $fleetCode,
        public string $jobCardReference,
        public int    $atSecond,
    ) {}
}

/**
 * No lock, no coordination, no shared aggregate. Anyone may claim anything, and
 * the ledger simply records what happened — which is the entire trade.
 */
final class ClaimLedger
{
    /** @var list<Claim> */
    private array $claims = [];

    /** @var list<string> */
    private array $revoked = [];

    /** @var list<string> */
    private array $notifications = [];

    public function claim(Claim $claim): void
    {
        $this->claims[] = $claim;
        printf(
            "  t=%02ds  %s claims %s   -> accepted, no questions asked\n",
            $claim->atSecond,
            $claim->jobCardReference,
            $claim->fleetCode
        );
    }

    /**
     * The reconciliation. Runs on a timer, or off a domain event — Lesson 2.3
     * is about how it gets triggered without the allocator knowing it exists.
     *
     * @return list<Claim> the claims that survived
     */
    public function reconcile(int $atSecond): array
    {
        printf("\n  t=%02ds  reconciliation runs\n\n", $atSecond);

        /** @var array<string, Claim> $winners keyed by fleet code */
        $winners = [];

        foreach ($this->claims as $claim) {
            $existing = $winners[$claim->fleetCode] ?? null;

            if ($existing === null) {
                $winners[$claim->fleetCode] = $claim;
                continue;
            }

            // FIRST CLAIM WINS. That is a POLICY, not a law of nature — see the
            // note at the foot of this file. Somebody in the workshop chose it,
            // and they could equally have chosen "the higher-priority account
            // wins" or "whichever one has already collected the car".
            $loser = $claim->atSecond >= $existing->atSecond ? $claim : $existing;
            $kept  = $loser === $claim ? $existing : $claim;

            $winners[$claim->fleetCode] = $kept;

            $this->revoked[] = sprintf('%s / %s', $loser->jobCardReference, $loser->fleetCode);

            printf(
                "        collision on %s: %s (t=%ds) and %s (t=%ds)\n",
                $claim->fleetCode,
                $existing->jobCardReference,
                $existing->atSecond,
                $claim->jobCardReference,
                $claim->atSecond
            );
            printf("        keeping %s, revoking %s\n", $kept->jobCardReference, $loser->jobCardReference);

            // THE COMPENSATION IS VISIBLE. A silent revocation would leave the
            // system eventually RIGHT and the customer permanently WRONG.
            $this->notifications[] = sprintf(
                'Ring %s: %s has been reallocated. Offer an alternative.',
                $loser->jobCardReference,
                $loser->fleetCode
            );
        }

        return array_values($winners);
    }

    /** @return list<string> */
    public function notifications(): array
    {
        return $this->notifications;
    }

    /** @return list<string> */
    public function revoked(): array
    {
        return $this->revoked;
    }
}


// ═══════════════════════════════════════════════════════════════════════════
section('The collision, allowed to happen');

$ledger = new ClaimLedger();

$ledger->claim(new Claim('CV-01', 'JC-1001', 0));
$ledger->claim(new Claim('CV-02', 'JC-2002', 1));
$ledger->claim(new Claim('CV-01', 'JC-4471', 1));       // the collision
$ledger->claim(new Claim('CV-03', 'JC-5150', 2));

echo "\n  Both CV-01 claims succeeded. For the next two seconds, I10 is FALSE\n";
echo "  and the system does not know it.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Reconciliation');

$survivors = $ledger->reconcile(3);

echo "\n  Surviving allocations:\n\n";
foreach ($survivors as $claim) {
    printf("    %s -> %s\n", $claim->fleetCode, $claim->jobCardReference);
}

echo "\n  Revoked:\n\n";
foreach ($ledger->revoked() as $revocation) {
    echo "    {$revocation}\n";
}

echo "\n  And the part that is not code:\n\n";
foreach ($ledger->notifications() as $notification) {
    echo "    {$notification}\n";
}


// ═══════════════════════════════════════════════════════════════════════════
section('The window, stated plainly');

$window = [
    ['t=0s',  'JC-1001 told it has CV-01',   'true'],
    ['t=1s',  'JC-4471 told it has CV-01',   'I10 IS NOW FALSE'],
    ['t=2s',  'nothing happens',             'still false'],
    ['t=3s',  'reconciliation revokes one',  'true again'],
    ['t=3s+', 'somebody rings JC-4471',      'true, and a customer is annoyed'],
];

printf("  %-8s %-32s %s\n", 'When', 'What', 'Is I10 true?');
echo '  ' . str_repeat('-', 74) . "\n";
foreach ($window as [$when, $what, $state]) {
    printf("  %-8s %-32s %s\n", $when, $what, $state);
}

echo "\n  Three seconds. That is the whole product. Everything else in this\n";
echo "  file is machinery for making three seconds survivable.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('The two things that are policy, not engineering');

echo "  1. WHO WINS.\n\n";
echo "     This file says first claim wins, because it had to say something.\n";
echo "     Equally defensible: the fleet account wins; whoever has physically\n";
echo "     collected the car wins; the job card with the earlier promised\n";
echo "     collection time wins. Each produces different phone calls to\n";
echo "     different customers, and a developer picking one at their desk has\n";
echo "     made a commercial decision and hidden it in a foreach.\n\n";

echo "  2. WHETHER THREE SECONDS IS ACCEPTABLE AT ALL.\n\n";
echo "     Courtesy car    -> almost certainly. The cost is a phone call.\n";
echo "     Operating room  -> no. There is no compensation for two surgeries\n";
echo "                        booked into one theatre.\n";
echo "     Airline seat    -> yes, deliberately, compensated with money. They\n";
echo "                        have priced the phone call.\n";
echo "     Bank withdrawal -> no. Clawing funds back is sometimes impossible.\n\n";
echo "     Identical technique. Completely different answers. The difference\n";
echo "     is entirely about what the compensation costs in the real world.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('How to ask, without using the words');

echo "  Do not ask \"should this be eventually consistent?\". It means nothing\n";
echo "  to anybody outside engineering, and the answer will be whatever they\n";
echo "  think you want.\n\n";
echo "  Ask instead:\n\n";
echo "      \"If two of your people both booked the same courtesy car within a\n";
echo "       few seconds, what would happen today?\"\n\n";
echo "  The answer is almost always a description of an existing manual\n";
echo "  process — which tells you two things at once: the collision ALREADY\n";
echo "  HAPPENS, and the business ALREADY has a compensation for it. Your job\n";
echo "  is then to automate a decision they have already made, rather than to\n";
echo "  invent one.\n\n";
echo "  And if the answer is \"that could never happen\" — that is worth\n";
echo "  hearing too. It usually means the volume is low enough that example\n";
echo "  02's fleet aggregate is the right answer and none of this is needed.\n\n";
