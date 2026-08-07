<?php
declare(strict_types=1);

/**
 * Example 01 — One feature, three architectures
 * ==============================================
 *
 * The feature: add a labour line to a job card, then invoice it.
 * The rule:    a job card may not be invoiced unless it is complete.
 *
 * Written three times — Active Record, transaction script, tactical DDD — with
 * nothing hidden and nothing exaggerated. Read all three before forming an
 * opinion, and notice that the third is the longest by a wide margin.
 *
 * That length is the cost from section 2 of the README, made concrete. This
 * example does NOT show it paying for itself; example 02 does that. Seeing the
 * bill before the benefit is deliberate, because that is the order in which you
 * encounter them on a real project.
 *
 *     php 01-the-same-feature-three-ways.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 70) . "\n  {$title}\n" . str_repeat('=', 70) . "\n\n";
}

function money(int $cents): string
{
    return number_format($cents / 100, 2);
}


// ═══════════════════════════════════════════════════════════════════════════
// OPTION A — Active Record
// The model IS the row. Rules live wherever the developer was standing.
// ═══════════════════════════════════════════════════════════════════════════
section('Option A — Active Record');

final class ArJobCard
{
    public string $status = 'in_progress';
    /** @var list<array{description:string, cents:int}> */
    public array $lines = [];

    public function __construct(public string $reference) {}

    public function totalCents(): int
    {
        return array_sum(array_column($this->lines, 'cents'));
    }

    public function save(): void
    {
        echo "  [db] UPDATE job_cards SET status='{$this->status}' WHERE ref='{$this->reference}'\n";
    }
}

$a = new ArJobCard('JC-1001');
$a->lines[] = ['description' => 'Diagnostic, 0.5h', 'cents' => 4500];
$a->status  = 'completed';
$a->status  = 'invoiced';
$a->save();

echo "  Total: " . money($a->totalCents()) . "\n";
echo "\n  Nine lines of application code. Nothing to explain to anybody.\n";

echo "\n  Now the same object, mishandled:\n";
$broken = new ArJobCard('JC-1002');
$broken->status = 'invoiced';          // never completed — nothing stops this
$broken->save();
echo "  Status is now '{$broken->status}' on a job card where no work was done.\n";
echo "  The assignment is legal PHP, legal SQL, and wrong. There is no line of\n";
echo "  code to point at, because the missing check has no home.\n";


// ═══════════════════════════════════════════════════════════════════════════
// OPTION B — Transaction script
// One procedure per use case. Honest, linear, no ceremony.
// ═══════════════════════════════════════════════════════════════════════════
section('Option B — Transaction script');

/**
 * @param array{ref:string, status:string, lines:list<array{description:string, cents:int}>} $card
 * @return array{ref:string, status:string, lines:list<array{description:string, cents:int}>}
 */
function invoiceJobCard(array $card): array
{
    if ($card['status'] !== 'completed') {
        throw new RuntimeException("Cannot invoice {$card['ref']}: status is '{$card['status']}'.");
    }
    $total = array_sum(array_column($card['lines'], 'cents'));
    echo "  [db] INSERT INTO invoices (job_card, total) VALUES ('{$card['ref']}', {$total})\n";

    $card['status'] = 'invoiced';
    return $card;
}

$b = [
    'ref'    => 'JC-2001',
    'status' => 'completed',
    'lines'  => [['description' => 'Diagnostic, 0.5h', 'cents' => 4500]],
];
$b = invoiceJobCard($b);
echo "  Status: {$b['status']}\n";

echo "\n  The rule now exists, in one place, and reads clearly.\n";

echo "\n  Now the same rule, from a second entry point:\n";
$c = ['ref' => 'JC-2002', 'status' => 'in_progress', 'lines' => []];
echo "  [bulk-invoice cron] UPDATE job_cards SET status='invoiced' WHERE age > 30\n";
$c['status'] = 'invoiced';
echo "  Status: {$c['status']}  ← the cron never called invoiceJobCard()\n";
echo "\n  Nothing forced it to. The rule lives in a function, and a function only\n";
echo "  protects the callers who remember it exists.\n";


// ═══════════════════════════════════════════════════════════════════════════
// OPTION C — Tactical DDD
// The rule lives in an object that refuses to break it.
// ═══════════════════════════════════════════════════════════════════════════
section('Option C — Tactical DDD');

final class CannotInvoiceIncompleteJobCard extends DomainException
{
    public static function inStatus(string $reference, string $status): self
    {
        return new self("Job card {$reference} is '{$status}', not 'completed'.");
    }
}

enum DddStatus: string
{
    case InProgress = 'in_progress';
    case Completed  = 'completed';
    case Invoiced   = 'invoiced';
}

final readonly class DddLine
{
    public function __construct(
        public string $description,
        public int    $cents,
    ) {
        if ($cents < 0) {
            throw new InvalidArgumentException('A line may not be negative.');
        }
    }
}

final class DddJobCard
{
    /** @var list<DddLine> */
    private array $lines = [];

    private DddStatus $status = DddStatus::InProgress;

    public function __construct(private readonly string $reference) {}

    public function addLine(DddLine $line): void
    {
        if ($this->status === DddStatus::Invoiced) {
            throw new DomainException("Job card {$this->reference} is invoiced. Lines are fixed.");
        }
        $this->lines[] = $line;
    }

    public function complete(): void
    {
        $this->status = DddStatus::Completed;
    }

    public function invoice(): void
    {
        if ($this->status !== DddStatus::Completed) {
            throw CannotInvoiceIncompleteJobCard::inStatus($this->reference, $this->status->value);
        }
        $this->status = DddStatus::Invoiced;
    }

    public function totalCents(): int
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += $line->cents;
        }
        return $total;
    }

    public function status(): DddStatus
    {
        return $this->status;
    }
}

$d = new DddJobCard('JC-3001');
$d->addLine(new DddLine('Diagnostic, 0.5h', 4500));
$d->complete();
$d->invoice();
echo "  Status: {$d->status()->value}   Total: " . money($d->totalCents()) . "\n";

echo "\n  Now every misuse from the first two options, tried again:\n\n";

$attempts = [
    'invoice without completing' => static function (): void {
        (new DddJobCard('JC-3002'))->invoice();
    },
    'add a line after invoicing' => static function (): void {
        $jc = new DddJobCard('JC-3003');
        $jc->complete();
        $jc->invoice();
        $jc->addLine(new DddLine('Late part', 1000));
    },
    'a negative line'            => static function (): void {
        new DddLine('Mystery discount', -5000);
    },
];

foreach ($attempts as $label => $attempt) {
    try {
        $attempt();
        echo "  [!!] {$label} — SUCCEEDED. That is a hole in the model.\n";
    } catch (Throwable $e) {
        echo "  [ok] {$label}\n";
        echo "       refused by " . $e::class . "\n";
    }
}

echo "\n  There is no bulk-invoice cron that can bypass this, because there is no\n";
echo "  way to set the status other than calling invoice().\n";


// ═══════════════════════════════════════════════════════════════════════════
// THE BILL
// ═══════════════════════════════════════════════════════════════════════════
section('The bill, so far');

$rows = [
    ['Option',              'Types', 'What stops a bad invoice'],
    ['A — Active Record',   '1',     'nothing'],
    ['B — Transaction script', '1 fn', 'the callers who remember'],
    ['C — Tactical DDD',    '4',     'the object itself'],
];

foreach ($rows as $i => $row) {
    printf("  %-26s %-7s %s\n", $row[0], $row[1], $row[2]);
    if ($i === 0) {
        echo '  ' . str_repeat('-', 64) . "\n";
    }
}

echo "\n";
echo "  Four types instead of one, for one rule. On this evidence alone, option C\n";
echo "  is not obviously worth it — and if this were the whole story, it would not\n";
echo "  be. A single rule does not justify a domain layer.\n\n";
echo "  Example 02 changes the rule and re-runs all three. That is where the\n";
echo "  arithmetic starts to move.\n\n";
