<?php
declare(strict_types=1);

/**
 * Example 03 — Events are facts about the past
 * =============================================
 *
 * Three rules, each with the bug it prevents:
 *
 *   Part 1  Past tense — because a command can be refused and a fact cannot.
 *   Part 2  Carry values, not the aggregate.
 *   Part 3  What to put in one, and why "everything" is expensive.
 *
 * Part 4 is about the timestamp that is deliberately missing.
 *
 *     php 03-facts-about-the-past.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — Past tense
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — Names, and the difference a tense makes');

$names = [
    ['InvoiceJobCard',            'command', 'an instruction. It can be refused.'],
    ['JobCardInvoiced',           'event',   'it happened. Nobody can refuse it.'],
    ['SendInvoiceEmail',          'command', 'and it names a MECHANISM, so it can only ever have one handler'],
    ['JobCardInvoiced',           'event',   'the same trigger, named so that three handlers can subscribe'],
    ['JobCardInvoiceReversed',    'event',   'how you undo an event: another event'],
    ['CancelJobCardInvoiced',     'nonsense','you cannot cancel the past. If you wrote this, you meant the row above.'],
];

printf("  %-26s %-9s %s\n", 'Name', 'Kind', 'Why');
echo '  ' . str_repeat('-', 74) . "\n";
foreach ($names as [$name, $kind, $why]) {
    printf("  %-26s %-9s %s\n", $name, $kind, $why);
}

echo "\n  The third and fourth rows are the useful pair. SendInvoiceEmail names\n";
echo "  what you want to happen, so the day somebody also wants an accounting\n";
echo "  posting there is nowhere to put it. JobCardInvoiced names what\n";
echo "  happened, and any number of things can care.\n\n";
echo "  THE TEST: if two unrelated teams could both reasonably want to react,\n";
echo "  it is an event. If exactly one thing must happen and it is the point\n";
echo "  of the call, it is a command — and a plain method call is fine.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — Carry values, not the aggregate
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — What happens when the event carries $this');

final class LeakyJobCard
{
    private string $status = 'invoiced';
    private int    $totalMinor;

    public function __construct(private readonly string $reference, int $totalMinor)
    {
        $this->totalMinor = $totalMinor;
    }

    public function reference(): string  { return $this->reference; }
    public function status(): string     { return $this->status; }
    public function totalMinor(): int    { return $this->totalMinor; }

    public function cancel(): void       { $this->status = 'cancelled'; }
    public function addLine(int $minor): void { $this->totalMinor += $minor; }
}

/** The event nobody should write. */
final readonly class LeakyInvoiced
{
    public function __construct(public LeakyJobCard $jobCard) {}
}

$jobCard = new LeakyJobCard('JC-1001', 10450);
$event   = new LeakyInvoiced($jobCard);

printf("  At the moment of the event: %s, %d\n", $event->jobCard->status(), $event->jobCard->totalMinor());

echo "\n  A handler, doing something a handler has no business doing:\n\n";
$event->jobCard->addLine(5000);
$event->jobCard->cancel();

printf("  The same event now reads: %s, %d\n", $event->jobCard->status(), $event->jobCard->totalMinor());

echo "\n  The event is `readonly`, and it changed anyway. readonly protects the\n";
echo "  REFERENCE, not the object it points at — the handler was handed a live\n";
echo "  handle to the aggregate, and Lesson 2.1's leaky collection has arrived\n";
echo "  by post.\n";

echo "\n  The second reason, which outlasts the first:\n\n";
echo "    An event may be handled LATER. Queued, stored, replayed next\n";
echo "    Tuesday. By then the aggregate has moved on, so an event carrying\n";
echo "    \$this describes the aggregate NOW — and a fact about the past is\n";
echo "    supposed to describe the world WHEN IT HAPPENED.\n";

final readonly class Invoiced
{
    public function __construct(
        public string $jobCardReference,
        public int    $totalMinor,
    ) {}
}

$safe    = new LeakyJobCard('JC-4471', 10450);
$factual = new Invoiced($safe->reference(), $safe->totalMinor());

printf("\n  Values instead: %s, %d\n", $factual->jobCardReference, $factual->totalMinor);

$safe->addLine(5000);
$safe->cancel();

printf("  After the aggregate changes: %s, %d\n", $factual->jobCardReference, $factual->totalMinor);
echo "\n  Unchanged, because it was never a window onto the aggregate. It is a\n";
echo "  record of what was true.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — What goes in
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — What to put in an event');

echo "  THE TEST: what does a handler need in order to act, WITHOUT loading\n";
echo "  anything?\n\n";

$payloads = [
    ['JobCardOpened',    'job card id, vehicle id, account holder id', 'enough to notify or index'],
    ['JobCardCompleted', 'job card id',                                'the courtesy-vehicle handler needs nothing else'],
    ['JobCardInvoiced',  'job card id, total',                         'the total AT THE MOMENT of invoicing'],
];

foreach ($payloads as [$event, $carries, $why]) {
    printf("  %-18s %-44s %s\n", $event, $carries, $why);
}

echo "\n  And the argument against 'put everything in, just in case':\n\n";
echo "    An event is a PUBLISHED CONTRACT. Every field is something a\n";
echo "    handler may come to depend on, and handlers are frequently written\n";
echo "    by people you will never meet — another team, another service, a\n";
echo "    webhook consumer.\n\n";
echo "    Adding a field later is easy. Removing one is a breaking change to\n";
echo "    code you do not own and cannot grep.\n\n";
echo "    So the cost of a speculative field is not the byte. It is that you\n";
echo "    can never take it out.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 4 — The missing timestamp
// ═══════════════════════════════════════════════════════════════════════════
section('Part 4 — Where the timestamp went');

echo "  Every real event system stamps events with a time. None of the events\n";
echo "  in this lesson has one. That is a trade-off, not an obvious win.\n\n";

echo "  The problem: 'now' is the outside world. An event constructor calling\n";
echo "  new DateTimeImmutable() has reached for the system clock, and no\n";
echo "  signature anywhere admits to it.\n\n";

$options = [
    [
        'No timestamp; infrastructure stamps it on release',
        'what this course does',
        'the recorded time is when it was SAVED, not when it HAPPENED. Fine for'
        . ' audit ordering; wrong if a rule depends on the exact moment.',
    ],
    [
        'Inject a Clock into the method that records',
        'correct',
        'and it puts a parameter on every method that records an event. Reach for'
        . ' this the moment a business rule depends on time.',
    ],
    [
        'new DateTimeImmutable() in the event constructor',
        'avoid',
        'cheapest to write. The domain now depends on the system clock, nothing'
        . ' declares it, and nobody can substitute it in a test.',
    ],
];

foreach ($options as [$option, $verdict, $cost]) {
    printf("  %s   [%s]\n", $option, $verdict);
    foreach (explode("\n", wordwrap($cost, 66)) as $line) {
        echo "      {$line}\n";
    }
    echo "\n";
}

echo "  THE RULE OF THUMB:\n\n";
echo "    If the time is EVIDENCE — an audit trail, an ordering — infrastructure\n";
echo "    can supply it.\n\n";
echo "    If the time is INPUT TO A DECISION — 'is this overdue?', 'does this\n";
echo "    fall in the current tax year?' — it must be a parameter, and Module 3\n";
echo "    will make you pass it.\n\n";
echo "  The third option is what most codebases do, and it is the one to avoid.\n";
echo "  Not because a timestamp is expensive — because a dependency that\n";
echo "  nothing declares is one nobody can replace.\n\n";
