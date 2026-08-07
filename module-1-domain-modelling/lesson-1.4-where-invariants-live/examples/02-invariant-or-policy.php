<?php
declare(strict_types=1);

/**
 * Example 02 — Invariant, policy, or sanity check?
 * =================================================
 *
 * The most useful distinction in the lesson, and the one that decides where a
 * guard goes before you have argued about anything else.
 *
 * Part 1 sorts eight guards. Part 2 runs the failure that a hard-coded policy
 * produces. Part 3 runs the failure that an un-overridable sanity check
 * produces, which is the subtler and more damaging of the two.
 *
 *     php 02-invariant-or-policy.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — Eight guards, sorted');

/** @var list<array{guard:string, kind:string, home:string, tell:string}> */
$guards = [
    [
        'guard' => 'Money is never negative',
        'kind'  => 'INVARIANT',
        'home'  => 'Money constructor',
        'tell'  => 'no customer could reasonably have a different answer',
    ],
    [
        'guard' => 'Labour bills in whole tenths, rounded up',
        'kind'  => 'INVARIANT',
        'home'  => 'LabourUnits constructor',
        'tell'  => 'a fractional tenth is not a thing that exists',
    ],
    [
        'guard' => 'A job card cannot be invoiced before completion',
        'kind'  => 'INVARIANT',
        'home'  => 'JobCard::invoice()',
        'tell'  => 'loaded from storage in that state, it would be corruption',
    ],
    [
        'guard' => 'Additional work under 50 needs no authorisation',
        'kind'  => 'POLICY',
        'home'  => 'passed in',
        'tell'  => 'it was 30 last year and it is 200 for fleet accounts',
    ],
    [
        'guard' => 'Tax is 20%',
        'kind'  => 'POLICY',
        'home'  => 'passed in',
        'tell'  => 'set by a government, with a commencement date',
    ],
    [
        'guard' => 'Courtesy vehicles are only for jobs over 2 days',
        'kind'  => 'POLICY',
        'home'  => 'passed in',
        'tell'  => 'a commercial decision; it will be waived for someone',
    ],
    [
        'guard' => 'An odometer reading below 2,000,000 km',
        'kind'  => 'SANITY',
        'home'  => 'boundary, overridable',
        'tell'  => 'it exists to catch a typo, not to state a fact',
    ],
    [
        'guard' => 'A fault description under 10,000 characters',
        'kind'  => 'SANITY',
        'home'  => 'boundary, overridable',
        'tell'  => 'nobody in the business has ever discussed it',
    ],
];

printf("  %-46s %-11s %s\n", 'Guard', 'Kind', 'Home');
echo '  ' . str_repeat('-', 92) . "\n";
foreach ($guards as $g) {
    printf("  %-46s %-11s %s\n", $g['guard'], $g['kind'], $g['home']);
    echo "      tell: {$g['tell']}\n";
}

$counts = array_count_values(array_column($guards, 'kind'));
echo "\n  ";
foreach ($counts as $kind => $n) {
    echo "{$kind}: {$n}   ";
}
echo "\n\n  The tells are the useful part. 'Could two customers reasonably have\n";
echo "  different answers?' and 'has anyone in the business ever discussed\n";
echo "  it?' settle most cases in one question.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — A policy hard-coded as an invariant');

final class HardCodedJobCard
{
    /** A policy, welded into a domain class. */
    private const int AUTHORISATION_THRESHOLD_MINOR = 5000;

    public function needsAuthorisation(int $amountMinor): bool
    {
        return $amountMinor > self::AUTHORISATION_THRESHOLD_MINOR;
    }
}

$hardCoded = new HardCodedJobCard();

echo "  Standard account, 4,000 minor units: "
   . ($hardCoded->needsAuthorisation(4000) ? 'needs authorisation' : 'proceeds') . "\n";
echo "  Standard account, 6,000 minor units: "
   . ($hardCoded->needsAuthorisation(6000) ? 'needs authorisation' : 'proceeds') . "\n";

echo "\n  Correct today. Now the fleet contract is signed and Northgate's\n";
echo "  threshold is 20,000:\n\n";
echo "  Northgate, 6,000 minor units:        "
   . ($hardCoded->needsAuthorisation(6000) ? 'needs authorisation' : 'proceeds')
   . "   <- wrong, and there is no way to say so\n";

echo "\n  The fix is a deployment. Every deployment. The business asks for a\n";
echo "  number to be changed and is told it will be in next week's release,\n";
echo "  which is the moment they stop believing the system reflects how they\n";
echo "  work.\n";

final class PolicyJobCard
{
    public function needsAuthorisation(int $amountMinor, int $thresholdMinor): bool
    {
        return $amountMinor > $thresholdMinor;
    }
}

$passedIn = new PolicyJobCard();

echo "\n  With the policy passed in:\n\n";
foreach ([['Standard', 5000], ['Northgate fleet', 20000]] as [$account, $threshold]) {
    printf(
        "    %-18s threshold %-7d 6,000 -> %s\n",
        $account,
        $threshold,
        $passedIn->needsAuthorisation(6000, $threshold) ? 'needs authorisation' : 'proceeds'
    );
}

echo "\n  Same class, both answers correct, no deployment. The domain object\n";
echo "  still owns the COMPARISON, which is the part that is genuinely a rule.\n";
echo "  It just does not own the NUMBER, which never was one.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — A sanity check with no override');

final class BoundedReading
{
    private const int MAX_KM = 2_000_000;

    private function __construct(public int $kilometres) {}

    public static function of(int $kilometres): self
    {
        if ($kilometres < 0 || $kilometres > self::MAX_KM) {
            throw new InvalidArgumentException(
                "Reading must be between 0 and " . self::MAX_KM . "; got {$kilometres}."
            );
        }
        return new self($kilometres);
    }
}

echo "  A haulage contract. A truck arrives on 2,400,000 km — a true reading.\n\n";

try {
    BoundedReading::of(2_400_000);
    echo "  Accepted.\n";
} catch (Throwable $e) {
    echo "  [refused] " . $e->getMessage() . "\n";
}

echo "\n  What happens next, on a real service desk:\n\n";
echo "    1. The adviser does not read the exception. They see a form error.\n";
echo "    2. They still need the job card open — there is a truck on the ramp.\n";
echo "    3. So they enter 999,999. Or 240,000, assuming it was a typo.\n";
echo "    4. The reading is accepted. The job card opens. Everyone moves on.\n\n";

$entered = BoundedReading::of(999_999);
echo "  Recorded: {$entered->kilometres} km, against a truck on 2,400,000.\n";

echo "\n  Nobody logs a bug, because from the desk it looked like validation\n";
echo "  working. The service history is now wrong, permanently, and it will be\n";
echo "  believed by everyone who reads it afterwards — including whoever\n";
echo "  handles the warranty claim.\n\n";
echo "  A SANITY CHECK WITH NO OVERRIDE PATH HAS BECOME A BUSINESS RULE BY\n";
echo "  ACCIDENT. And it is worse than the hard-coded policy in part 2,\n";
echo "  because that one produced a visible complaint. This one produces\n";
echo "  plausible data.\n";

echo "\n  The two honest shapes:\n\n";
echo "    - A bound so far out that no real value reaches it (say 10 million),\n";
echo "      accepting that it then catches fewer typos.\n";
echo "    - A bound with a documented override that records WHO overrode it\n";
echo "      and why, so the unusual reading arrives with its evidence.\n\n";
echo "  Which you choose depends on how often the typo happens versus how\n";
echo "  often the outlier does — which is a question with an answer, in your\n";
echo "  own data, that almost nobody looks up before writing the guard.\n\n";
