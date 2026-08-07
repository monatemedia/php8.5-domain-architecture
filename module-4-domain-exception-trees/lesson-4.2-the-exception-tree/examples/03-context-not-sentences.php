<?php
declare(strict_types=1);

/**
 * Example 03 — Why a client must not parse your message
 * ======================================================
 *
 * Part 1: a client extracting what it needs from a message, with a regex.
 * Part 2: a copy editor improves the message. One word.
 * Part 3: the same client, reading fields.
 * Part 4: what the fields let a UI do that a sentence never could.
 *
 *     php 03-context-not-sentences.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

enum Status: string
{
    case Booked     = 'booked';
    case InProgress = 'in_progress';
    case Completed  = 'completed';
    case Invoiced   = 'invoiced';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Booked     => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed  => [self::Invoiced],
            default          => [],
        };
    }
}

final class IllegalTransition extends DomainException
{
    /** @param array<string, mixed> $context */
    private function __construct(string $message, public readonly array $context)
    {
        parent::__construct($message);
    }

    public static function from(string $id, Status $current, Status $attempted, string $connective): self
    {
        return new self(
            sprintf(
                'Job card %s is %s and cannot become %s. Legal from here: %s.',
                $id,
                $current->label(),
                $attempted->label(),
                implode($connective, array_map(
                    static fn(Status $s): string => $s->label(),
                    $current->allowedNext()
                ))
            ),
            [
                'jobCardId' => $id,
                'from'      => $current->value,
                'to'        => $attempted->value,
                'allowed'   => array_map(static fn(Status $s): string => $s->value, $current->allowedNext()),
            ]
        );
    }
}


// ═══════════════════════════════════════════════════════════════════════════
// The client, written against the message
// ═══════════════════════════════════════════════════════════════════════════

/** @return list<string> the transitions this client will offer as buttons */
function buttonsFromMessage(string $message): array
{
    if (preg_match('/Legal from here: (.+)\.$/', $message, $m) !== 1) {
        return [];
    }

    return array_map(
        static fn(string $label): string => strtolower(str_replace(' ', '_', trim($label))),
        explode(', ', $m[1])
    );
}

/** @param array<string, mixed> $context */
function buttonsFromContext(array $context): array
{
    return $context['allowed'] ?? [];
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — It works
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — the client parses the message, and it works');

$failure = IllegalTransition::from('JC-1042', Status::InProgress, Status::Invoiced, ', ');

echo "  message:\n    {$failure->getMessage()}\n\n";
printf("  buttons from message:  %s\n", implode(' | ', buttonsFromMessage($failure->getMessage())));
printf("  buttons from context:  %s\n", implode(' | ', buttonsFromContext($failure->context)));

echo "\n  Identical. The regex is fine. Everybody ships.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — Someone improves the wording
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — a copy editor adds the word "or"');

$edited = IllegalTransition::from('JC-1042', Status::InProgress, Status::Invoiced, ' or ');

echo "  message:\n    {$edited->getMessage()}\n\n";

$fromMessage = buttonsFromMessage($edited->getMessage());
$fromContext = buttonsFromContext($edited->context);

printf("  buttons from message:  %s\n", $fromMessage === [] ? '(none)' : implode(' | ', $fromMessage));
printf("  buttons from context:  %s\n", implode(' | ', $fromContext));

echo "\n";
printf("  message-based client still correct?  %s\n", $fromMessage === ['completed', 'cancelled'] ? 'yes' : 'NO');
printf("  context-based client still correct?  %s\n", $fromContext === ['completed', 'cancelled'] ? 'yes' : 'NO');

echo "\n  The change was to a STRING. No API version changed, no interface\n";
echo "  changed, no test in the workshop's own suite failed. The client's\n";
echo "  buttons quietly became one wrong button.\n\n";
echo "  Note it did not become an ERROR. `strtolower(str_replace(...))` on\n";
echo "  'Completed or Cancelled' happily produces 'completed_or_cancelled',\n";
echo "  a status that does not exist, and the UI renders a dead button.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — Translation, which is the same bug with a bigger blast radius
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — and then somebody adds Afrikaans');

$translated = 'Werkkaart JC-1042 is Aan Die Gang en kan nie Gefaktureer word nie.';

printf("  buttons from message:  %s\n", implode(' | ', buttonsFromMessage($translated)) ?: '(none)');
printf("  buttons from context:  %s\n", implode(' | ', buttonsFromContext($edited->context)));

echo "\n  The context is untouched by translation, because `in_progress` was\n";
echo "  never English. It is an identifier that happens to be spelled with\n";
echo "  Latin letters.\n\n";
echo "  This is why the reference implementation puts \$status->value in the\n";
echo "  context and \$status->label() in the message. Machine values and human\n";
echo "  labels are different data with different audiences, and a class that\n";
echo "  serves both must keep them apart.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 4 — What fields make possible
// ═══════════════════════════════════════════════════════════════════════════
section('Part 4 — the thing a sentence could never support');

$context = $edited->context;

echo "  A UI building itself from the context:\n\n";

foreach (Status::cases() as $status) {
    $enabled = in_array($status->value, $context['allowed'], true);
    printf(
        "      [%s] %-14s %s\n",
        $enabled ? 'x' : ' ',
        $status->label(),
        $enabled ? '' : ($status->value === $context['from'] ? '(current)' : '(not available)')
    );
}

echo "\n  Also available for free, because the fields are typed and separate:\n\n";
printf("      log line       transition_refused from=%s to=%s\n", $context['from'], $context['to']);
printf("      metric         workshop.transition.refused{from=%s}\n", $context['from']);
printf("      JSON body      %s\n", json_encode($context));

echo "\n  None of that is possible from a sentence, and all of it is the same\n";
echo "  four fields read four ways. The message stays — it is what a human\n";
echo "  reads in a log or a toast — but it is no longer load-bearing.\n\n";
echo "  RULE: the message is for people; the context is for programs.\n";
echo "  When one string has to serve both, the program wins the argument and\n";
echo "  the message stops being written for people.\n\n";
