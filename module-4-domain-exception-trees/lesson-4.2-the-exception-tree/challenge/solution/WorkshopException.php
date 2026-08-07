<?php
declare(strict_types=1);

namespace Workshop;

/**
 * The root of the workshop's business-rule failures.
 *
 * REFERENCE IMPLEMENTATION.
 *
 *     \DomainException
 *         └── WorkshopException                  (this class, abstract)
 *               └── JobCardException             (abstract — holds an identity)
 *                     ├── JobCardNotFound
 *                     ├── JobCardAlreadyExists
 *                     ├── IllegalStatusTransition
 *                     ├── JobCardIsTerminal
 *                     ├── LineNotFound
 *                     ├── LineCurrencyMismatch
 *                     ├── NothingAwaitingAuthorisation
 *                     └── OutstandingAuthorisations
 *
 * ABSTRACT, DELIBERATELY
 * -----------------------
 * You cannot throw one. `throw new WorkshopException('something went wrong')`
 * is a failure with no type — the thing Lesson 4.1 spent a decorator finding —
 * and making the class abstract means the compiler refuses it rather than a
 * reviewer having to notice.
 *
 * The same argument applies to every internal node of the tree. If a node can
 * be instantiated, it will be, on the afternoon somebody is in a hurry.
 *
 * IT KEEPS \DomainException AS ITS PARENT
 * ----------------------------------------
 * Not because SPL's placement is right — Lesson 4.1 section 4 argues it is not
 * — but because Modules 1 to 3 shipped forty-odd assertions naming that class,
 * and because `\DomainException` is what a PHP developer reads as "business
 * rule" whether or not the manual agrees.
 *
 * Every one of those assertions still passes. Nothing in this module rewrites
 * an earlier lesson's spec; the tree grows underneath it.
 *
 * WHY THE CONTEXT LIVES HERE AND NOT ON EACH SUBCLASS
 * ----------------------------------------------------
 * Twelve subclasses would otherwise each declare `private array $context` and
 * `public function context(): array`. That is the sort of duplication a base
 * class exists for, and it is the ONLY behaviour this class provides — which
 * is a reasonable test for whether an abstract base has earned its place.
 *
 * Compare the alternative shape you sometimes see, where the base class also
 * formats the message, holds a default HTTP status and knows a translation key.
 * That base class is doing presentation, and presentation is Lesson 4.4's job,
 * in `infrastructure/`. A domain exception that knows it is a 409 has the web
 * in it.
 *
 * WHAT IS NOT HERE
 * -----------------
 *   - No HTTP status. See above.
 *   - No `previous`. Domain failures are not caused by other exceptions; they
 *     are caused by somebody trying to do something. `Infrastructure\
 *     StorageFailure` is where a cause chain belongs.
 *   - No logging, no side effects. An exception constructor that writes to a
 *     log makes every test that asserts a refusal into a test that writes to a
 *     log.
 */
abstract class WorkshopException extends \DomainException implements CarriesContext
{
    /**
     * @var array<string, string|int|float|bool|null|list<string|int|float|bool|null>>
     */
    private array $context;

    /**
     * PROTECTED, so only the tree can build one. Combined with `abstract` on
     * this class and `final` on every leaf, the set of throwable types is
     * closed and enumerable — which is what makes Lesson 4.4's mapper
     * exhaustive rather than hopeful.
     *
     * Note the signature does not match \DomainException's. Constructors are
     * exempt from PHP's signature-compatibility rules, and this is one of the
     * few places that exemption is worth using: `int $code` is a thing SPL
     * offers and nothing in this course wants.
     *
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

    /**
     * `failureCode()` is NOT declared here.
     *
     * It is declared on `CarriesContext` and left unimplemented, which makes it
     * implicitly abstract — so every concrete subclass must supply one or PHP
     * refuses to load it.
     *
     * That is the enforcement. A default implementation here, returning
     * something derived or empty, would let a new exception ship without a
     * code, and the place you would find out is a client's `switch` statement
     * falling through to its default branch.
     */
}
