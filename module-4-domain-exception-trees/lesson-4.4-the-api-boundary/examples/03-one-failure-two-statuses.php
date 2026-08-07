<?php
declare(strict_types=1);

/**
 * Example 03 — The argument for httpStatus() on the exception, and what kills it
 * ==============================================================================
 *
 * Part 1: the case FOR putting the status in the domain. It is a good case.
 * Part 2: the same failure, two routes, two correct statuses.
 * Part 3: the second boundary, which is where it stops being one integer.
 * Part 4: what to do instead, and the test that answers the real objection.
 *
 *     php 03-one-failure-two-statuses.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — The case for
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — the argument, at its strongest');

echo "  \"The domain already decides that a missing job card and an illegal\n";
echo "   transition are different kinds of failure. That IS the tree. The\n";
echo "   boundary's table encodes the same distinction a second time, and two\n";
echo "   places encoding one decision drift. When they drift the domain is\n";
echo "   right and the table is stale. Put the status next to the rule.\"\n\n";
echo "      final class JobCardNotFound extends JobCardException\n";
echo "      {\n";
echo "          public const int HTTP_STATUS = 404;\n";
echo "      }\n\n";
echo "  That is not a weak argument. It is one integer, it removes a lookup,\n";
echo "  and it puts the answer where the question is.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — Two routes
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — the same failure, from two routes');

final class JobCardNotFound extends DomainException
{
    public function __construct(public readonly string $jobCardId)
    {
        parent::__construct(sprintf('No job card with id %s.', $jobCardId));
    }

    public function failureCode(): string
    {
        return 'job-card-not-found';
    }
}

$routes = [
    'GET /job-cards/JC-9999' => [
        'status' => 404,
        'why'    => 'the URL names a thing that is not there',
    ],
    'POST /invoices  {"jobCardId": "JC-9999"}' => [
        'status' => 422,
        'why'    => 'the URL is fine; the BODY names a thing that is not there',
    ],
];

foreach ($routes as $route => $answer) {
    printf("  %-42s -> %d\n", $route, $answer['status']);
    printf("  %-42s    %s\n\n", '', $answer['why']);
}

echo "  One exception. One failure code. Two correct answers.\n\n";
echo "  The status is about the REQUEST, and the exception does not know what\n";
echo "  was asked. It cannot: JobCardNotFound is raised inside a repository,\n";
echo "  which has never seen an HTTP request and should never see one.\n\n";
echo "  HTTP_STATUS = 404 would make the POST route wrong, and the only way\n";
echo "  to fix it is for the route to override — which is the table again,\n";
echo "  with an extra step.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — The second boundary
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — and then the queue consumer arrives');

$boundaries = [
    'HTTP API'       => ['needs' => 'httpStatus()',  'answer' => '404 / 422'],
    'gRPC service'   => ['needs' => 'grpcCode()',    'answer' => 'NOT_FOUND'],
    'Queue consumer' => ['needs' => 'retryable()',   'answer' => 'false — dead-letter it'],
    'CLI'            => ['needs' => 'exitCode()',    'answer' => '4'],
];

printf("  %-18s %-16s %s\n", 'boundary', 'would want', 'for JobCardNotFound');
printf("  %s\n", str_repeat('-', 68));

foreach ($boundaries as $name => $b) {
    printf("  %-18s %-16s %s\n", $name, $b['needs'], $b['answer']);
}

echo "\n  Four methods on the exception, three of which mean nothing to any\n";
echo "  given caller. The domain is now holding four opinions about transport\n";
echo "  and none of them is a fact about a workshop.\n\n";
echo "  It is one integer TODAY. That is the whole answer to part 1: the cost\n";
echo "  is not the integer, it is the precedent.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 4 — What to do instead
// ═══════════════════════════════════════════════════════════════════════════
section('Part 4 — the domain names it; each boundary decides what it means');

const CATALOGUE = [
    'job-card-not-found'        => 404,
    'illegal-status-transition' => 409,
    'missing-work-description'  => 422,
];

/** @param array<string, int> $overrides */
function statusFor(string $code, array $overrides = []): int
{
    return $overrides[$code] ?? CATALOGUE[$code] ?? 500;
}

$failure = new JobCardNotFound('JC-9999');

printf("  GET  route:  %d\n", statusFor($failure->failureCode()));
printf("  POST route:  %d   (\$mapper->withStatusFor('job-card-not-found', 422))\n",
    statusFor($failure->failureCode(), ['job-card-not-found' => 422]));

echo "\n  The catalogue is a DEFAULT. A route that knows better says so, and\n";
echo "  the domain is not consulted about HTTP at all.\n";

section('And the objection from part 1 — drift — is answered by a test');

$everyFailureCode = [
    'job-card-not-found',
    'illegal-status-transition',
    'missing-work-description',
    'outstanding-authorisations',   // <- imagine this is new, and nobody told the boundary
];

$unmapped = array_values(array_filter(
    $everyFailureCode,
    static fn(string $code): bool => statusFor($code) === 500
));

printf("  Codes in the tree:        %d\n", count($everyFailureCode));
printf("  Codes with no mapping:    %s\n", $unmapped === [] ? 'none' : implode(', ', $unmapped));
printf("  Would the build fail?     %s\n", $unmapped === [] ? 'no' : 'YES');

echo "\n  That is the check in checks.php, in four lines: walk every leaf of\n";
echo "  the tree and assert each maps to something other than 500.\n\n";
echo "  The worry in part 1 was real — a table in infrastructure CAN drift\n";
echo "  from the domain it describes. The answer is not to move the table\n";
echo "  into the domain. It is to make drift fail the build.\n\n";
echo "  Note what happens meanwhile, before anybody notices: an unmapped\n";
echo "  domain failure becomes an opaque 500. That is a 4xx reported as a\n";
echo "  5xx, which is the mistake this whole module opened with — and it is\n";
echo "  still the right default, because the alternative is guessing. A wrong\n";
echo "  4xx tells a client something false with confidence. A 500 says we do\n";
echo "  not know, which is true.\n\n";
