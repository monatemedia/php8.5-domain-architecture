<?php
declare(strict_types=1);

namespace Workshop;

/**
 * The other branch: input the workshop cannot make sense of.
 *
 * REFERENCE IMPLEMENTATION.
 *
 *     \InvalidArgumentException
 *         └── InvalidWorkshopInput               (this class, abstract)
 *               └── MissingWorkDescription
 *
 * WHY THERE ARE TWO ROOTS AND NOT ONE
 * ------------------------------------
 * This is the class that justifies the marker interface, so it is worth being
 * precise about the distinction rather than treating it as SPL trivia.
 *
 *     WorkshopException      "the request made sense; the answer is no"
 *     InvalidWorkshopInput   "the request did not make sense"
 *
 * `$jobCard->invoice()` on a job card that is In Progress is the first. The
 * caller asked a coherent question and the workshop refused, for a reason
 * Marcus would give you.
 *
 * `$jobCard->discoverAdditionalWork('')` is the second. There is no question
 * there to answer. An empty description is not additional work that the
 * workshop declines to record; it is nothing at all.
 *
 * The test that separates them, and it is not the same as Lesson 4.1's:
 *
 *     Could the caller have known this was invalid without asking the domain?
 *
 * Yes -> input. A form could have caught it, a type could have caught it, the
 *        caller's own code could have caught it.
 * No  -> a business rule. It depends on state the caller does not own.
 *
 * WHY IT MATTERS AT THE BOUNDARY
 * -------------------------------
 * Lesson 4.4 maps these two branches to different status codes, and the mapping
 * falls straight out of the distinction:
 *
 *     InvalidWorkshopInput  -> 422 Unprocessable Content  "fix your request"
 *     WorkshopException     -> 409 Conflict               "fix the world"
 *
 * A client can retry a 422 with a better payload. Retrying a 409 with the same
 * payload will fail identically until something else changes. That is a real
 * difference to a client that automates retries, and it is invisible if both
 * arrive as 400.
 *
 * WHY IT CANNOT SHARE A PARENT WITH WorkshopException
 * ----------------------------------------------------
 * `\InvalidArgumentException` and `\DomainException` are siblings under
 * `\LogicException`, so the only common ancestor available is one that also
 * covers every `\BadFunctionCallException` in `vendor/`.
 *
 * Hence `WorkshopFailure` — an interface, implemented by both roots, spanning a
 * hierarchy neither of them can leave. Lesson 4.1 built it before there was
 * anything to span. This is the class that makes it necessary rather than
 * merely defensible.
 *
 * THE DUPLICATION, AND WHY IT IS TOLERATED
 * -----------------------------------------
 * The context storage below is character-for-character what `WorkshopException`
 * does. It cannot be shared: a trait could hold it, and a trait holding the
 * private state of two class hierarchies is a worse thing to explain than eight
 * duplicated lines.
 *
 * Worth knowing the shape of the argument, because it recurs: PHP's single
 * inheritance means every genuine two-root design pays a small duplication tax
 * at the roots. The alternative is one root and a lie in the type system.
 */
abstract class InvalidWorkshopInput extends \InvalidArgumentException implements CarriesContext
{
    /**
     * @var array<string, string|int|float|bool|null|list<string|int|float|bool|null>>
     */
    private array $context;

    /**
     * @param array<string, string|int|float|bool|null|list<string|int|float|bool|null>> $context
     */
    protected function __construct(string $message, array $context = [])
    {
        parent::__construct($message);

        $this->context = $context;
    }

    /**
     * @return array<string, string|int|float|bool|null|list<string|int|float|bool|null>>
     */
    public function context(): array
    {
        return $this->context;
    }
}
