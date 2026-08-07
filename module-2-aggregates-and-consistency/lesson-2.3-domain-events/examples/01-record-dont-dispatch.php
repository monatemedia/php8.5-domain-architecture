<?php
declare(strict_types=1);

/**
 * Example 01 — Record, don't dispatch
 * ====================================
 *
 * Part 1 injects a dispatcher into the aggregate and fires events immediately,
 * which is what most event libraries encourage by default. Then the
 * transaction rolls back.
 *
 * Part 2 records instead, and dispatches after the commit.
 * Part 3 shows the smaller benefit that turns out to matter every day.
 *
 *     php 01-record-dont-dispatch.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// Shared: the event, and some handlers with consequences outside the database.
// ═══════════════════════════════════════════════════════════════════════════

final readonly class JobCardInvoiced
{
    public function __construct(
        public string $jobCardReference,
        public int    $totalMinor,
    ) {}
}

final class OutsideWorld
{
    /** @var list<string> */
    private array $done = [];

    public function emailCustomer(JobCardInvoiced $event): void
    {
        $this->done[] = "email sent to the account holder for {$event->jobCardReference}";
    }

    public function postToAccounting(JobCardInvoiced $event): void
    {
        $this->done[] = "posted {$event->totalMinor} to the accounting system";
    }

    /** @return list<string> */
    public function done(): array
    {
        return $this->done;
    }

    public function reset(): void
    {
        $this->done = [];
    }
}

final class Dispatcher
{
    /** @var list<callable(JobCardInvoiced): void> */
    private array $handlers = [];

    public function subscribe(callable $handler): void
    {
        $this->handlers[] = $handler;
    }

    public function dispatch(JobCardInvoiced $event): void
    {
        foreach ($this->handlers as $handler) {
            $handler($event);
        }
    }
}

$world      = new OutsideWorld();
$dispatcher = new Dispatcher();
$dispatcher->subscribe($world->emailCustomer(...));
$dispatcher->subscribe($world->postToAccounting(...));


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — Immediate dispatch
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — The aggregate holds a dispatcher and fires immediately');

final class EagerJobCard
{
    private string $status = 'completed';

    /** The dependency. It is the tell. */
    public function __construct(
        private readonly string     $reference,
        private readonly Dispatcher $dispatcher,
    ) {}

    public function invoice(): void
    {
        $this->status = 'invoiced';
        $this->dispatcher->dispatch(new JobCardInvoiced($this->reference, 10450));
    }

    public function status(): string
    {
        return $this->status;
    }
}

echo "  Notice what the constructor needs:\n\n";
echo "      new EagerJobCard('JC-1001', \$dispatcher)\n\n";
echo "  A job card cannot exist without a piece of infrastructure. Every test,\n";
echo "  every mapper, every fixture now needs one. Rule 1 is already broken\n";
echo "  before anything runs.\n";

echo "\n  Now the operation, inside a transaction that later fails:\n\n";

$eager = new EagerJobCard('JC-1001', $dispatcher);

echo "      [tx] BEGIN\n";
$eager->invoice();
echo "      [tx] job card marked invoiced\n";

foreach ($world->done() as $thing) {
    echo "      [!!] {$thing}\n";
}

echo "      [tx] ...the invoice number allocation fails\n";
echo "      [tx] ROLLBACK\n";

echo "\n  After the rollback:\n\n";
echo "    Database says:     the job card was never invoiced\n";
printf("    In-memory object:  %s\n", $eager->status());
echo "    The account holder: has an invoice in their inbox\n";
echo "    Accounting:         has a posting for it\n";

echo "\n  YOU CANNOT UN-SEND AN EMAIL. The database rolled back; the outside\n";
echo "  world did not. Nothing here is recoverable by a framework.\n";

echo "\n  And note the handlers were not wrong, the dispatcher was not wrong,\n";
echo "  and the rollback worked exactly as designed. The mistake was that the\n";
echo "  handlers ran BEFORE the thing they were told about was true.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — Record, then dispatch after the commit
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — Record it, and let the caller decide when it is real');

$world->reset();

final class RecordingJobCard
{
    /** @var list<JobCardInvoiced> */
    private array $recordedEvents = [];

    private string $status = 'completed';

    /** No dispatcher. No dependencies at all. */
    public function __construct(private readonly string $reference) {}

    public function invoice(): void
    {
        $this->status           = 'invoiced';
        $this->recordedEvents[] = new JobCardInvoiced($this->reference, 10450);
    }

    /**
     * Returns AND CLEARS. Saving an aggregate twice in one request is entirely
     * ordinary, and dispatching its events twice is a support ticket.
     *
     * The name matters: releaseEvents() says something is handed over and given
     * up. getEvents() implies a harmless read, and invites a second call.
     *
     * @return list<JobCardInvoiced>
     */
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
}

echo "  The constructor now:\n\n";
echo "      new RecordingJobCard('JC-1001')\n\n";

echo "  The same failing transaction:\n\n";

$recording = new RecordingJobCard('JC-1001');

echo "      [tx] BEGIN\n";
$recording->invoice();
echo "      [tx] job card marked invoiced, event recorded (nothing dispatched)\n";
echo "      [tx] ...the invoice number allocation fails\n";
echo "      [tx] ROLLBACK\n";

echo "\n  The outside world:\n\n";
if ($world->done() === []) {
    echo "    nothing happened. Which is correct — nothing happened.\n";
}

echo "\n  Now the transaction that succeeds:\n\n";

$good = new RecordingJobCard('JC-4471');
echo "      [tx] BEGIN\n";
$good->invoice();
echo "      [tx] COMMIT\n";

foreach ($good->releaseEvents() as $event) {
    $dispatcher->dispatch($event);
}

foreach ($world->done() as $thing) {
    echo "      [ok] {$thing}\n";
}

echo "\n  Handlers ran exactly once, for something that actually happened.\n";

echo "\n  And releaseEvents() has already cleared:\n\n";
printf("    a second release returns %d event(s)\n", count($good->releaseEvents()));


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — The benefit you will use every day
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — Testing, without a dispatcher at all');

echo "  With immediate dispatch, asserting that invoicing announces itself\n";
echo "  needs a mock or a spy dispatcher, injected into the aggregate, whose\n";
echo "  calls you then inspect.\n\n";
echo "  With recording, it is an array:\n\n";

$underTest = new RecordingJobCard('JC-9001');
$underTest->invoice();
$events = $underTest->releaseEvents();

printf("    count(\$events) === 1                      %s\n", count($events) === 1 ? 'true' : 'FALSE');
printf("    \$events[0] instanceof JobCardInvoiced     %s\n", $events[0] instanceof JobCardInvoiced ? 'true' : 'FALSE');
printf("    \$events[0]->jobCardReference === 'JC-9001' %s\n", $events[0]->jobCardReference === 'JC-9001' ? 'true' : 'FALSE');
printf("    \$events[0]->totalMinor === 10450          %s\n", $events[0]->totalMinor === 10450 ? 'true' : 'FALSE');

echo "\n  No mock, no spy, no framework, no container. The aggregate's own\n";
echo "  return value is the assertion.\n\n";
echo "  That is not a small convenience. A test that needs a test double to\n";
echo "  observe a domain rule is a test that is coupled to how the rule is\n";
echo "  delivered — and Rule 2 of the previous course says to test behaviour,\n";
echo "  not layout.\n\n";
