<?php
declare(strict_types=1);

/**
 * Example 01 — The same failure, four boundaries
 * ===============================================
 *
 * Part 1: catch (\Throwable) -> 500. Lesson 4.1's opening.
 * Part 2: a plain JSON error body. Better, and still unusable.
 * Part 3: RFC 9457, and what a client can now do with it.
 * Part 4: title vs detail, and the dashboard that stops working.
 *
 *     php 01-what-a-client-sees.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

function body(int $status, array $json): void
{
    printf("  HTTP/1.1 %d\n", $status);
    printf("  %s\n\n", json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/** The failure, as Lesson 4.2 built it. */
final class IllegalStatusTransition extends DomainException
{
    public function __construct(
        public readonly string $jobCardId,
        public readonly string $from,
        public readonly string $to,
        public readonly array $allowed,
    ) {
        parent::__construct(sprintf(
            'Job card %s is %s and cannot become %s. Legal from here: %s.',
            $jobCardId,
            ucwords(str_replace('_', ' ', $from)),
            ucwords(str_replace('_', ' ', $to)),
            implode(', ', array_map(
                static fn(string $s): string => ucwords(str_replace('_', ' ', $s)),
                $allowed
            ))
        ));
    }

    public function failureCode(): string
    {
        return 'illegal-status-transition';
    }

    public function context(): array
    {
        return [
            'jobCardId' => $this->jobCardId,
            'from'      => $this->from,
            'to'        => $this->to,
            'allowed'   => $this->allowed,
        ];
    }
}

$failure = new IllegalStatusTransition(
    'JC-1042',
    'in_progress',
    'invoiced',
    ['awaiting_authorisation', 'awaiting_parts', 'completed', 'cancelled']
);


// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — where Module 4 started');

body(500, ['error' => 'Internal Server Error']);

echo "  Nothing went wrong. The server understood the request, applied a rule\n";
echo "  the business wrote down, and refused. The client is told the server\n";
echo "  is broken.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — a plain JSON error, which most APIs stop at');

body(400, ['error' => $failure->getMessage()]);

echo "  Better: the status is at least 4xx and the message is true. Three\n";
echo "  things a client still cannot do:\n\n";
echo "  1. Tell this apart from any other 400 without parsing English.\n";
echo "  2. Know whether retrying with a fixed payload could work. (400 says\n";
echo "     'malformed'; this request parsed perfectly.)\n";
echo "  3. Build a UI from it — the legal transitions are in a sentence.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — RFC 9457');

$problem = [
    'type'     => 'https://workshop.example/problems/' . $failure->failureCode(),
    'title'    => 'Illegal status transition',
    'status'   => 409,
    'detail'   => $failure->getMessage(),
    'instance' => '/job-cards/JC-1042/invoice',
] + $failure->context() + ['kind' => 'state'];

echo "  Content-Type: application/problem+json\n";
body(409, $problem);

echo "  Now the client can:\n\n";
printf("      switch on            %s\n", $problem['type']);
printf("      group and alert on   \"%s\"\n", $problem['title']);
printf("      show the user        %s\n", substr((string) $problem['detail'], 0, 46) . '...');
printf("      enable buttons for   %s\n", implode(', ', $problem['allowed']));
printf("      decide on a retry    409 -> not until something else changes\n");

echo "\n  And every one of those fields came from context(), which Lesson 4.2\n";
echo "  built before there was anything to serialise it into.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Part 4 — title vs detail, and the dashboard');

echo "  A tempting 'improvement': put the job card in the title.\n\n";

$occurrences = [
    ['title' => 'Illegal status transition', 'id' => 'JC-1042'],
    ['title' => 'Illegal status transition', 'id' => 'JC-1043'],
    ['title' => 'Illegal status transition', 'id' => 'JC-1051'],
];

$correct = [];
$broken  = [];

foreach ($occurrences as $o) {
    $correct[$o['title']] = ($correct[$o['title']] ?? 0) + 1;

    $withId = sprintf('%s on job card %s', $o['title'], $o['id']);
    $broken[$withId] = ($broken[$withId] ?? 0) + 1;
}

echo "  Grouping by title, as written:\n";
foreach ($correct as $title => $count) {
    printf("      %-52s %d\n", $title, $count);
}

echo "\n  Grouping by title, with the id in it:\n";
foreach ($broken as $title => $count) {
    printf("      %-52s %d\n", $title, $count);
}

echo "\n  Three distinct 'problem types', each seen once. An alert on 'more\n";
echo "  than 50 of these an hour' can never fire, and it will look like it is\n";
echo "  working.\n\n";
echo "  RULE: title describes the KIND and never changes. detail describes\n";
echo "  THIS ONE and always does. The id belongs in detail and in an\n";
echo "  extension member, which is where it already is.\n\n";
