<?php
declare(strict_types=1);

/**
 * Example 03 — Translating driver failures, without destroying evidence
 * ======================================================================
 *
 * Part 1: what a leaked PDOException does to application code.
 * Part 2: the decorator, and the two-line rule it enforces.
 * Part 3: pass-through must be IDENTITY, not a copy. Measured.
 * Part 4: catch (\Exception), not catch (\Throwable) — run with the wrong one
 *         to see what it costs.
 *
 *     php 03-translating-at-the-edge.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

function shortName(string $class): string
{
    $parts = explode('\\', $class);

    return (string) end($parts);
}


// ═══════════════════════════════════════════════════════════════════════════
// A miniature of the Module 3 arrangement
// ═══════════════════════════════════════════════════════════════════════════

interface WorkshopFailure extends \Throwable
{
}

/** Domain. Category (a). */
final class ThingNotFound extends \DomainException implements WorkshopFailure
{
}

/** Infrastructure. Category (b). Deliberately NOT a WorkshopFailure. */
final class StorageFailure extends \RuntimeException
{
    public static function during(string $operation, \Throwable $cause): self
    {
        return new self(
            sprintf('Storage failed during %s.', $operation),
            0,
            $cause   // evidence, kept
        );
    }
}

interface Things
{
    public function get(string $id): string;
}

/** Stands in for SqliteJobCards. Throws whatever you tell it to. */
final class FlakyThings implements Things
{
    public function __construct(private readonly \Closure $behaviour) {}

    public function get(string $id): string
    {
        return ($this->behaviour)($id);
    }
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — The leak
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — what a caller has to write when the driver leaks');

echo "  Application code — a use case, nowhere near the database:\n\n";
echo "      try {\n";
echo "          \$jobCard = \$jobCards->get(\$id);\n";
echo "      } catch (\\PDOException \$e) {          // <-- ext-pdo, in a use case\n";
echo "          ...\n";
echo "      }\n\n";
echo "  Three things are now true, and each is worse than the last:\n\n";
echo "  1. A use case names a class from a database extension.\n";
echo "  2. Swap SQLite for an HTTP API and that clause is dead code. It still\n";
echo "     compiles. Nothing warns you.\n";
echo "  3. The JobCards INTERFACE — the clean one, in the domain — is now a\n";
echo "     lie, because its real contract includes exceptions it never\n";
echo "     mentions. Failures are part of an interface whether or not you\n";
echo "     wrote them down.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — The decorator
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — one class, one rule');

final class GuardedThings implements Things
{
    public function __construct(private readonly Things $inner) {}

    public function get(string $id): string
    {
        try {
            return $this->inner->get($id);
        } catch (WorkshopFailure $e) {
            throw $e;                                   // ours. Untouched.
        } catch (\Exception $e) {
            throw StorageFailure::during('get', $e);    // theirs. Translated.
        }
    }
}

$cases = [
    'domain failure' => new FlakyThings(
        static fn(string $id): string => throw new ThingNotFound("No thing {$id}.")
    ),
    'driver failure' => new FlakyThings(
        static fn(string $id): string => throw new \PDOException('SQLSTATE[HY000] [2002] Connection refused')
    ),
    'success' => new FlakyThings(
        static fn(string $id): string => "thing {$id}"
    ),
];

foreach ($cases as $label => $inner) {
    $guarded = new GuardedThings($inner);

    try {
        $result = $guarded->get('T-1');
        printf("  %-16s -> returned %s\n", $label, $result);
    } catch (\Throwable $e) {
        printf(
            "  %-16s -> %-16s  WorkshopFailure? %s\n",
            $label,
            shortName($e::class),
            $e instanceof WorkshopFailure ? 'yes' : 'no'
        );
    }
}

echo "\n  The boundary can now write:\n\n";
echo "      catch (WorkshopFailure \$e)  -> 4xx, with the message\n";
echo "      catch (\\Exception \$e)       -> 500, message logged, not shown\n\n";
echo "  and the second clause never sees a business rule.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — Pass-through means identity
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — "untouched" is a stronger claim than it looks');

final class AlmostGuardedThings implements Things
{
    public function __construct(private readonly Things $inner) {}

    public function get(string $id): string
    {
        try {
            return $this->inner->get($id);
        } catch (WorkshopFailure $e) {
            // Looks harmless. It is not.
            throw new ThingNotFound($e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            throw StorageFailure::during('get', $e);
        }
    }
}

$thrown = new ThingNotFound('No thing T-1.');
$inner  = new FlakyThings(static fn(string $id): string => throw $thrown);

foreach (['GuardedThings' => GuardedThings::class, 'AlmostGuardedThings' => AlmostGuardedThings::class] as $label => $class) {
    try {
        (new $class($inner))->get('T-1');
    } catch (\Throwable $caught) {
        printf(
            "  %-22s same object? %-4s  line reported: %d\n",
            $label,
            $caught === $thrown ? 'yes' : 'NO',
            $caught->getLine()
        );
    }
}

printf("\n  The exception was actually created on line %d of this file.\n\n", $thrown->getLine());
echo "  AlmostGuardedThings reports the line inside the DECORATOR instead —\n";
echo "  it built a new object there. Every stack trace in your logs now\n";
echo "  points at the wrapper, and the trail back to where the rule was\n";
echo "  actually broken lives one getPrevious() call away, if anyone thinks\n";
echo "  to look.\n\n";
echo "  It also silently loses any subclass. Rewrap a JobCardIsTerminal as a\n";
echo "  ThingNotFound and the boundary maps it to 404 instead of 409.\n\n";
echo "  Hence `throw \$e;` — the same object, re-thrown. The challenge asserts\n";
echo "  this with ===.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 4 — \Exception, not \Throwable
// ═══════════════════════════════════════════════════════════════════════════
section('Part 4 — the two-character change that hides bugs');

final class OverGuardedThings implements Things
{
    public function __construct(private readonly Things $inner) {}

    public function get(string $id): string
    {
        try {
            return $this->inner->get($id);
        } catch (WorkshopFailure $e) {
            throw $e;
        } catch (\Throwable $e) {            // <-- \Throwable, "to be safe"
            throw StorageFailure::during('get', $e);
        }
    }
}

/** A bug in a mapper: the column is a string, and the mapper treats it as Money. */
$buggy = new FlakyThings(static function (string $id): string {
    $row = ['total' => '4250'];

    // @phpstan-ignore-next-line — deliberate: that is a string, not a Money.
    return $row['total']->formatted();
});

foreach (['GuardedThings' => GuardedThings::class, 'OverGuardedThings' => OverGuardedThings::class] as $label => $class) {
    try {
        (new $class($buggy))->get('T-1');
        printf("  %-20s -> returned normally (?)\n", $label);
    } catch (\Throwable $e) {
        printf(
            "  %-20s -> %-16s  %s\n",
            $label,
            shortName($e::class),
            $e->getMessage()
        );
    }
}

echo "\n";
echo "  OverGuardedThings reports a STORAGE FAILURE. The storage is fine.\n";
echo "  The bug is a mapper reading a key that is not there, and the operator\n";
echo "  who gets paged will start by checking the database.\n\n";
echo "  GuardedThings lets the \\Error through, where it belongs to whoever\n";
echo "  wrote the mapper.\n\n";
echo "  This is section 2 of the README earning its keep. Classify failures\n";
echo "  into 'domain' and 'not domain' and you wrap the bug, because a bug is\n";
echo "  not domain. Three categories, not two.\n\n";
