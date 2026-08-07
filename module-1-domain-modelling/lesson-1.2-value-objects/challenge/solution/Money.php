<?php
declare(strict_types=1);

namespace Workshop;

/**
 * An amount of money in a single currency.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * INVARIANT I1 (DOMAIN.md): money is never negative, and two values of
 * different currencies never combine.
 *
 * THE THREE PROPERTIES
 * --------------------
 *   Immutable         `final readonly class`. Every operation returns a new
 *                     instance; there is no way to change one.
 *   Structural equal  `equals()` is explicit, so the class decides what "the
 *                     same" means rather than leaving it to `==`.
 *   Self-validating   The constructor is private. `of()` and `zero()` are the
 *                     only doors in, and both guard.
 *
 * WHY THE CONSTRUCTOR IS PRIVATE
 * -------------------------------
 * If it were public, the guards in `of()` would be optional — and the first
 * place they get skipped is a test factory, followed by a mapper, followed by
 * "just this once" in an import script. Private means there is no way to
 * construct one that has not been through a named factory, including from
 * inside this codebase.
 *
 * It also buys a second thing: several named entry points with different rules.
 * `of()` and `zero()` here; a real system would likely add
 * `fromDecimalString()` for parsing user input. A constructor can only have one
 * signature.
 *
 * WHY THERE IS NO divide()
 * ------------------------
 * Deliberately absent. Dividing money raises a question the class cannot
 * answer: where do the remainder minor units go? Splitting EUR 10.00 three ways
 * gives 3.33, 3.33 and 3.33, which is EUR 9.99 — one cent has vanished, and in
 * a system that reconciles, somebody will find it.
 *
 * The honest answer is an allocation method that takes ratios and guarantees
 * the parts sum to the whole, with a stated rule for who gets the remainder.
 * That is a business decision, not an arithmetic one, and inventing it here
 * would be putting a policy in a value object. See DOMAIN.md, "What is not an
 * invariant".
 */
final readonly class Money
{
    private function __construct(
        private int      $amountMinor,
        private Currency $currency,
    ) {}

    /**
     * @param int $amountMinor in the currency's smallest unit — cents for EUR,
     *                         whole yen for JPY, thousandths for KWD
     *
     * @throws \InvalidArgumentException the amount is negative
     */
    public static function of(int $amountMinor, Currency $currency): self
    {
        // INVARIANT I1. An argument problem rather than a rule violation: there
        // is no state of anything that makes a negative amount of money
        // meaningful, so it can never be right at any moment.
        if ($amountMinor < 0) {
            throw new \InvalidArgumentException(sprintf(
                'Money cannot be negative; got %d %s. If you are modelling a refund or a '
                . 'credit note, that is a separate concept with its own direction, not a '
                . 'negative amount.',
                $amountMinor,
                $currency->value
            ));
        }

        return new self($amountMinor, $currency);
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function isZero(): bool
    {
        return $this->amountMinor === 0;
    }

    /**
     * @throws \DomainException the currencies differ
     */
    public function plus(self $other): self
    {
        $this->assertSameCurrency($other, 'add');

        return new self($this->amountMinor + $other->amountMinor, $this->currency);
    }

    /**
     * @throws \DomainException the currencies differ, or the result would be negative
     */
    public function minus(self $other): self
    {
        // Currency first, deliberately. If both guards could fire, the caller is
        // better served by "you cannot mix EUR and USD" than by a complaint
        // about a negative result that only arose because the amounts were
        // never comparable in the first place.
        $this->assertSameCurrency($other, 'subtract');

        $result = $this->amountMinor - $other->amountMinor;

        // A rule violation rather than an argument problem: both operands are
        // perfectly valid Money, and the identical call would succeed against a
        // larger balance.
        if ($result < 0) {
            throw new \DomainException(sprintf(
                'Subtracting %s from %s would give a negative amount, and there is no '
                . 'negative money in this domain.',
                $other,
                $this
            ));
        }

        return new self($result, $this->currency);
    }

    /**
     * @throws \InvalidArgumentException the multiplier is negative
     */
    public function times(int $multiplier): self
    {
        if ($multiplier < 0) {
            throw new \InvalidArgumentException(sprintf(
                'A negative multiplier (%d) would produce negative money.',
                $multiplier
            ));
        }

        return new self($this->amountMinor * $multiplier, $this->currency);
    }

    /**
     * Note this does NOT throw on a currency mismatch — EUR 45.00 and USD 45.00
     * are simply not equal, which is a perfectly good answer to the question
     * "are these the same value?".
     *
     * `isGreaterThan()` is different: "is EUR 45 more than USD 45?" has no
     * answer at all without a rate, so it refuses rather than guessing.
     */
    public function equals(self $other): bool
    {
        return $this->amountMinor === $other->amountMinor
            && $this->currency === $other->currency;
    }

    /**
     * @throws \DomainException the currencies differ
     */
    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other, 'compare');

        return $this->amountMinor > $other->amountMinor;
    }

    public function __toString(): string
    {
        return $this->currency->format($this->amountMinor);
    }

    /**
     * Written once, used three times.
     *
     * Enum cases are singletons, so `!==` is the correct comparison for two of
     * them. No `equals()` and no `->value` comparison is needed, and using one
     * would suggest the currencies might be distinct objects, which they cannot
     * be.
     *
     * @throws \DomainException
     */
    private function assertSameCurrency(self $other, string $operation): void
    {
        if ($this->currency !== $other->currency) {
            throw new \DomainException(sprintf(
                'Cannot %s %s and %s. Converting between currencies needs a rate, a date '
                . 'and a rounding policy, none of which belong inside an arithmetic '
                . 'operation — do the conversion explicitly first.',
                $operation,
                $this->currency->value,
                $other->currency->value
            ));
        }
    }
}
