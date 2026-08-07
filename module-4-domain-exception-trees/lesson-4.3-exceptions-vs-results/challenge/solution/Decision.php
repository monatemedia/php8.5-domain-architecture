<?php
declare(strict_types=1);

namespace Workshop;

/**
 * The answer to "may I?" — yes, or here is every reason why not.
 *
 * REFERENCE IMPLEMENTATION. The course's one result type, and it is deliberately
 * the smallest useful one.
 *
 * WHAT IT IS NOT
 * ---------------
 * Not `Result<T, E>`. Not an Either. Not a monad, and there is no `map()`,
 * `flatMap()` or `andThen()` on it.
 *
 * PHP has no generics, so a general result type is either `mixed` with a
 * docblock — which the engine does not check and which every IDE guesses at —
 * or a library with a template annotation that only a static analyser
 * understands. Both are real options and both cost more than this domain needs.
 *
 * This answers one question — *may I do this thing?* — where the "yes" case
 * carries no value. A permission has no payload. That collapses the generic
 * problem entirely, and the class is thirty lines instead of three hundred.
 *
 * Rule 6: ceremony must be paid for. A result type that models the return value
 * as well would be paying for a generic system PHP does not have.
 *
 * WHY IT COLLECTS ALL THE REASONS
 * --------------------------------
 * This is the only thing it does that an exception cannot.
 *
 * A job card sitting in Awaiting Authorisation with an item outstanding cannot
 * be completed for TWO reasons: the outstanding item, and the fact that the
 * transition table has no path from that state to Completed. `complete()`
 * throws the first and abandons the second — necessarily, because unwinding
 * only carries one object.
 *
 * A user filling in a form wants both. So does a UI deciding which buttons to
 * grey out, and so does an operator on the phone to a customer.
 *
 * `#[\NoDiscard]` — PHP 8.5
 * --------------------------
 * On both factories, because the characteristic bug of every result type in
 * every language is this:
 *
 *     $jobCard->checkCanComplete();     // <- the whole point, discarded
 *     $jobCard->complete();
 *
 * That reads like a guard and is a no-op. It is the same shape as the discarded
 * wither from Lesson 1.2 — a line that looks like it does something and does
 * not — and it is the strongest argument AGAINST result types: an exception
 * cannot be ignored by accident, and this can.
 *
 * `#[\NoDiscard]` closes most of the gap, which is why this course can
 * recommend the pattern at all. It emits a warning on the line above. Note
 * "most": it is a warning, not an error, and it does not fire if the value is
 * assigned to a variable nobody reads.
 *
 * THE ORDER OF THE REFUSALS IS PART OF THE CONTRACT
 * --------------------------------------------------
 * `orThrow()` throws the first one, so the order decides which exception a
 * caller using the act-path sees. In `JobCard::checkCanComplete()` the
 * outstanding-work refusal is first, because that is what `complete()` threw
 * before this lesson existed.
 *
 * Worth noticing what happened there: statement order inside a method used to
 * be an implementation detail, and it has quietly become an observable
 * decision. That is a real cost of this pattern, and it is the kind that does
 * not show up until somebody reorders two `if` blocks during a tidy-up.
 */
final readonly class Decision
{
    /**
     * @param list<Refusal> $refusals
     */
    private function __construct(public array $refusals) {}

    #[\NoDiscard('A Decision that is not read has decided nothing.')]
    public static function allowed(): self
    {
        return new self([]);
    }

    /**
     * Variadic, so a caller cannot build a refused Decision with no reasons.
     * `Decision::refusedBecause()` with no arguments would be a decision that
     * reports itself as allowed, which is the one state this class must not be
     * able to reach.
     *
     * PHP requires at least one argument for the first variadic position only
     * if it is declared before the variadic — so this is enforced by naming the
     * first one explicitly.
     */
    #[\NoDiscard('A Decision that is not read has decided nothing.')]
    public static function refusedBecause(Refusal $first, Refusal ...$rest): self
    {
        return new self([$first, ...array_values($rest)]);
    }

    public function isAllowed(): bool
    {
        return $this->refusals === [];
    }

    public function isRefused(): bool
    {
        return $this->refusals !== [];
    }

    /**
     * The failure codes, in order. What a client switches on.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        return array_map(static fn(Refusal $r): string => $r->code, $this->refusals);
    }

    /**
     * The bridge back to the act-path.
     *
     * This is what makes the two styles cost almost nothing to have together:
     * `complete()` is written as `$this->checkCanComplete()->orThrow();` and
     * therefore CANNOT disagree with the check. There is one list of guards and
     * two ways of reading it.
     *
     * It throws the FIRST refusal and discards the rest, which is the honest
     * cost of the exception style stated in one line of code. A caller who
     * wants every reason has to ask rather than act.
     */
    public function orThrow(): void
    {
        if ($this->refusals !== []) {
            $this->refusals[0]->raise();
        }
    }
}
