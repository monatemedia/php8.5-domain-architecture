<?php
declare(strict_types=1);

/**
 * Example 01 — Ask, or act
 * =========================
 *
 * Part 1: four callers, one operation, and what each of them needs.
 * Part 2: the same four served by exceptions only.
 * Part 3: the same four served by results only.
 * Part 4: both, sharing one list of rules.
 *
 *     php 01-ask-or-act.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
// A miniature of the workshop
// ═══════════════════════════════════════════════════════════════════════════

interface WorkshopFailure extends Throwable {}

abstract class WorkshopException extends DomainException implements WorkshopFailure
{
    public function __construct(string $message, public readonly string $failureCode)
    {
        parent::__construct($message);
    }
}

final class OutstandingWork extends WorkshopException
{
    public function __construct(public readonly array $descriptions)
    {
        parent::__construct(
            sprintf('%d item(s) of work are still awaiting approval.', count($descriptions)),
            'outstanding-authorisations'
        );
    }
}

final class WrongStatus extends WorkshopException
{
    public function __construct(public readonly string $from)
    {
        parent::__construct(
            sprintf('A job card that is %s cannot be completed.', $from),
            'illegal-status-transition'
        );
    }
}

final class Refusal
{
    private function __construct(
        public readonly string $code,
        public readonly string $message,
        private readonly WorkshopException $failure,
    ) {}

    public static function from(WorkshopException $failure): self
    {
        return new self($failure->failureCode, $failure->getMessage(), $failure);
    }

    public function raise(): never
    {
        throw $this->failure;
    }
}

final class Decision
{
    /** @param list<Refusal> $refusals */
    private function __construct(public readonly array $refusals) {}

    public static function allowed(): self { return new self([]); }

    public static function refusedBecause(Refusal ...$r): self { return new self(array_values($r)); }

    public function isAllowed(): bool { return $this->refusals === []; }

    /** @return list<string> */
    public function codes(): array
    {
        return array_map(static fn(Refusal $r): string => $r->code, $this->refusals);
    }

    /** @return list<string> */
    public function messages(): array
    {
        return array_map(static fn(Refusal $r): string => $r->message, $this->refusals);
    }

    public function orThrow(): void
    {
        if ($this->refusals !== []) {
            $this->refusals[0]->raise();
        }
    }
}

final class JobCard
{
    /** @param list<string> $pending */
    public function __construct(
        public readonly string $id,
        private string $status,
        private array $pending = [],
    ) {}

    public function status(): string { return $this->status; }

    /** THE ONE LIST OF RULES. Both styles read it. */
    public function checkCanComplete(): Decision
    {
        $refusals = [];

        if ($this->pending !== []) {
            $refusals[] = Refusal::from(new OutstandingWork($this->pending));
        }

        if ($this->status !== 'in_progress') {
            $refusals[] = Refusal::from(new WrongStatus($this->status));
        }

        return $refusals === [] ? Decision::allowed() : Decision::refusedBecause(...$refusals);
    }

    public function complete(): void
    {
        $this->checkCanComplete()->orThrow();

        $this->status = 'completed';
    }
}

function theAwkwardOne(): JobCard
{
    return new JobCard('JC-1042', 'awaiting_authorisation', ['Rear discs below minimum thickness']);
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — Four callers
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — one job card, four callers, four needs');

$callers = [
    'Queue worker'   => 'one reason. It logs and moves on.',
    'Advisor form'   => 'EVERY reason, so they fix them in one pass.',
    'Button in a UI' => 'to know BEFORE trying, without an exception.',
    'Phone operator' => 'every reason, in words they can read aloud.',
];

foreach ($callers as $who => $wants) {
    printf("  %-16s %s\n", $who, $wants);
}

echo "\n  Three of the four are ASKING a question. Only the queue worker is\n";
echo "  performing an ACTION.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — Exceptions only
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — exceptions only');

echo "  Queue worker:\n";

try {
    theAwkwardOne()->complete();
    echo "      completed\n";
} catch (WorkshopFailure $e) {
    printf("      refused: %s\n", $e->getMessage());
}

echo "\n  Advisor form — has to try, and gets one of two reasons:\n";

try {
    theAwkwardOne()->complete();
} catch (WorkshopFailure $e) {
    printf("      shown to the user: %s\n", $e->getMessage());
    echo "      the second reason: never mentioned\n";
}

echo "\n  Button in a UI — must attempt the operation to find out whether it\n";
echo "  may attempt the operation:\n\n";
echo "      try { \$clone->complete(); \$enabled = true; }\n";
echo "      catch (WorkshopFailure) { \$enabled = false; }\n\n";
echo "  ...on a COPY, because the real one must not be mutated by a question.\n";
echo "  That is unwinding used to compute a boolean, and it is the shape that\n";
echo "  tells you the mechanism is wrong for this caller.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — Results only
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — results only');

$decision = theAwkwardOne()->checkCanComplete();

echo "  Advisor form:\n";
foreach ($decision->messages() as $i => $message) {
    printf("      %d. %s\n", $i + 1, $message);
}

printf("\n  Button in a UI:   enabled = %s\n", $decision->isAllowed() ? 'true' : 'false');
printf("  Machine codes:    %s\n", implode(', ', $decision->codes()));

echo "\n  Now the queue worker, which just wants to complete things:\n\n";
echo "      \$decision = \$jobCard->checkCanComplete();\n";
echo "      if (!\$decision->isAllowed()) {\n";
echo "          \$this->logger->warning(...\$decision->codes());\n";
echo "          return;\n";
echo "      }\n";
echo "      \$jobCard->complete();\n\n";
echo "  Five lines to do what `\$jobCard->complete();` did in one, and every\n";
echo "  intermediate caller between here and the top now has to decide what to\n";
echo "  do with a Decision it has no opinion about.\n\n";
echo "  THAT is the cost of results everywhere: the ninety percent of callers\n";
echo "  with nothing useful to say about a failure have to say something.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 4 — Both, sharing one list
// ═══════════════════════════════════════════════════════════════════════════
section('Part 4 — both, and only one list of rules');

echo "  public function complete(): void\n";
echo "  {\n";
echo "      \$this->checkCanComplete()->orThrow();\n";
echo "      \$this->status = 'completed';\n";
echo "  }\n\n";

$jobCard  = theAwkwardOne();
$asked    = $jobCard->checkCanComplete();
$actedOn  = null;

try {
    $jobCard->complete();
} catch (WorkshopFailure $e) {
    $actedOn = $e;
}

printf("  ask  -> %d reason(s): %s\n", count($asked->refusals), implode(', ', $asked->codes()));
printf("  act  -> 1 reason:    %s\n", $actedOn?->failureCode ?? '(none)');
printf("  first refusal == what was thrown?  %s\n",
    $asked->codes()[0] === $actedOn?->failureCode ? 'yes' : 'NO');

echo "\n  They cannot disagree, because there is only one list of guards.\n";
echo "  orThrow() throws the first and discards the rest — which is the cost of\n";
echo "  the exception style, stated in one line of code rather than in prose.\n\n";
echo "  Every caller now gets what it needs:\n\n";
printf("      queue worker    complete() and let it throw\n");
printf("      advisor form    checkCanComplete()->messages()\n");
printf("      UI button       checkCanComplete()->isAllowed()\n");
printf("      phone operator  checkCanComplete()->messages()\n\n");
