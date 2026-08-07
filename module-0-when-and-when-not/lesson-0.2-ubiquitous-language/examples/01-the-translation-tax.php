<?php
declare(strict_types=1);

/**
 * Example 01 — The translation tax
 * =================================
 *
 * Two implementations of the same workshop feature. Identical behaviour,
 * identical line count, different vocabulary.
 *
 * The point is not that one is prettier. It is that you can hand the second one
 * to the workshop manager and they can tell you it is wrong, and you cannot do
 * that with the first. Being correctable by a non-programmer is a functional
 * property, not a stylistic one.
 *
 *     php 01-the-translation-tax.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 70) . "\n  {$title}\n" . str_repeat('=', 70) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// VERSION 1 — invented vocabulary
// ═══════════════════════════════════════════════════════════════════════════
section('Version 1 — words nobody in the workshop uses');

final class JobCardRecordManager
{
    /** @var array<string, array{status:int, extra_flag:bool, cust:string}> */
    private array $records = [];

    public function createRecord(string $ref, string $cust): void
    {
        $this->records[$ref] = ['status' => 1, 'extra_flag' => false, 'cust' => $cust];
    }

    public function updateStatus(string $ref, int $status): void
    {
        $this->records[$ref]['status'] = $status;
    }

    public function setExtraFlag(string $ref, bool $flag): void
    {
        $this->records[$ref]['extra_flag'] = $flag;
    }

    public function processRecord(string $ref): bool
    {
        if ($this->records[$ref]['status'] !== 4) {
            return false;
        }
        if ($this->records[$ref]['extra_flag']) {
            return false;
        }
        $this->records[$ref]['status'] = 5;
        return true;
    }
}

$mgr = new JobCardRecordManager();
$mgr->createRecord('JC-1001', 'CUST-77');
$mgr->updateStatus('JC-1001', 4);
$mgr->setExtraFlag('JC-1001', true);

var_dump($mgr->processRecord('JC-1001'));

echo "\n  Now read that back to the workshop manager:\n\n";
echo "    \"We create a record, update the status to four, set the extra flag,\n";
echo "     and then processing returns false.\"\n\n";
echo "  They cannot tell you whether it is right, because nothing in that\n";
echo "  sentence is about their business. So they will not correct it, and the\n";
echo "  first person to find out it is wrong will be a customer.\n";


// ═══════════════════════════════════════════════════════════════════════════
// VERSION 2 — the workshop's own words
// ═══════════════════════════════════════════════════════════════════════════
section("Version 2 — the workshop's own words");

enum JobCardStatus: string
{
    case Booked                = 'booked';
    case InProgress            = 'in progress';
    case AwaitingAuthorisation = 'awaiting authorisation';
    case Completed             = 'completed';
    case Invoiced              = 'invoiced';
}

final class CannotInvoice extends DomainException {}

final class JobCard
{
    private JobCardStatus $status = JobCardStatus::Booked;
    private bool $additionalWorkAwaitingAuthorisation = false;

    public function __construct(
        private readonly string $reference,
        private readonly string $accountHolder,
    ) {}

    public function startWork(): void
    {
        $this->status = JobCardStatus::InProgress;
    }

    public function discoverAdditionalWork(string $description): void
    {
        $this->additionalWorkAwaitingAuthorisation = true;
        $this->status = JobCardStatus::AwaitingAuthorisation;
        echo "    [additional work found] {$description}\n";
    }

    public function accountHolderAuthorises(): void
    {
        $this->additionalWorkAwaitingAuthorisation = false;
        $this->status = JobCardStatus::InProgress;
    }

    public function complete(): void
    {
        if ($this->additionalWorkAwaitingAuthorisation) {
            throw new CannotInvoice(
                "Job card {$this->reference} has additional work awaiting authorisation."
            );
        }
        $this->status = JobCardStatus::Completed;
    }

    public function invoice(): void
    {
        if ($this->status !== JobCardStatus::Completed) {
            throw new CannotInvoice(
                "Job card {$this->reference} is {$this->status->value}, not completed."
            );
        }
        $this->status = JobCardStatus::Invoiced;
        echo "    [invoiced] {$this->reference} to {$this->accountHolder}\n";
    }

    public function status(): JobCardStatus
    {
        return $this->status;
    }
}

$jobCard = new JobCard('JC-1001', 'Northgate Couriers Ltd');
$jobCard->startWork();
$jobCard->discoverAdditionalWork('Rear brake discs below minimum thickness');

try {
    $jobCard->complete();
} catch (CannotInvoice $e) {
    echo "    [refused] " . $e->getMessage() . "\n";
}

$jobCard->accountHolderAuthorises();
$jobCard->complete();
$jobCard->invoice();

echo "\n  Status: {$jobCard->status()->value}\n";

echo "\n  Now read THAT back to the workshop manager:\n\n";
echo "    \"We start work, we find additional work, we refuse to complete until\n";
echo "     the account holder authorises it, and then we can invoice.\"\n\n";
echo "  That is a sentence they can agree or disagree with. If the workshop\n";
echo "  actually allows completion with unauthorised work written off, they will\n";
echo "  say so — in the meeting, not in an incident.\n";


// ═══════════════════════════════════════════════════════════════════════════
// THE COMPARISON
// ═══════════════════════════════════════════════════════════════════════════
section('The comparison');

$rows = [
    ['Version 1', 'Version 2'],
    ['createRecord()', 'new JobCard(...)  — "book in"'],
    ['updateStatus($ref, 4)', 'complete()'],
    ['setExtraFlag($ref, true)', 'discoverAdditionalWork($description)'],
    ['processRecord()', 'invoice()'],
    ['returns false', 'throws CannotInvoice, with the reason'],
    ['status 4, status 5', 'Completed, Invoiced'],
    ['$cust', '$accountHolder'],
];

foreach ($rows as $i => $row) {
    printf("  %-28s  %s\n", $row[0], $row[1]);
    if ($i === 0) {
        echo '  ' . str_repeat('-', 68) . "\n";
    }
}

echo "\n";
echo "  The last row is the one that costs the most. 'cust' will become\n";
echo "  'Customer', and Customer will quietly hold both the person who pays and\n";
echo "  the person who drops the vehicle off — which the business treats as two\n";
echo "  different people, because half the work is on company fleets.\n\n";
echo "  Nobody decided to merge those two concepts. It happened because the\n";
echo "  abbreviation had room for both.\n\n";
