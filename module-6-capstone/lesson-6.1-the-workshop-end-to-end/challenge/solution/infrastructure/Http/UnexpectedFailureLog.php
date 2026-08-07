<?php
declare(strict_types=1);

namespace Infrastructure\Http;

/**
 * Where the failures nobody expected go.
 *
 * REFERENCE IMPLEMENTATION, and the resolution of a tension Lesson 4.1 left
 * open.
 *
 * 4.1 said category (c) — programmer error — should be LOUD, and that widening
 * a `catch (\Exception)` to `catch (\Throwable)` is the most expensive
 * two-character change in the module.
 *
 * The outermost middleware of a web application catches `\Throwable` anyway.
 * It has to: the alternative is a white page and a stack trace served to a
 * customer, which is worse in every direction.
 *
 * Both are right, and the thing that reconciles them is WHO GETS TOLD.
 *
 *     the user      an opaque 500. No message, no class, no trace.
 *     the operator  everything, here, with the exception intact.
 *
 * "Loud" never meant "loud to the person who typed the wrong registration
 * number". It meant somebody whose job it is finds out, with enough detail to
 * fix it. A boundary that catches everything and reports nothing is the failure
 * 4.1 was warning about; a boundary that catches everything and reports it to
 * an operator is just a boundary.
 *
 * IN A REAL APPLICATION this is a PSR-3 logger and an error tracker. It is an
 * array here because the capstone has to assert that it fired, and asserting
 * against a log file is a worse test than asserting against a list.
 */
final class UnexpectedFailureLog
{
    /** @var list<\Throwable> */
    private array $failures = [];

    public function record(\Throwable $failure): void
    {
        $this->failures[] = $failure;
    }

    /** @return list<\Throwable> */
    public function all(): array
    {
        return $this->failures;
    }

    public function count(): int
    {
        return count($this->failures);
    }
}
