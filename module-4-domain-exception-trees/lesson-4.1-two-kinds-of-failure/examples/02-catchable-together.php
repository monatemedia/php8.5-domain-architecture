<?php
declare(strict_types=1);

/**
 * Example 02 — Two SPL branches, one catch clause
 * ================================================
 *
 * Part 1: the shape of the problem — where SPL puts the two exceptions a domain
 *         throws most often.
 * Part 2: four attempts at one catch clause that means "my domain refused
 *         something". Three of them are wrong, and each is wrong differently.
 * Part 3: the marker interface.
 * Part 4: the cost of the alternative — wrapping — measured.
 *
 *     php 02-catchable-together.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

function ancestry(string $class): string
{
    $chain = [$class];

    for ($parent = get_parent_class($class); $parent !== false; $parent = get_parent_class($parent)) {
        $chain[] = $parent;
    }

    return implode(' -> ', array_map(static fn(string $c): string => '\\' . $c, $chain));
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — Where SPL puts them
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — the two exceptions a domain throws most often');

echo "  A negative price:\n      " . ancestry(InvalidArgumentException::class) . "\n\n";
echo "  A broken business rule:\n      " . ancestry(DomainException::class) . "\n\n";
echo "  A dead database (PDOException):\n      " . ancestry(PDOException::class) . "\n\n";

echo "  The first two share exactly one ancestor: \\LogicException. So do the\n";
echo "  BadFunctionCallException and OutOfRangeException thrown by every\n";
echo "  library in your vendor directory.\n\n";
echo "  Note also where \\DomainException sits. The PHP manual describes\n";
echo "  \\LogicException as errors that 'should directly lead to a fix in your\n";
echo "  code'. A job card that is not ready to be invoiced is not a fix in\n";
echo "  your code. The name is right and the parent is wrong, and there is\n";
echo "  nothing you can do about either.\n";


// ═══════════════════════════════════════════════════════════════════════════
// Some domain failures, spread across both branches
// ═══════════════════════════════════════════════════════════════════════════

interface WorkshopFailure extends \Throwable
{
}

final class NegativeMoney extends \InvalidArgumentException implements WorkshopFailure
{
}

final class JobCardNotComplete extends \DomainException implements WorkshopFailure
{
}

/** Not ours. Stands in for anything from vendor/. */
final class SomeLibraryProblem extends \BadFunctionCallException
{
}

/** Not ours, and a different branch again. */
final class TheDatabaseFellOver extends \RuntimeException
{
}

/** @return array<string, \Throwable> */
function failures(): array
{
    return [
        'NegativeMoney (ours)'       => new NegativeMoney('Money cannot be negative.'),
        'JobCardNotComplete (ours)'  => new JobCardNotComplete('JC-1042 is In Progress.'),
        'SomeLibraryProblem'         => new SomeLibraryProblem('vendor code, not ours'),
        'TheDatabaseFellOver'        => new TheDatabaseFellOver('Connection refused'),
    ];
}

/**
 * Would `catch ($type)` catch each failure? Printed as a grid.
 *
 * @param list<string> $types
 */
function grid(array $types): void
{
    printf("  %-28s", '');
    foreach ($types as $type) {
        printf('%-24s', shortName($type));
    }
    echo "\n";

    foreach (failures() as $label => $failure) {
        printf('  %-28s', $label);
        foreach ($types as $type) {
            printf('%-24s', $failure instanceof $type ? 'caught' : '.');
        }
        echo "\n";
    }
}

function shortName(string $class): string
{
    $parts = explode('\\', $class);

    return (string) end($parts);
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — Four attempts
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — four candidates for "my domain refused something"');

echo "  You want the two OURS rows caught and the two others not.\n\n";

grid([
    \DomainException::class,
    \LogicException::class,
    \Exception::class,
    WorkshopFailure::class,
]);

echo "\n";
echo "  DomainException    misses NegativeMoney. Half your domain, silently.\n";
echo "                     The failure mode: a negative price becomes a 500\n";
echo "                     while a bad status transition becomes a 409, and\n";
echo "                     nobody notices for months.\n\n";
echo "  LogicException     catches both of ours AND the library one. Now a bug\n";
echo "                     in a date parser is presented to the user as a\n";
echo "                     business rule.\n\n";
echo "  Exception          catches everything including the database. Back to\n";
echo "                     example 01.\n\n";
echo "  WorkshopFailure    exactly the two rows you wanted.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — What the interface actually is
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — the whole implementation');

echo "  interface WorkshopFailure extends \\Throwable\n";
echo "  {\n";
echo "  }\n\n";

$reflection = new ReflectionClass(WorkshopFailure::class);
printf("  methods declared:   %d\n", count($reflection->getMethods()) - count((new ReflectionClass(Throwable::class))->getMethods()));
printf("  constants declared: %d\n", count($reflection->getConstants()));
printf("  extends:            %s\n\n", implode(', ', $reflection->getInterfaceNames()));

echo "  It adds nothing. It is a TYPE, and a catch clause takes a type.\n\n";
echo "  `extends \\Throwable` is not decoration either — it is what lets a\n";
echo "  handler call getMessage() and getPrevious() on what it caught, and it\n";
echo "  is what stops somebody implementing WorkshopFailure on a class that is\n";
echo "  not throwable at all. PHP will refuse that at compile time.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 4 — The alternative, and what it costs
// ═══════════════════════════════════════════════════════════════════════════
section('Part 4 — the alternative: wrap everything under one base class');

abstract class WrappedWorkshopException extends \DomainException
{
}

final class WrappedNegativeMoney extends WrappedWorkshopException
{
}

/** The domain now has to translate at every throw site. */
function priceAThing(int $cents): void
{
    try {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Money cannot be negative.');
        }
    } catch (\InvalidArgumentException $e) {
        throw new WrappedNegativeMoney($e->getMessage(), 0, $e);
    }
}

try {
    priceAThing(-1);
} catch (WrappedWorkshopException $e) {
    echo "  Caught under one base class:  " . $e::class . "\n";
    echo "  Original preserved as previous: " . ($e->getPrevious()?->getMessage() ?? '(none)') . "\n";
}

echo "\n  It works. Three costs, in the order you meet them:\n\n";
echo "  1. Every throw site needs the try/catch. Miss one and that failure is\n";
echo "     uncatchable by the boundary — and nothing tells you, because the\n";
echo "     code compiles and the tests for that class still pass.\n\n";
echo "  2. Handlers must unwrap. getPrevious() chains are fine once and\n";
echo "     tiresome at the fourth layer.\n\n";
echo "  3. The type is now a lie in one direction: WrappedNegativeMoney IS-A\n";
echo "     DomainException, but a negative price is an invalid ARGUMENT. You\n";
echo "     have flattened a real distinction to buy a catch clause.\n\n";
echo "  The marker interface costs one `implements` clause, written where the\n";
echo "  class is declared, which is the one place a developer adding a new\n";
echo "  exception is already looking.\n\n";
echo "  Prefer the version that is hard to forget.\n\n";
