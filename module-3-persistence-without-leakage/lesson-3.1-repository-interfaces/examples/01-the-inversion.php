<?php
declare(strict_types=1);

/**
 * Example 01 — Which way the arrow points
 * ========================================
 *
 * Part 1: the domain depends on a repository. Ordinary, and it is what every
 *         framework tutorial teaches.
 * Part 2: the domain declares an interface; infrastructure implements it.
 * Part 3: the difference, measured by what each version needs in order to run.
 *
 *     php 01-the-inversion.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — The domain depends on infrastructure
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — The usual arrangement');

/** Infrastructure. It cannot exist without a connection. */
final class SqlJobCardRepository
{
    public function __construct(private readonly PDO $connection) {}

    public function find(string $id): array
    {
        return ['id' => $id, 'status' => 'in_progress'];
    }
}

/** Domain... except it holds infrastructure. */
final class InvoicingServiceV1
{
    public function __construct(private readonly SqlJobCardRepository $repository) {}

    public function describe(string $id): string
    {
        $row = $this->repository->find($id);

        return "{$row['id']} is {$row['status']}";
    }
}

echo "  To construct InvoicingServiceV1 you need:\n\n";
echo "      new InvoicingServiceV1(new SqlJobCardRepository(new PDO(...)))\n\n";
echo "  ...so you need a database. To run a unit test about invoicing rules,\n";
echo "  you need a database. To construct one in a fixture, you need a\n";
echo "  database.\n";

echo "\n  Let us try, without one:\n\n";
try {
    $service = new InvoicingServiceV1(new SqlJobCardRepository(new PDO('nosuchdriver:host=nowhere')));
    echo "  [!!] constructed\n";
} catch (Throwable $e) {
    echo "  [failed] " . $e::class . "\n";
    echo "           " . substr($e->getMessage(), 0, 60) . "...\n";
}

echo "\n  Note WHAT failed: not the invoicing rule, which was never reached.\n";
echo "  The object could not be built.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — The domain declares; infrastructure implements
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — The inversion');

/** DOMAIN. An interface, in the domain's own words. No imports, no PDO. */
interface JobCardsPort
{
    public function get(string $id): JobCardRecord;
}

/** DOMAIN. */
final readonly class JobCardRecord
{
    public function __construct(
        public string $id,
        public string $status,
    ) {}
}

/** DOMAIN. Depends on the interface it declared, and on nothing else. */
final class InvoicingServiceV2
{
    public function __construct(private readonly JobCardsPort $jobCards) {}

    public function describe(string $id): string
    {
        $jobCard = $this->jobCards->get($id);

        return "{$jobCard->id} is {$jobCard->status}";
    }
}

/** INFRASTRUCTURE. It imports the domain. The domain does not import it. */
final class InMemoryJobCardsAdapter implements JobCardsPort
{
    /** @param array<string, JobCardRecord> $records */
    public function __construct(private readonly array $records) {}

    public function get(string $id): JobCardRecord
    {
        return $this->records[$id] ?? throw new DomainException("No job card {$id}.");
    }
}

$service = new InvoicingServiceV2(new InMemoryJobCardsAdapter([
    'JC-1001' => new JobCardRecord('JC-1001', 'completed'),
]));

echo "  " . $service->describe('JC-1001') . "\n";

echo "\n  Constructed with an array. No database, no connection string, no\n";
echo "  waiting. The invoicing rule can be exercised in microseconds.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — What each one needs
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — Measured');

$comparison = [
    ['To construct the service',       'a PDO connection',        'an array'],
    ['To run one test',                'a live database',         'nothing'],
    ['Time to construct',              'a network round trip',    'microseconds'],
    ['Domain imports',                 'PDO, the SQL repository', 'nothing'],
    ['To swap the storage',            'edit the domain',         'pass a different adapter'],
    ['If the DB is down, the rule',    'cannot be exercised',     'still works'],
];

printf("  %-30s %-26s %s\n", '', 'Depends on infra', 'Inverted');
echo '  ' . str_repeat('-', 74) . "\n";
foreach ($comparison as [$what, $before, $after]) {
    printf("  %-30s %-26s %s\n", $what, $before, $after);
}

echo "\n  The last row is the one that matters and it is easy to skim past.\n";
echo "  A business rule that cannot be exercised without a database is a rule\n";
echo "  nobody will exercise often — so it will be tested rarely, changed\n";
echo "  nervously, and eventually worked around.\n";

echo "\n  WHERE THE INTERFACE LIVES IS THE WHOLE TRICK.\n\n";
echo "  If JobCardsPort lived beside the SQL implementation, in an\n";
echo "  infrastructure package, the domain would depend on that package to\n";
echo "  compile — and the arrow would point outward again, with an interface\n";
echo "  in the middle making it look inverted.\n\n";
echo "  That is the most common way this is got wrong, because everything\n";
echo "  LOOKS right: there is an interface, there is a fake, the tests are\n";
echo "  fast. The only thing wrong is which directory one file is in.\n\n";
