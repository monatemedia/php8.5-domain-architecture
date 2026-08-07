<?php
declare(strict_types=1);

namespace Workshop;

/**
 * One reason something cannot be done.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * WHAT IT IS
 * ----------
 * The same three facts a `CarriesContext` exception carries — a code, a
 * sentence, and fields — with the difference that this one is a VALUE you can
 * put in a list, and a list is the thing an exception can never be.
 *
 * An exception is thrown one at a time by construction. That is not a flaw; it
 * is what unwinding means. But "why can I not complete this job card?" can have
 * two answers at once, and a mechanism that reports the first and abandons the
 * rest is answering a different question than the one the user asked.
 *
 * IT HOLDS THE EXCEPTION, AND THAT IS THE WHOLE DESIGN
 * -----------------------------------------------------
 * The obvious shape is `Refusal::of('outstanding-authorisations', 'Job card
 * ...', [...])` — three arguments, no exception anywhere near it. It is
 * simpler, and it is wrong here, for one reason:
 *
 *     There would then be TWO places that know what refusing looks like,
 *     and nothing keeping them in step.
 *
 * `complete()` would throw `OutstandingAuthorisations`, `checkCanComplete()`
 * would build a `Refusal` with a hand-written code and a hand-written message,
 * and the day somebody adds a guard to one they will not add it to the other.
 * The ask-path would say yes and the act-path would refuse — which is the worst
 * possible failure for this pattern, because the caller did the responsible
 * thing and was punished for it.
 *
 * Holding the exception means the exception is the single source of truth.
 * `Refusal::from()` reads the code, the message and the context off it, and
 * `raise()` throws the very object those facts came from. They cannot disagree,
 * because there is only one of them.
 *
 * THE COST, MEASURED
 * -------------------
 * Constructing an exception captures a stack trace, which is the expensive part
 * of `new` in PHP. So building a Decision that refuses costs one backtrace per
 * reason, whether or not anybody throws it.
 *
 * On the happy path it costs nothing at all — no refusals, no exceptions. On
 * the refusal path it costs roughly what throwing would have cost, and you were
 * about to do that anyway.
 *
 * Where it genuinely hurts: a report that asks `checkCanComplete()` for ten
 * thousand job cards and counts how many are blocked. That is fifteen thousand
 * backtraces for a number. Example 03 measures it. If you have that report,
 * the answer is a read model — Lesson 3.3 — not a redesign of this class.
 *
 * WHY IT IS NOT A `Throwable` ITSELF
 * -----------------------------------
 * A tempting simplification: make Refusal extend Exception and skip the
 * wrapper. It breaks the moment you hold two of them. `Decision::refusedBecause(
 * $a, $b)` would be a list of live exceptions, each carrying a stack trace from
 * where it was BUILT rather than where it was thrown, and any code that later
 * caught one would see a trace pointing into a check method.
 *
 * A refusal is data about a failure. The failure itself is one `raise()` away,
 * for the caller who wants it.
 */
final readonly class Refusal
{
    /**
     * @param array<string, string|int|float|bool|null|list<string|int|float|bool|null>> $context
     */
    private function __construct(
        public string $code,
        public string $message,
        public array $context,
        private CarriesContext $failure,
    ) {}

    /**
     * Build a refusal from the exception that would have been thrown.
     *
     * The parameter is typed `CarriesContext` rather than `WorkshopFailure`
     * deliberately: this needs `failureCode()` and `context()`, and a marker
     * interface does not have them. A domain failure that carries no context
     * cannot become a Refusal — which is a constraint worth having, because a
     * refusal a client cannot act on is a refusal not worth collecting.
     */
    public static function from(CarriesContext $failure): self
    {
        return new self(
            $failure->failureCode(),
            $failure->getMessage(),
            $failure->context(),
            $failure
        );
    }

    /**
     * Throw the failure this refusal was built from.
     *
     * `never` rather than `void`, so PHP and every static analyser know that
     * control does not come back. It is what lets `Decision::orThrow()` be
     * written as four lines without a redundant `return`.
     *
     * The object thrown is the ORIGINAL — the same instance `from()` was given.
     * Rebuilding it here would report this file in the stack trace, which is
     * Lesson 4.1's decorator argument arriving in a different costume.
     */
    public function raise(): never
    {
        throw $this->failure;
    }
}
