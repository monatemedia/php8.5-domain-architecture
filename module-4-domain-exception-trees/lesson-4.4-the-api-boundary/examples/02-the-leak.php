<?php
declare(strict_types=1);

/**
 * Example 02 — Four boundaries, four leaks
 * =========================================
 *
 * Each one is a real pattern someone has shipped, each leaks something
 * different, and one detector catches all four.
 *
 * Part 1: the four boundaries.
 * Part 2: the detector, run over all of them.
 * Part 3: why the detector inspects DECODED VALUES and not JSON text.
 * Part 4: testing the detector, because a test that cannot fail is not a test.
 *
 *     php 02-the-leak.php
 */

/**
 * NAMESPACED ON PURPOSE. Boundary B below emits `$e::class`, and the whole
 * point of that leak is what a namespace looks like on the wire. In an example
 * written in the global namespace it would look harmless, and prove nothing.
 */

namespace Workshop;

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

interface CarriesContext extends \Throwable
{
    public function failureCode(): string;

    public function context(): array;
}

final class JobCardIsTerminal extends \DomainException implements CarriesContext
{
    public function __construct(private readonly string $jobCardId)
    {
        parent::__construct(sprintf(
            'Cannot add a line to job card %s: it is Invoiced, and its lines are fixed.',
            $jobCardId
        ));
    }

    public function failureCode(): string
    {
        return 'job-card-is-terminal';
    }

    public function context(): array
    {
        return ['jobCardId' => $this->jobCardId, 'jobCardStatus' => 'invoiced'];
    }
}

/** A driver exception, with everything a driver exception has. */
function aDriverFailure(): \Throwable
{
    return new \PDOException(
        'SQLSTATE[HY000] [2002] Connection refused in /var/www/app/src/Sqlite.php:88'
    );
}

/** A bug. */
function aBug(): \Throwable
{
    return new \TypeError('Argument #1 ($money) must be of type Workshop\Money, string given');
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — Four boundaries
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — four ways to build a body');

/** LEAK 1: "just for debugging". */
function boundaryA(\Throwable $e): array
{
    return ['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500,
            'detail' => $e->getMessage()];
}

/** LEAK 2: the class name as a machine-readable code. */
function boundaryB(\Throwable $e): array
{
    return ['type' => 'about:blank', 'title' => 'Error', 'status' => 500,
            'code' => $e::class];
}

/** LEAK 3: a trace behind a flag somebody left on. */
function boundaryC(\Throwable $e): array
{
    $debug = true;

    return ['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500]
        + ($debug ? ['trace' => explode("\n", $e->getTraceAsString())[0]] : []);
}

/** No leak. */
function boundaryD(\Throwable $e): array
{
    if (!$e instanceof CarriesContext) {
        return ['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500];
    }

    return [
        'type'   => 'https://workshop.example/problems/' . $e->failureCode(),
        'title'  => 'Job card is closed',
        'status' => 409,
        'detail' => $e->getMessage(),
    ] + $e->context();
}

$boundaries = [
    'A: detail = getMessage()' => boundaryA(...),
    'B: code = $e::class'      => boundaryB(...),
    'C: trace behind a flag'   => boundaryC(...),
    'D: only CarriesContext'   => boundaryD(...),
];

foreach ($boundaries as $label => $build) {
    printf("  %-28s %s\n", $label, json_encode($build(aDriverFailure()), JSON_UNESCAPED_SLASHES));
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — One detector
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — the detector');

const FORBIDDEN = [
    '\\'             => 'a namespace separator',
    'Infrastructure' => 'an infrastructure namespace',
    '.php'           => 'a file name',
    '/var/'          => 'an absolute path',
    'SQLSTATE'       => 'a driver error code',
    'Exception'      => 'a class name',
    '#0 '            => 'a stack frame',
];

function stringsIn(array $body): array
{
    $found = [];

    array_walk_recursive($body, static function (mixed $value, string|int $key) use (&$found): void {
        $found[] = (string) $key;

        if ($value === null || is_scalar($value)) {
            $found[] = (string) $value;
        }
    });

    return $found;
}

function leakIn(array $body): ?string
{
    foreach (stringsIn($body) as $string) {
        foreach (FORBIDDEN as $needle => $why) {
            if (str_contains($string, $needle)) {
                return sprintf('%s (%s)', $needle, $why);
            }
        }
    }

    return null;
}

$throwables = [
    'driver failure' => aDriverFailure(),
    'a bug'          => aBug(),
    'domain failure' => new JobCardIsTerminal('JC-1042'),
];

printf("  %-28s", '');
foreach (array_keys($throwables) as $label) {
    printf('%-22s', $label);
}
echo "\n  " . str_repeat('-', 94) . "\n";

foreach ($boundaries as $label => $build) {
    printf('  %-28s', $label);

    foreach ($throwables as $throwable) {
        $leak = leakIn($build($throwable));
        printf('%-22s', $leak ?? 'clean');
    }

    echo "\n";
}

echo "\n  Boundary D is clean on all three, including the domain failure —\n";
echo "  because Lesson 4.2 moved every message onto a class in Workshop\\,\n";
echo "  and those messages name job cards, not files.\n\n";
echo "  Note boundary B leaks on the DOMAIN failure too. `\$e::class` is\n";
echo "  Workshop\\JobCardIsTerminal, and a client that switches on it now has\n";
echo "  your namespace layout in its codebase. That is why the mapper switches\n";
echo "  on failureCode().\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — Why decoded values
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — the detector that fires on correct output');

$innocent = [
    'type'   => 'https://workshop.example/problems/line-currency-mismatch',
    'title'  => 'Line currency does not match the job card',
    'status' => 422,
    'detail' => 'Job card JC-1042 is in EUR; the line "Front pads" is in GBP — check the line.',
];

$escaped   = json_encode($innocent);
$unescaped = json_encode($innocent, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

printf("  Two encodings of one innocent body:\n\n");
printf("      default flags     %s\n", substr((string) $escaped, 90, 60) . '...');
printf("      unescaped         %s\n", substr((string) $unescaped, 90, 60) . '...');

printf("\n  Backslashes in the JSON TEXT:   default %d, unescaped %d\n",
    substr_count((string) $escaped, '\\'),
    substr_count((string) $unescaped, '\\'));

printf("  Leak found in the VALUES:       %s\n", leakIn($innocent) ?? 'none');

echo "\n  A raw-text search for a backslash calls the first encoding a leak.\n";
echo "  Nothing is wrong with it: the message quotes a part name, and \\\" is\n";
echo "  how JSON writes a quote. Escaped unicode does the same for the em dash.\n\n";
echo "  A leak test that fires on correct output is a leak test somebody\n";
echo "  switches off. Testing the decoded values means the encoder's choices\n";
echo "  can neither raise a false alarm nor hide a real leak.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 4 — Testing the test
// ═══════════════════════════════════════════════════════════════════════════
section('Part 4 — a test that cannot fail is not a test');

$poisoned = [
    'type'   => 'about:blank',
    'title'  => 'Internal Server Error',
    'status' => 500,
    'detail' => 'Workshop\JobCard::invoice() failed in /var/www/src/JobCard.php',
];

printf("  detector on a poisoned body:  %s\n", leakIn($poisoned) ?? 'NOTHING — the detector is broken');
printf("  detector on a correct body:   %s\n", leakIn(boundaryD(aDriverFailure())) ?? 'none');

echo "\n  Both directions matter. A detector that never fires passes forever,\n";
echo "  and a detector that always fires gets deleted in a hurry on a Friday.\n\n";
echo "  Note also what is NOT in FORBIDDEN: the bare word 'Error'. The correct\n";
echo "  opaque title is 'Internal Server Error', which RFC 9457 asks for when\n";
echo "  type is about:blank. Adding it would have failed the right answer.\n\n";
