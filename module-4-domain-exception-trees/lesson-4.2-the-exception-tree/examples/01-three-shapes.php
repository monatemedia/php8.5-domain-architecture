<?php
declare(strict_types=1);

/**
 * Example 01 — Three ways to shape a domain's failures
 * =====================================================
 *
 * The same four failures, modelled three ways. Then the same three requirements
 * put to each:
 *
 *   R1  catch ONE specific failure and recover from it
 *   R2  catch every failure about job cards, and nothing else
 *   R3  catch everything the domain refuses, at the boundary
 *
 *     php 01-three-shapes.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

function result(string $requirement, bool $met, string $note): void
{
    printf("  %-4s %-8s %s\n", $requirement, $met ? 'YES' : 'no', $note);
}


// ═══════════════════════════════════════════════════════════════════════════
// SHAPE A — One class, a string code
// ═══════════════════════════════════════════════════════════════════════════
section('Shape A — one class carrying a code');

/**
 * NOTE THE PROPERTY NAME, which is not what you would write first.
 *
 * `public readonly string $code` is the obvious spelling and it is a fatal
 * error: \Exception already declares `protected int $code`, and PHP will not
 * let a subclass redeclare a non-readonly property as readonly — or change its
 * type. The message is
 *
 *     Cannot redeclare non-readonly property Exception::$code
 *     as readonly ShopFailureA::$code
 *
 * A small thing, and worth meeting here rather than at 6pm. `$code` and
 * `$message` are both taken on every exception in PHP, which is one more reason
 * the tree in shape C carries `failureCode()` as a METHOD rather than reusing a
 * property name SPL already owns.
 */
final class ShopFailureA extends DomainException
{
    public function __construct(string $message, public readonly string $failureCode)
    {
        parent::__construct($message);
    }
}

$failuresA = [
    new ShopFailureA('No job card 1042.',            'JC_NOT_FOUND'),
    new ShopFailureA('Cannot invoice yet.',          'JC_NOT_COMPLETE'),
    new ShopFailureA('No line LN-7.',                'LINE_NOT_FOUND'),
    new ShopFailureA('That fleet code is invalid.',  'FLEET_CODE_BAD'),
];

echo "      throw new ShopFailureA('Cannot invoice yet.', 'JC_NOT_COMPLETE');\n\n";

result('R1', false, 'catch (ShopFailureA) then if ($e->failureCode === ...) — a switch');
result('R2', false, 'no way to name the job-card subset except by listing codes');
result('R3', true,  'catch (ShopFailureA) does catch everything');

echo "\n  R1 is the expensive one. The code is DATA and the type is not, so the\n";
echo "  work a catch clause exists to do moves into an if. Nothing checks it,\n";
echo "  a typo is silent, and the set of codes is discoverable by grep.\n";


// ═══════════════════════════════════════════════════════════════════════════
// SHAPE B — A flat list of classes
// ═══════════════════════════════════════════════════════════════════════════
section('Shape B — a flat list, all under \DomainException');

final class JobCardNotFoundB      extends DomainException {}
final class JobCardNotCompleteB   extends DomainException {}
final class LineNotFoundB         extends DomainException {}
final class FleetCodeInvalidB     extends DomainException {}

$failuresB = [
    new JobCardNotFoundB('No job card 1042.'),
    new JobCardNotCompleteB('Cannot invoice yet.'),
    new LineNotFoundB('No line LN-7.'),
    new FleetCodeInvalidB('That fleet code is invalid.'),
];

result('R1', true,  'catch (JobCardNotCompleteB) — checked by the compiler');
result('R2', false, 'three clauses today, four next month, and you forget one');
result('R3', false, 'catch (\DomainException) also catches vendor code');

echo "\n  R2 fails QUIETLY, which is the worst way to fail. The code compiles,\n";
echo "  the tests pass, and the fifth job-card failure added next quarter\n";
echo "  falls through to whatever catches DomainException.\n";


// ═══════════════════════════════════════════════════════════════════════════
// SHAPE C — Depth where depth buys something
// ═══════════════════════════════════════════════════════════════════════════
section('Shape C — the tree');

interface ShopFailure extends Throwable {}

abstract class ShopException extends DomainException implements ShopFailure {}
abstract class JobCardExceptionC extends ShopException {}
abstract class InvalidShopInput extends InvalidArgumentException implements ShopFailure {}

final class JobCardNotFoundC    extends JobCardExceptionC {}
final class JobCardNotCompleteC extends JobCardExceptionC {}
final class LineNotFoundC       extends JobCardExceptionC {}
final class FleetCodeInvalidC   extends InvalidShopInput {}

$failuresC = [
    new JobCardNotFoundC('No job card 1042.'),
    new JobCardNotCompleteC('Cannot invoice yet.'),
    new LineNotFoundC('No line LN-7.'),
    new FleetCodeInvalidC('That fleet code is invalid.'),
];

result('R1', true,  'catch (JobCardNotCompleteC)');
result('R2', true,  'catch (JobCardExceptionC) — and it covers classes not yet written');
result('R3', true,  'catch (ShopFailure) — spans BOTH SPL branches');

echo "\n  Note the fourth class. FleetCodeInvalidC is an InvalidArgumentException,\n";
echo "  which is a SIBLING of DomainException, so no base class covers it and\n";
echo "  the three others. The interface does.\n";


// ═══════════════════════════════════════════════════════════════════════════
// Proving R2 and R3 rather than asserting them
// ═══════════════════════════════════════════════════════════════════════════
section('Proof — how many of the four does each clause catch?');

/** @param list<Throwable> $failures */
function caughtBy(array $failures, string $type): int
{
    return count(array_filter($failures, static fn(Throwable $e): bool => $e instanceof $type));
}

$vendorNoise = new BadFunctionCallException('something in vendor/');
$engineBug   = new TypeError('a bug in your mapper');

printf("  %-34s %-12s %-12s %s\n", 'clause', 'ours (4)', 'vendor', 'engine bug');
printf("  %s\n", str_repeat('-', 72));

printf("  %-34s %-12d %-12s %s\n", 'A: catch (ShopFailureA)',
    caughtBy($failuresA, ShopFailureA::class), 'no', 'no');

printf("  %-34s %-12d %-12s %s\n", 'B: catch (\DomainException)',
    caughtBy($failuresB, DomainException::class),
    $vendorNoise instanceof DomainException ? 'CAUGHT' : 'no',
    $engineBug instanceof DomainException ? 'CAUGHT' : 'no');

printf("  %-34s %-12d %-12s %s\n", 'B: catch (\LogicException)',
    caughtBy($failuresB, LogicException::class),
    $vendorNoise instanceof LogicException ? 'CAUGHT' : 'no',
    $engineBug instanceof LogicException ? 'CAUGHT' : 'no');

printf("  %-34s %-12d %-12s %s\n", 'C: catch (ShopFailure)',
    caughtBy($failuresC, ShopFailure::class),
    $vendorNoise instanceof ShopFailure ? 'CAUGHT' : 'no',
    $engineBug instanceof ShopFailure ? 'CAUGHT' : 'no');

printf("  %-34s %-12d %-12s %s\n", 'C: catch (JobCardExceptionC)',
    caughtBy($failuresC, JobCardExceptionC::class), 'no', 'no');

echo "\n  Row 2: \\DomainException gets 4 of 4 here only because this example put\n";
echo "  all four under it. Move FleetCodeInvalid to InvalidArgumentException,\n";
echo "  where it belongs, and it drops to 3 — silently.\n\n";
echo "  Row 3: \\LogicException gets all four AND the vendor exception. That is\n";
echo "  the trade the flat design forces: precision or coverage, never both.\n\n";
echo "  Row 4: the interface gets exactly 4, and nothing else. Row 5 gets the\n";
echo "  job-card subset, which is a clause the other two shapes cannot write.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('The rule for adding a node');

echo "  Shape C has three internal nodes. It is easy to draw six.\n\n";
echo "      Name the catch clause that would use the node,\n";
echo "      and name the field it holds.\n";
echo "      If you can do neither, you are drawing a diagram.\n\n";
echo "  JobCardExceptionC passes both: a job-card use case writes that clause,\n";
echo "  and the node holds the identity every failure below it needs.\n\n";
echo "  A CourtesyVehicleException with ONE subclass passes neither. Nobody\n";
echo "  catches a node when catching the child is more precise, and one child\n";
echo "  means there is nothing to hoist. A node with one child is a rename\n";
echo "  with extra steps.\n\n";
echo "  Add it when the second class arrives. Two minutes, then.\n\n";
