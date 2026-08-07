<?php
declare(strict_types=1);

/**
 * Example 03 — The lifecycle belongs in one table
 * ================================================
 *
 * DOMAIN.md gives the job card's legal transitions exhaustively. Everything not
 * in that table must throw.
 *
 * Part 1 scatters the rules across the methods, which is what everybody writes
 * first and which is Lesson 1.4's problem wearing a lifecycle costume.
 * Part 2 puts them in one table on the enum, and then walks every possible
 * transition to prove the table is complete.
 *
 *     php 03-lifecycle-as-a-state-machine.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 78) . "\n  {$title}\n" . str_repeat('=', 78) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — Scattered
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — The rules scattered across the methods');

final class ScatteredJobCard
{
    private string $status = 'booked';

    public function startWork(): void
    {
        if ($this->status !== 'booked') {
            throw new DomainException("Cannot start work from '{$this->status}'.");
        }
        $this->status = 'in_progress';
    }

    public function complete(): void
    {
        if ($this->status !== 'in_progress') {
            throw new DomainException("Cannot complete from '{$this->status}'.");
        }
        $this->status = 'completed';
    }

    public function invoice(): void
    {
        if ($this->status !== 'completed') {
            throw new DomainException("Cannot invoice from '{$this->status}'.");
        }
        $this->status = 'invoiced';
    }

    /**
     * The bug. Read it and see how ordinary it looks.
     *
     * The author was thinking about the two states you obviously cannot cancel
     * from — and 'completed' is not one they thought of, because a completed
     * job card feels cancellable. DOMAIN.md says it is not: a completed job
     * card has had work done on it, and the way you undo that is a credit note,
     * which is a different operation with different accounting consequences.
     */
    public function cancel(): void
    {
        if ($this->status === 'invoiced') {
            throw new DomainException('Cannot cancel an invoiced job card.');
        }
        $this->status = 'cancelled';
    }

    public function status(): string
    {
        return $this->status;
    }
}

$scattered = new ScatteredJobCard();
$scattered->startWork();
$scattered->complete();
echo "  Status: {$scattered->status()}\n";

$scattered->cancel();
echo "  After cancel(): {$scattered->status()}\n";

echo "\n  A completed job card was cancelled. Work has been done on that\n";
echo "  vehicle, parts have been fitted, and the record now says the job never\n";
echo "  happened. Nothing will bill for it.\n";

echo "\n  Four methods, four separate conditions, and one of them is subtly\n";
echo "  wrong. Finding that by reading requires holding the whole transition\n";
echo "  table in your head while you read four unrelated method bodies — and\n";
echo "  the table is not written down anywhere in the class.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — One table
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — One table, on the enum');

enum JobCardStatus: string
{
    case Booked                = 'booked';
    case InProgress            = 'in_progress';
    case AwaitingAuthorisation = 'awaiting_authorisation';
    case AwaitingParts         = 'awaiting_parts';
    case Completed             = 'completed';
    case Invoiced              = 'invoiced';
    case Cancelled             = 'cancelled';

    /**
     * The transition table from DOMAIN.md, transcribed once.
     *
     * Read it beside that table. A business person can check this method; they
     * cannot check four scattered if-statements.
     *
     * Invoiced and Cancelled are terminal by having no successors. No special
     * case, no `isTerminal()` flag to keep in step — just an empty list.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Booked                => [self::InProgress, self::Cancelled],
            self::InProgress            => [self::AwaitingAuthorisation, self::AwaitingParts, self::Completed, self::Cancelled],
            self::AwaitingAuthorisation => [self::InProgress],
            self::AwaitingParts         => [self::InProgress],
            self::Completed             => [self::Invoiced],
            self::Invoiced              => [],
            self::Cancelled             => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedNext() === [];
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}

final class MachineJobCard
{
    private JobCardStatus $status = JobCardStatus::Booked;

    public function status(): JobCardStatus
    {
        return $this->status;
    }

    /**
     * One method. Every transition states which one it is attempting and lets
     * the table decide.
     */
    private function transitionTo(JobCardStatus $next): void
    {
        if (!$this->status->canTransitionTo($next)) {
            throw new DomainException(sprintf(
                '%s cannot become %s. Legal from here: %s.',
                $this->status->label(),
                $next->label(),
                $this->status->isTerminal()
                    ? 'nothing — it is terminal'
                    : implode(', ', array_map(
                        static fn(JobCardStatus $s): string => $s->label(),
                        $this->status->allowedNext()
                    ))
            ));
        }

        $this->status = $next;
    }

    public function startWork(): void  { $this->transitionTo(JobCardStatus::InProgress); }
    public function complete(): void   { $this->transitionTo(JobCardStatus::Completed); }
    public function invoice(): void    { $this->transitionTo(JobCardStatus::Invoiced); }
    public function cancel(): void     { $this->transitionTo(JobCardStatus::Cancelled); }
}

$machine = new MachineJobCard();
$machine->startWork();
$machine->complete();
echo "  Status: {$machine->status()->label()}\n\n";

try {
    $machine->cancel();
    echo "  [!!] Cancelled a completed job card.\n";
} catch (DomainException $e) {
    echo "  [ok] refused: " . $e->getMessage() . "\n";
}

echo "\n  The same mistake is now impossible to make, because there is no\n";
echo "  cancel-specific condition to get wrong. There is one table.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — Every transition, exhaustively
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — All 49 transitions, from the table');

$states = JobCardStatus::cases();

printf('  %-24s', 'from \\ to');
foreach ($states as $to) {
    printf('%-6s', substr($to->name, 0, 5));
}
echo "\n  " . str_repeat('-', 24 + count($states) * 6) . "\n";

$legal = 0;
foreach ($states as $from) {
    printf('  %-24s', $from->label());
    foreach ($states as $to) {
        $ok = $from->canTransitionTo($to);
        $legal += $ok ? 1 : 0;
        printf('%-6s', $ok ? ' yes' : ' .');
    }
    echo "\n";
}

$total = count($states) ** 2;
printf("\n  %d legal of %d possible. %d refusals, none of them written by hand.\n", $legal, $total, $total - $legal);

echo "\n  Two things to read off that grid:\n\n";
echo "    - The Invoiced and Cancelled rows are empty. Terminal, for free.\n";
echo "    - The diagonal is empty: no state can transition to itself. That is\n";
echo "      a decision, not an accident — completing an already-completed job\n";
echo "      card is a duplicate submission, and the model says so rather than\n";
echo "      silently succeeding.\n";

echo "\n  Compare with part 1, where 49 outcomes were governed by four\n";
echo "  hand-written conditions and nobody could see the grid.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('What the enum is NOT allowed to know');

echo "  The table above is about STATES only. Two rules in DOMAIN.md look like\n";
echo "  transition rules and are not:\n\n";
echo "    I8  a job card cannot be completed while additional work awaits\n";
echo "        authorisation\n";
echo "    I7  a job card cannot be invoiced unless it is Completed\n\n";
echo "  I7 IS in the table — Completed is the only state with Invoiced as a\n";
echo "  successor. I8 is not, and cannot be: the enum knows the current state\n";
echo "  and nothing else. Whether there is unauthorised work outstanding is a\n";
echo "  fact about the job card's CONTENTS.\n\n";
echo "  So I8 lives on the aggregate, checked before it asks the enum. This is\n";
echo "  Lesson 1.4's test again — can you decide the rule while holding only\n";
echo "  this object? The enum cannot. The aggregate can.\n\n";
