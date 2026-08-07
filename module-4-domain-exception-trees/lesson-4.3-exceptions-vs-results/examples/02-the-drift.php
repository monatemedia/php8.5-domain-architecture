<?php
declare(strict_types=1);

/**
 * Example 02 — Drift, built deliberately and then made impossible
 * ================================================================
 *
 * Part 1: two implementations of the same rules. They agree. Everybody ships.
 * Part 2: a new rule arrives. One of them gets it.
 * Part 3: what that does to the caller who did the responsible thing.
 * Part 4: the same new rule, on the version where drift cannot happen.
 *
 *     php 02-the-drift.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

/** @param list<string> $states */
function agreementTable(array $states, callable $ask, callable $act): int
{
    $mismatches = 0;

    printf("  %-24s %-12s %-16s %s\n", 'state', 'ask says', 'act does', '');
    printf("  %s\n", str_repeat('-', 68));

    foreach ($states as $state) {
        $allowed = $ask($state);
        $threw   = false;

        try {
            $act($state);
        } catch (Throwable) {
            $threw = true;
        }

        $agree = $allowed === !$threw;
        $mismatches += $agree ? 0 : 1;

        printf(
            "  %-24s %-12s %-16s %s\n",
            $state,
            $allowed ? 'may' : 'may not',
            $threw ? 'refused' : 'succeeded',
            $agree ? '' : '<-- MISMATCH'
        );
    }

    return $mismatches;
}

$states = ['booked', 'in_progress', 'in_progress_unpaid', 'completed'];


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — Two implementations, agreeing
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — the honest version: two lists that happen to match');

final class DriftingJobCardV1
{
    public function __construct(private readonly string $state) {}

    public function checkCanComplete(): bool
    {
        return $this->state === 'in_progress' || $this->state === 'in_progress_unpaid';
    }

    public function complete(): void
    {
        if ($this->state !== 'in_progress' && $this->state !== 'in_progress_unpaid') {
            throw new DomainException('wrong status');
        }
    }
}

$mismatches = agreementTable(
    $states,
    static fn(string $s): bool => (new DriftingJobCardV1($s))->checkCanComplete(),
    static function (string $s): void { (new DriftingJobCardV1($s))->complete(); }
);

printf("\n  %d mismatch(es). Tests green. Ship it.\n", $mismatches);


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — A new rule, added in one place
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — "a job card with an unpaid balance may not be completed"');

echo "  Somebody adds the guard to complete(), where the bug report pointed.\n";
echo "  They do not know checkCanComplete() exists.\n\n";

final class DriftingJobCardV2
{
    public function __construct(private readonly string $state) {}

    public function checkCanComplete(): bool
    {
        // UNCHANGED.
        return $this->state === 'in_progress' || $this->state === 'in_progress_unpaid';
    }

    public function complete(): void
    {
        if ($this->state !== 'in_progress' && $this->state !== 'in_progress_unpaid') {
            throw new DomainException('wrong status');
        }

        // NEW.
        if ($this->state === 'in_progress_unpaid') {
            throw new DomainException('unpaid balance');
        }
    }
}

$mismatches = agreementTable(
    $states,
    static fn(string $s): bool => (new DriftingJobCardV2($s))->checkCanComplete(),
    static function (string $s): void { (new DriftingJobCardV2($s))->complete(); }
);

printf("\n  %d mismatch(es).\n", $mismatches);


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — Who this hurts
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — the caller who did the responsible thing');

echo "  A careless caller:\n\n";
echo "      \$jobCard->complete();          // throws. Handled. Correct message.\n\n";
echo "  A careful caller:\n\n";
echo "      if (\$jobCard->checkCanComplete()) {\n";
echo "          \$jobCard->complete();      // <- throws anyway\n";
echo "      }\n\n";

$careful = new DriftingJobCardV2('in_progress_unpaid');

if ($careful->checkCanComplete()) {
    try {
        $careful->complete();
        echo "      completed\n";
    } catch (Throwable $e) {
        printf("      the check said yes and the call threw: %s\n", $e->getMessage());
    }
}

echo "\n  The careful caller's error handling is thinner, precisely BECAUSE it\n";
echo "  checked first — it has an `if` where the careless one has a `catch`.\n";
echo "  So the exception escapes further, and the user sees a 500 for a rule\n";
echo "  the system understands perfectly.\n\n";
echo "  Checking made it worse. That is what makes drift the failure mode of\n";
echo "  this pattern rather than merely a bug in it.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 4 — One list
// ═══════════════════════════════════════════════════════════════════════════
section('Part 4 — the same new rule, where drift cannot happen');

final class OneListJobCard
{
    public function __construct(private readonly string $state) {}

    /** @return list<string> the ONLY list of reasons in this class */
    private function refusals(): array
    {
        $refusals = [];

        if ($this->state !== 'in_progress' && $this->state !== 'in_progress_unpaid') {
            $refusals[] = 'wrong status';
        }

        if ($this->state === 'in_progress_unpaid') {
            $refusals[] = 'unpaid balance';
        }

        return $refusals;
    }

    public function checkCanComplete(): bool
    {
        return $this->refusals() === [];
    }

    /** @return list<string> */
    public function whyNot(): array
    {
        return $this->refusals();
    }

    public function complete(): void
    {
        $refusals = $this->refusals();

        if ($refusals !== []) {
            throw new DomainException($refusals[0]);
        }
    }
}

$mismatches = agreementTable(
    $states,
    static fn(string $s): bool => (new OneListJobCard($s))->checkCanComplete(),
    static function (string $s): void { (new OneListJobCard($s))->complete(); }
);

printf("\n  %d mismatch(es), and the new rule was added ONCE.\n\n", $mismatches);

printf("  And the ask-path now gives something the act-path cannot:\n\n");
printf("      in_progress_unpaid -> %s\n", implode(' + ', (new OneListJobCard('in_progress_unpaid'))->whyNot()));
printf("      booked             -> %s\n", implode(' + ', (new OneListJobCard('booked'))->whyNot()));

echo "\n  Note this is not discipline, a convention, or a code review rule. The\n";
echo "  two paths cannot disagree because there is only one list, and a\n";
echo "  developer adding a rule has nowhere else to put it.\n\n";
echo "  The reference implementation writes complete() as:\n\n";
echo "      \$this->checkCanComplete()->orThrow();\n\n";
echo "  which is the same idea with the Decision doing the work.\n\n";
echo "  The drift test in checks.php still runs both paths over every\n";
echo "  reachable state — because 'there is only one list' is a property of\n";
echo "  today's implementation, and someone can add an `if` above that line\n";
echo "  next week. The test is what would notice.\n\n";
