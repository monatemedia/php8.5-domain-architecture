<?php
declare(strict_types=1);

/**
 * Example 02 — Lesson 2.2's loose end, tied off
 * ==============================================
 *
 * The question 2.2 could not answer: a completed job card could hold a
 * courtesy vehicle forever, and every obvious way of returning it couples two
 * aggregates that should know nothing about each other.
 *
 * Part 1 tries the three obvious ways and shows what each costs.
 * Part 2 does it with an event.
 * Part 3 is honest about what the event did NOT solve.
 *
 *     php 02-neither-side-knows.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

/** Which classes does this one name? A crude coupling meter, and good enough. */
function mentions(string $class, string $needle): bool
{
    $file = (new ReflectionClass($class))->getFileName();

    if ($file === false) {
        return false;
    }

    // Only this class's own body, so the shared file does not confuse the count.
    $source = (string) file_get_contents($file);
    $start  = (new ReflectionClass($class))->getStartLine();
    $end    = (new ReflectionClass($class))->getEndLine();
    $body   = implode("\n", array_slice(explode("\n", $source), $start - 1, $end - $start + 1));

    return str_contains($body, $needle);
}


// ═══════════════════════════════════════════════════════════════════════════
// The two aggregates, cut down to the bone.
// ═══════════════════════════════════════════════════════════════════════════

final readonly class JobCardCompleted
{
    public function __construct(public string $jobCardReference) {}
}

final class MiniFleet
{
    /** @var array<string, ?string> fleet code => job card it is out on */
    private array $vehicles = ['CV-01' => null, 'CV-02' => null];

    public function allocate(string $fleetCode, string $jobCardReference): void
    {
        $this->vehicles[$fleetCode] = $jobCardReference;
    }

    public function returnFromJobCard(string $jobCardReference): void
    {
        foreach ($this->vehicles as $code => $out) {
            if ($out === $jobCardReference) {
                $this->vehicles[$code] = null;
                echo "        [fleet] {$code} returned\n";
                return;
            }
        }

        throw new DomainException("No courtesy vehicle is out on {$jobCardReference}.");
    }

    public function report(): string
    {
        $parts = [];
        foreach ($this->vehicles as $code => $out) {
            $parts[] = $code . ' -> ' . ($out ?? 'available');
        }
        return implode(' | ', $parts);
    }
}

final class MiniJobCard
{
    /** @var list<JobCardCompleted> */
    private array $recordedEvents = [];

    private string $status = 'in_progress';

    public function __construct(private readonly string $reference) {}

    public function complete(): void
    {
        $this->status           = 'completed';
        $this->recordedEvents[] = new JobCardCompleted($this->reference);
    }

    /** @return list<JobCardCompleted> */
    public function releaseEvents(): array
    {
        $events               = $this->recordedEvents;
        $this->recordedEvents = [];
        return $events;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function reference(): string
    {
        return $this->reference;
    }
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — The three obvious answers
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — Three obvious answers, and what each costs');

$attempts = [
    [
        'name' => 'The fleet asks the job card',
        'code' => 'if ($this->jobCards->find($ref)->isComplete()) { ... }',
        'cost' => 'a second aggregate inside the fleet boundary. Two aggregates, one'
                . ' transaction, and 2.2 example 01 is about what that does.',
    ],
    [
        'name' => 'The job card calls the fleet',
        'code' => '$this->fleet->returnFromJobCard($this->id);',
        'cost' => 'JobCard now knows courtesy vehicles exist, holds a fleet, and cannot'
                . ' be constructed without one. Rule 1, and the coupling just points'
                . ' the other way.',
    ],
    [
        'name' => 'A service calls both',
        'code' => 'function completeJobCard(...) { $jc->complete(); $fleet->return(...); }',
        'cost' => 'works, and is a real option. But every caller who completes a job card'
                . ' must remember to use it — Lesson 1.1\'s second caller, exactly.',
    ],
];

foreach ($attempts as $attempt) {
    echo "  {$attempt['name']}\n";
    echo "      {$attempt['code']}\n";
    foreach (explode("\n", wordwrap($attempt['cost'], 64)) as $line) {
        echo "      {$line}\n";
    }
    echo "\n";
}

echo "  The third is not wrong, and on a small system it is the right answer.\n";
echo "  Rule 6 applies: if there is one caller and one rule, a service is\n";
echo "  cheaper than an event system. What follows is what you reach for when\n";
echo "  there are four callers and three things that must happen.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — The event
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — The job card announces; something else acts');

$fleet = new MiniFleet();
$fleet->allocate('CV-01', 'JC-1001');

echo "  Fleet: " . $fleet->report() . "\n\n";

/**
 * APPLICATION code, not domain code. It knows about both aggregates, which is
 * exactly why it does not live in domain/workshop/ — and why the challenge
 * does not ask you to write one there.
 */
$returnCourtesyVehicle = static function (JobCardCompleted $event) use ($fleet): void {
    echo "      [handler] JobCardCompleted for {$event->jobCardReference}\n";

    try {
        $fleet->returnFromJobCard($event->jobCardReference);
    } catch (DomainException) {
        // Not every job card has a courtesy vehicle. A handler that throws on
        // the ordinary case would take down the dispatch of every other
        // handler subscribed to the same event.
        echo "        [fleet] nothing out for that job card — nothing to do\n";
    }
};

$jobCard = new MiniJobCard('JC-1001');

echo "  [tx] BEGIN\n";
$jobCard->complete();
echo "      job card {$jobCard->reference()} is {$jobCard->status()}\n";
echo "  [tx] COMMIT\n\n";

foreach ($jobCard->releaseEvents() as $event) {
    $returnCourtesyVehicle($event);
}

echo "\n  Fleet: " . $fleet->report() . "\n";

echo "\n  And a job card that never had one:\n\n";
$other = new MiniJobCard('JC-9999');
$other->complete();
foreach ($other->releaseEvents() as $event) {
    $returnCourtesyVehicle($event);
}


// ═══════════════════════════════════════════════════════════════════════════
section('The coupling, measured');

$pairs = [
    ['MiniJobCard', 'MiniFleet',        'Does JobCard mention the fleet?'],
    ['MiniFleet',   'MiniJobCard',      'Does the fleet mention JobCard?'],
    ['MiniJobCard', 'JobCardCompleted', 'Does JobCard mention the event?'],
];

foreach ($pairs as [$class, $needle, $question]) {
    printf("  %-38s %s\n", $question, mentions($class, $needle) ? 'YES' : 'no');
}

echo "\n  Neither aggregate names the other. The job card names the event —\n";
echo "  which is a fact it published, not a dependency it holds — and the\n";
echo "  handler in the middle names both, because connecting them is its\n";
echo "  entire job.\n\n";
echo "  The subscription is made once, at the composition root. That is Rule 1\n";
echo "  of the previous course, arriving in a new form: wiring belongs at the\n";
echo "  entry point.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — What the event did not solve
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — What this did NOT solve');

$leftovers = [
    'Two transactions' => 'the job card commits, then the fleet is updated. There is a'
        . ' window where the job card is complete and the vehicle is still out.',
    'A failed handler' => 'if returning the vehicle throws for a real reason, the vehicle'
        . ' stays out. Something must retry, or a human must notice.',
    'Ordering'         => 'two handlers on the same event run in subscription order, which'
        . ' is a fact about your wiring, not about the business.',
    'Silence'          => 'nothing tells the job card that the vehicle came back. If that'
        . ' matters, it needs its own event coming the other way.',
];

foreach ($leftovers as $problem => $detail) {
    echo "  {$problem}\n";
    foreach (explode("\n", wordwrap($detail, 66)) as $line) {
        echo "      {$line}\n";
    }
    echo "\n";
}

echo "  Every one of those is Lesson 2.2's eventual consistency, arriving as\n";
echo "  an implementation problem rather than an argument. Domain events are\n";
echo "  HOW eventual consistency is built; 2.2 was about WHETHER it is\n";
echo "  acceptable.\n\n";
echo "  The order matters. Deciding the second question first is what stops\n";
echo "  this from being machinery in search of a justification.\n\n";
