<?php
declare(strict_types=1);

/**
 * Example 01 — One aggregate per transaction
 * ===========================================
 *
 * Two reasons for the rule, both run rather than asserted.
 *
 *   Part 1  Correctness — a write that touches two aggregates and fails halfway.
 *   Part 2  Contention  — two locks, two orders, one deadlock.
 *   Part 3  What to do instead.
 *
 *     php 01-one-aggregate-per-transaction.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// A toy store with a transaction, so the failure is visible.
// ═══════════════════════════════════════════════════════════════════════════

final class Store
{
    /** @var array<string, string> committed state */
    private array $committed = [];

    /** @var ?array<string, string> pending writes, null when not in a transaction */
    private ?array $pending = null;

    public function begin(): void
    {
        $this->pending = [];
    }

    public function write(string $key, string $value): void
    {
        if ($this->pending === null) {
            throw new LogicException('Not in a transaction.');
        }
        $this->pending[$key] = $value;
        echo "      [tx] write {$key} = {$value}\n";
    }

    public function commit(): void
    {
        if ($this->pending === null) {
            throw new LogicException('Not in a transaction.');
        }
        $this->committed = [...$this->committed, ...$this->pending];
        $this->pending   = null;
        echo "      [tx] COMMIT\n";
    }

    public function rollback(): void
    {
        $this->pending = null;
        echo "      [tx] ROLLBACK\n";
    }

    public function read(string $key): string
    {
        return $this->committed[$key] ?? '(unset)';
    }
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — Correctness
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — Two aggregates, one transaction, one failure');

$store = new Store();

echo "  The operation: complete a job card AND return its courtesy vehicle.\n";
echo "  Two aggregates. One transaction.\n\n";

$store->begin();
try {
    $store->write('jobcard:JC-1001:status', 'completed');

    // The second aggregate refuses. Anything can cause this — a rule, a
    // constraint, a lost connection.
    throw new DomainException('CV-01 is not out, so it cannot be returned.');
} catch (DomainException $e) {
    echo "      [!!] " . $e->getMessage() . "\n";
    $store->rollback();
}

printf("\n  Job card status after rollback: %s\n", $store->read('jobcard:JC-1001:status'));
echo "  The database is fine. The transaction did its job.\n";

echo "\n  Now the objects that were in memory:\n\n";

final class ToyJobCard
{
    private string $status = 'in_progress';

    public function complete(): void
    {
        $this->status = 'completed';
    }

    public function status(): string
    {
        return $this->status;
    }
}

$jobCard = new ToyJobCard();
$jobCard->complete();                        // the first half ran
// the second half threw, and the transaction rolled back

printf("  In-memory job card status: %s\n", $jobCard->status());

echo "\n  THE DATABASE ROLLED BACK. THE OBJECT DID NOT.\n\n";
echo "  There is no rollback for an object. If that job card is still held —\n";
echo "  by a request that catches the exception and carries on, by a queue\n";
echo "  worker processing a batch, by anything with a longer life than one\n";
echo "  transaction — it now believes it is completed and the database says\n";
echo "  otherwise.\n\n";
echo "  This is why 'one aggregate per transaction' is a correctness rule and\n";
echo "  not a performance one. With one aggregate there is no halfway.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — Contention
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — Two locks, two orders, one deadlock');

/** Two requests, each locking two aggregates, in the order that suited them. */
$requestA = ['jobcard:JC-1001', 'fleet:CV-01'];
$requestB = ['fleet:CV-01', 'jobcard:JC-1001'];

echo "  Request A locks:  " . implode(' then ', $requestA) . "\n";
echo "  Request B locks:  " . implode(' then ', $requestB) . "\n\n";

$held = [];
$step = 0;

foreach ([[$requestA[0], 'A'], [$requestB[0], 'B'], [$requestA[1], 'A'], [$requestB[1], 'B']] as [$resource, $who]) {
    $step++;
    if (isset($held[$resource]) && $held[$resource] !== $who) {
        printf("  %d. %s wants %-18s held by %s  -> BLOCKED\n", $step, $who, $resource, $held[$resource]);
        continue;
    }
    $held[$resource] = $who;
    printf("  %d. %s takes %-18s -> ok\n", $step, $who, $resource);
}

echo "\n  A holds the job card and wants the fleet. B holds the fleet and wants\n";
echo "  the job card. Neither can proceed.\n\n";
echo "  Nobody wrote a deadlock. Two developers each locked what their own\n";
echo "  operation needed, in the order that read naturally to them, and the\n";
echo "  bug exists only when both run at once — which is to say, only in\n";
echo "  production, only under load, and never in the test that was written\n";
echo "  for either one.\n";

echo "\n  With one aggregate per transaction there is one lock, so there is no\n";
echo "  order to get wrong.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — What to do instead
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — The three options');

$options = [
    [
        'name' => '1. Move the rule inside one boundary',
        'when' => 'the objects genuinely must be correct together AND there are few of them',
        'cost' => 'the aggregate grows; every write to any part of it contends with every other',
        'ex'   => 'CourtesyFleet owns every courtesy vehicle. The challenge builds this.',
    ],
    [
        'name' => '2. Two transactions, eventual consistency',
        'when' => 'a window in which the rule is false is acceptable to the business',
        'cost' => 'a window in which the rule is false, plus a compensation somebody must write',
        'ex'   => 'allocate optimistically; a check revokes the loser. Example 03.',
    ],
    [
        'name' => '3. Notice the rule is not what you thought',
        'when' => 'more often than anyone expects',
        'cost' => 'a conversation',
        'ex'   => '"one courtesy car per job card" needs no cross-aggregate rule at all —'
                . ' it is a fact about ONE job card.',
    ],
];

foreach ($options as $option) {
    echo "  {$option['name']}\n";
    printf("      when:  %s\n", $option['when']);
    printf("      cost:  %s\n", $option['cost']);
    printf("      e.g.:  %s\n\n", $option['ex']);
}

echo "  Option 3 is listed last and should be tried first. A surprising number\n";
echo "  of 'cross-aggregate invariants' dissolve when somebody states them\n";
echo "  precisely, because the imprecise version quietly involved two objects\n";
echo "  and the precise one does not.\n\n";
echo "  Compare, from DOMAIN.md:\n\n";
echo "    'a job card has at most one courtesy vehicle'\n";
echo "        -> one job card. No spanning rule. Put it on the job card.\n\n";
echo "    'a courtesy vehicle is on at most one open job card AT ANY TIME'\n";
echo "        -> every open job card at once. Genuinely spanning. That is I10,\n";
echo "           and example 02 is about why it is hard.\n\n";
