<?php
declare(strict_types=1);

/**
 * Example 02 — The same repository, in two languages
 * ===================================================
 *
 * Both interfaces answer the same questions. One is written in the workshop's
 * words; the other is written in the database's.
 *
 * Read every method name out loud. That is the entire test, and it is more
 * reliable than any rule you could write down.
 *
 *     php 02-vocabulary.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
section("Written in the database's words");

$leaky = [
    'findBy(array $criteria): array'                 => 'a query language, wearing a method name',
    'findWhereStatusEquals(int $status): array'      => 'names a column, and a magic number',
    'findAllByVehicleIdAndStatusIn(...): array'      => 'the WHERE clause, spelled out in camelCase',
    'query(): QueryBuilder'                          => 'hands the database to the caller',
    'getConnection(): PDO'                           => 'no comment',
    'hydrate(array $row): JobCard'                   => 'the caller now knows there are rows',
    'flush(): void'                                  => "somebody else's persistence model, exposed",
];

foreach ($leaky as $signature => $problem) {
    printf("  %-46s %s\n", $signature, $problem);
}

echo "\n  Read the third one aloud: \"find all by vehicle id and status in.\"\n";
echo "  Nobody in the workshop has ever said that sentence.\n";

echo "\n  ONE OF THESE IS ENOUGH. The moment a caller can express a query, the\n";
echo "  storage model is in the caller's head, and the interface has become a\n";
echo "  thin wrapper over the database. Nobody will ever put it back, because\n";
echo "  by then forty call sites depend on it.\n";


// ═══════════════════════════════════════════════════════════════════════════
section("Written in the workshop's words");

$clean = [
    'add(JobCard $jobCard): void'                    => '"put this job card in"',
    'get(JobCardId $id): JobCard'                    => '"give me this job card"',
    'contains(JobCardId $id): bool'                  => '"do we have this job card?"',
    'forVehicle(VehicleId $id): array'               => '"this vehicle\'s job cards"',
    'awaitingAuthorisation(): array'                 => '"the ones waiting on an account holder"',
    'remove(JobCardId $id): void'                    => '"take this job card out"',
];

foreach ($clean as $signature => $sentence) {
    printf("  %-46s %s\n", $signature, $sentence);
}

echo "\n  Every one of those is a sentence the service manager would recognise,\n";
echo "  which means they can READ IT BACK TO YOU AND SAY IT IS WRONG. Rule 4,\n";
echo "  applied to an interface rather than to an entity.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('The discipline, and what it costs');

echo "  Every query the domain needs must be a NAMED METHOD.\n\n";
echo "  That is uncomfortable, and the discomfort is the point. Somebody asks\n";
echo "  for 'job cards over 500 that are still open, for fleet accounts' and\n";
echo "  there is no findBy() to reach for. You have to name it — and naming it\n";
echo "  forces the question of what it is actually FOR.\n\n";

$requests = [
    'Job cards over 500, still open, fleet accounts only' =>
        'is this a business concept with a name, or one screen\'s filter? If the'
        . ' second, it is a READ MODEL and Lesson 3.3 is about it.',
    'Job cards for this vehicle' =>
        'service history. A real concept. forVehicle().',
    'Job cards awaiting authorisation' =>
        'a question the manager asks every morning. awaitingAuthorisation().',
    'Job cards where updated_at > ?' =>
        'not a business question at all. Somebody is building a sync job, and it'
        . ' does not belong on this interface.',
];

foreach ($requests as $request => $answer) {
    echo "  \"{$request}\"\n";
    foreach (explode("\n", wordwrap($answer, 66)) as $line) {
        echo "      {$line}\n";
    }
    echo "\n";
}

echo "  Note that two of those four turned out NOT to belong here. The\n";
echo "  discipline did not just name things — it separated domain questions\n";
echo "  from reporting and plumbing, which is work that findBy() would have\n";
echo "  hidden forever.\n\n";
