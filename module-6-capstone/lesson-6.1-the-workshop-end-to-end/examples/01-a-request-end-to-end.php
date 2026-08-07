<?php
declare(strict_types=1);

/**
 * Example 01 — One request, every layer, and which lesson each hop came from
 * ===========================================================================
 *
 * No Slim here and no database. This traces the SHAPE of a request through the
 * architecture, so the wiring in the challenge has something to be a wiring OF.
 *
 *     php 01-a-request-end-to-end.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

/**
 * @param list<array{layer:string, does:string, lesson:string}> $hops
 */
function trace(array $hops): void
{
    printf("  %-26s %-30s %s\n", 'layer', 'what it does', 'decided in');
    printf("  %s\n", str_repeat('-', 78));

    foreach ($hops as $hop) {
        printf("  %-26s %-30s %s\n", $hop['layer'], $hop['does'], $hop['lesson']);
    }
}


// ═══════════════════════════════════════════════════════════════════════════
section('POST /job-cards/JC-1042/invoice   (on a job card that is Booked)');

trace([
    ['layer' => 'ProblemDetailsMiddleware', 'does' => 'try { ... }',                    'lesson' => '4.4'],
    ['layer' => 'BodyParsingMiddleware',    'does' => 'parses nothing; no body',        'lesson' => 'Slim'],
    ['layer' => 'RoutingMiddleware',        'does' => 'matches, extracts {id}',         'lesson' => 'Slim'],
    ['layer' => 'JobCardsController',       'does' => 'calls one use case',             'lesson' => 'capstone'],
    ['layer' => 'InvoiceJobCard',           'does' => 'get, change, store',             'lesson' => 'capstone'],
    ['layer' => 'GuardedJobCards',          'does' => 'passes domain failures through', 'lesson' => '4.1'],
    ['layer' => 'SqliteJobCards',           'does' => 'SELECT, then hands rows on',     'lesson' => '3.4'],
    ['layer' => 'JobCardMapper',            'does' => 'rows -> reconstitute()',         'lesson' => '3.2'],
    ['layer' => 'JobCard',                  'does' => 'REFUSES. Booked -> Invoiced',    'lesson' => '2.1, I9'],
]);

echo "\n  The refusal now travels back UP the same stack, and every layer it\n";
echo "  passes does nothing to it:\n\n";

trace([
    ['layer' => 'JobCard',                  'does' => 'throws IllegalStatusTransition', 'lesson' => '4.2'],
    ['layer' => 'SqliteJobCards',           'does' => '(not involved)',                 'lesson' => ''],
    ['layer' => 'GuardedJobCards',          'does' => 'throw $e;  — same object',       'lesson' => '4.1'],
    ['layer' => 'InvoiceJobCard',           'does' => 'no catch. Nothing.',             'lesson' => 'capstone'],
    ['layer' => 'JobCardsController',       'does' => 'no catch. Nothing.',             'lesson' => 'capstone'],
    ['layer' => 'ProblemDetailsMiddleware', 'does' => 'catch -> mapper -> 409',         'lesson' => '4.4'],
]);

echo "\n  SIX LAYERS, ONE catch. That is the property worth noticing: a failure\n";
echo "  is handled where somebody can do something about it, and nowhere else.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('What the client gets');

$problem = [
    'type'      => 'https://workshop.example/problems/illegal-status-transition',
    'title'     => 'Illegal status transition',
    'status'    => 409,
    'detail'    => 'Job card JC-1042 is Booked and cannot become Invoiced. Legal from here: In Progress, Cancelled.',
    'instance'  => '/job-cards/JC-1042/invoice',
    'jobCardId' => 'JC-1042',
    'from'      => 'booked',
    'to'        => 'invoiced',
    'allowed'   => ['in_progress', 'cancelled'],
    'kind'      => 'state',
];

echo "  HTTP/1.1 409 Conflict\n";
echo "  Content-Type: application/problem+json\n\n";
echo '  ' . str_replace("\n", "\n  ", (string) json_encode($problem, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n";

$origins = [
    'type'      => '4.2 — failureCode(), declared per class',
    'title'     => '4.4 — the catalogue',
    'status'    => '4.4 — the catalogue; 409 because it is a WorkshopException',
    'detail'    => '4.2 — the message, built by the exception itself',
    'instance'  => 'capstone — the request path, which only the boundary knows',
    'jobCardId' => '4.2 — JobCardException, which holds the identity',
    'from/to'   => '4.2 — context(), enum VALUES not labels',
    'allowed'   => '2.1 — JobCardStatus::allowedNext(), read at throw time',
    'kind'      => '4.4 — which branch of the tree it came from',
];

echo "\n  Every field, and where it was decided:\n\n";

foreach ($origins as $field => $origin) {
    printf("      %-12s %s\n", $field, $origin);
}

echo "\n  The middleware assembled NONE of that. It called map() and wrote the\n";
echo "  result to a stream.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('The same request against a dead database');

trace([
    ['layer' => 'SqliteJobCards',           'does' => 'PDOException: no such table',   'lesson' => 'ext-pdo'],
    ['layer' => 'GuardedJobCards',          'does' => '-> StorageFailure, prev intact','lesson' => '4.1'],
    ['layer' => 'InvoiceJobCard',           'does' => 'no catch',                      'lesson' => 'capstone'],
    ['layer' => 'ProblemDetailsMiddleware', 'does' => 'not a CarriesContext -> 500',   'lesson' => '4.4'],
    ['layer' => 'UnexpectedFailureLog',     'does' => 'records it, in full',           'lesson' => 'capstone'],
]);

echo "\n  the client:    {\"type\":\"about:blank\",\"title\":\"Internal Server Error\",\"status\":500}\n";
echo "  the operator:  StorageFailure: Storage failed during get.\n";
echo "                   previous: PDOException: SQLSTATE[HY000]: no such table: job_cards\n\n";
echo "  Same request, same stack, and the only class that behaved differently\n";
echo "  is the one whose entire job is to know the difference.\n\n";
