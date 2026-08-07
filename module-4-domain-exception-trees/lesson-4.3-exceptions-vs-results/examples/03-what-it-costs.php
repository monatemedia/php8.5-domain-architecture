<?php
declare(strict_types=1);

/**
 * Example 03 — What each style costs, measured
 * =============================================
 *
 * Part 1: #[\NoDiscard] catching the characteristic bug. This part prints a
 *         real PHP warning on purpose.
 * Part 2: what #[\NoDiscard] does NOT catch.
 * Part 3: the price of a backtrace, timed.
 * Part 4: the happy path, timed — where the cost is not.
 * Part 5: the case where it genuinely hurts, and the right answer to it.
 *
 * REQUIRES PHP 8.5 for the warning in part 1. On 8.4 the attribute parses and
 * does nothing, and part 1 silently proves the opposite of its point — which is
 * itself the bug verify.php's own #[\NoDiscard] probe once had.
 *
 *     php 03-what-it-costs.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

final class Decision
{
    /** @param list<string> $reasons */
    private function __construct(public readonly array $reasons) {}

    #[\NoDiscard('A Decision that is not read has decided nothing.')]
    public static function allowed(): self
    {
        return new self([]);
    }

    #[\NoDiscard('A Decision that is not read has decided nothing.')]
    public static function refusedBecause(string ...$reasons): self
    {
        return new self(array_values($reasons));
    }

    public function isAllowed(): bool
    {
        return $this->reasons === [];
    }
}

final class Thing
{
    public function __construct(private readonly bool $ready) {}

    #[\NoDiscard('A Decision that is not read has decided nothing.')]
    public function checkCanShip(): Decision
    {
        return $this->ready
            ? Decision::allowed()
            : Decision::refusedBecause('not-ready', 'no-address');
    }
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — The bug the attribute exists for
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — the discarded guard (this prints a real warning)');

echo "  The code below reads like a guard and is a no-op:\n\n";
echo "      \$thing->checkCanShip();     // <- discarded\n";
echo "      \$thing->ship();\n\n";
echo "  PHP 8.5 says:\n\n";

$thing = new Thing(false);

// DELIBERATE. The warning below is the output of this example, not a defect.
$thing->checkCanShip();

echo "\n  Without the attribute that line is silent, and the reviewer's eye\n";
echo "  reads it as a check because it is shaped like one. It is the discarded\n";
echo "  wither from Lesson 1.2 in different clothes.\n\n";
echo "  This is the strongest argument AGAINST result types — an exception\n";
echo "  cannot be ignored by accident and this can — and #[\\NoDiscard] is\n";
echo "  most of the answer to it.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — Where the attribute does not reach
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — "most of the answer", not all of it');

echo "  Assigned and never read — no warning, same bug:\n\n";

$ignored = $thing->checkCanShip();

echo "      \$ignored = \$thing->checkCanShip();   // no warning\n";
echo "      \$thing->ship();\n\n";
echo "  Also: it is a WARNING, not an error. A codebase that already prints\n";
echo "  warnings has just hidden this one among them.\n\n";
echo "  Both gaps are closable — an unused-variable rule in your static\n";
echo "  analyser, and error_reporting set to fail the build. Neither is\n";
echo "  automatic, and it is worth knowing what you are relying on.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — What a backtrace costs
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — Refusal holds an exception, and exceptions capture traces');

$iterations = 20_000;

$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $value = ['code' => 'not-ready', 'message' => 'Not ready to ship.'];
}
$plainArray = (hrtime(true) - $start) / 1e6;

$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $value = new DomainException('Not ready to ship.');
}
$exception = (hrtime(true) - $start) / 1e6;

printf("  %-42s %8.2f ms\n", "{$iterations} x plain array", $plainArray);
printf("  %-42s %8.2f ms\n", "{$iterations} x new DomainException", $exception);
printf("  %-42s %8.1fx\n", 'ratio', $plainArray > 0 ? $exception / $plainArray : 0);

echo "\n  That multiple is why Refusal::from() taking a built exception is a\n";
echo "  real cost and not a stylistic preference. Building a refused Decision\n";
echo "  with two reasons builds two exceptions.\n\n";
echo "  Note the absolute numbers, though. Divide by {$iterations}: one\n";
echo "  exception is a fraction of a microsecond. For a request that refuses\n";
echo "  one operation, this is not a number anybody can measure.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 4 — Where the cost is not
// ═══════════════════════════════════════════════════════════════════════════
section('Part 4 — the happy path costs nothing');

$ready    = new Thing(true);
$notReady = new Thing(false);

$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $allowed = $ready->checkCanShip()->isAllowed();
}
$happy = (hrtime(true) - $start) / 1e6;

$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $allowed = $notReady->checkCanShip()->isAllowed();
}
$refused = (hrtime(true) - $start) / 1e6;

printf("  %-42s %8.2f ms\n", "{$iterations} x checkCanShip() -> allowed", $happy);
printf("  %-42s %8.2f ms\n", "{$iterations} x checkCanShip() -> refused", $refused);

echo "\n  The allowed path builds no exceptions at all, because there are no\n";
echo "  refusals to build them from. The cost arrives only when something is\n";
echo "  actually being refused — and at that point you were about to throw\n";
echo "  one anyway.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 5 — Where it genuinely hurts
// ═══════════════════════════════════════════════════════════════════════════
section('Part 5 — the report, and the right answer to it');

$fleet = array_map(static fn(int $i): Thing => new Thing($i % 3 === 0), range(1, 5_000));

$start   = hrtime(true);
$blocked = count(array_filter($fleet, static fn(Thing $t): bool => !$t->checkCanShip()->isAllowed()));
$report  = (hrtime(true) - $start) / 1e6;

printf("  \"How many of the 5,000 are blocked?\"\n\n");
printf("      answer     %d\n", $blocked);
printf("      cost       %.2f ms, and roughly %d exceptions built and discarded\n", $report, $blocked * 2);

echo "\n  This is the shape that hurts: a QUESTION asked in bulk, where every\n";
echo "  refusal constructs objects nobody will ever throw.\n\n";
echo "  The temptation is to redesign Refusal so it does not hold an\n";
echo "  exception. Do not. That reintroduces the drift from example 02, and\n";
echo "  trades a correctness property for milliseconds.\n\n";
echo "  The right answer is Lesson 3.3: a report is a READ MODEL. It asks the\n";
echo "  database how many rows are in a state, and never loads an aggregate at\n";
echo "  all — which is faster than any version of this by three orders of\n";
echo "  magnitude and was the correct design before the exceptions were an\n";
echo "  issue.\n\n";
echo "  RULE: when a domain pattern is too slow in bulk, check whether the\n";
echo "  bulk operation should have been touching the domain.\n\n";
