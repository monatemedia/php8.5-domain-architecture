<?php
declare(strict_types=1);

namespace Workshop;

/**
 * A rate of tax.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * WHY THIS IS A VALUE OBJECT AND NOT A DOMAIN SERVICE
 * ----------------------------------------------------
 * "Apply tax to an amount" sounds like an operation, and operations sound like
 * services. It is not one, and the test is Lesson 1.4's:
 *
 *     can you decide this holding only these objects?
 *
 * A rate and an amount are all you need. Nothing is looked up, nothing else is
 * consulted, and the answer depends on nothing outside the two values. So it is
 * a method on the value that owns the knowledge — how much tax a rate produces
 * is a fact about the rate.
 *
 * A `TaxCalculator` service taking a rate and an amount would be the same
 * arithmetic with an extra class and an extra injection, and it is exactly the
 * kind of "service" that makes people think domain services are ceremony. They
 * are, when they are this.
 *
 * WHY BASIS POINTS
 * ----------------
 * A hundredth of a percent, as an integer. Real rates are not always whole
 * percentages — 12.5%, 5.5%, 7.25% all exist — and the moment you store a rate
 * as a float you have put a float into money arithmetic through the back door.
 *
 * 2000 basis points is 20%. 1250 is 12.5%.
 *
 * WHY THIS IS NOT ON Money
 * ------------------------
 * `$money->plusTax(0.2)` would put a tax concept on a class whose entire job is
 * to be an amount. Money is used for prices, costs, credit limits and
 * thresholds, none of which have anything to do with tax. Rule 5: the knowledge
 * belongs with the thing that owns it, and the thing that owns tax is the rate.
 */
final readonly class TaxRate
{
    private const int BASIS_POINTS_PER_UNIT = 10_000;

    private function __construct(private int $basisPoints) {}

    /**
     * @param int $basisPoints hundredths of a percent. 2000 is 20%.
     *
     * @throws \InvalidArgumentException
     */
    public static function ofBasisPoints(int $basisPoints): self
    {
        if ($basisPoints < 0) {
            throw new \InvalidArgumentException(
                "A tax rate cannot be negative; got {$basisPoints} basis points. A refund is a "
                . 'different concept with its own direction.'
            );
        }

        // An upper bound at 100%. Rates above it exist in the world — some
        // excise duties exceed the value of the goods — but a rate over 100%
        // arriving here is overwhelmingly a units mistake: somebody passed 20
        // meaning 20%, or 0.2 meaning a fraction. This is a SANITY CHECK, not a
        // business rule, and Lesson 1.4 section 3 is about knowing the
        // difference before somebody meets it.
        if ($basisPoints > self::BASIS_POINTS_PER_UNIT) {
            throw new \InvalidArgumentException(sprintf(
                'A tax rate above 100%% (%d basis points) is almost certainly a units error — '
                . 'ofBasisPoints() wants hundredths of a percent, so 20%% is 2000.',
                $basisPoints
            ));
        }

        return new self($basisPoints);
    }

    public static function ofPercent(int $percent): self
    {
        return self::ofBasisPoints($percent * 100);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function basisPoints(): int
    {
        return $this->basisPoints;
    }

    public function isZero(): bool
    {
        return $this->basisPoints === 0;
    }

    /**
     * The tax due on an amount. Not the amount plus tax — see addTo().
     *
     * Rounded half up, in integers. `intdiv($x * $bp + 5000, 10000)` is
     * `round($x * $bp / 10000)` with no float anywhere near it.
     *
     * ROUNDING IS A POLICY, and this class has chosen one. Half-up is the
     * common default and it is not universal: some jurisdictions round tax down
     * per line, some round the invoice total rather than each line, and getting
     * it wrong is the kind of error an auditor finds. A production system would
     * take the rounding mode as an argument. This one does not, and saying so
     * is better than pretending the question does not exist.
     */
    public function applyTo(Money $amount): Money
    {
        $tax = intdiv(
            $amount->amountMinor() * $this->basisPoints + intdiv(self::BASIS_POINTS_PER_UNIT, 2),
            self::BASIS_POINTS_PER_UNIT
        );

        return Money::of($tax, $amount->currency());
    }

    public function addTo(Money $amount): Money
    {
        return $amount->plus($this->applyTo($amount));
    }

    public function equals(self $other): bool
    {
        return $this->basisPoints === $other->basisPoints;
    }

    /** 2000 -> '20.00%' */
    public function __toString(): string
    {
        return sprintf(
            '%d.%s%%',
            intdiv($this->basisPoints, 100),
            str_pad((string) ($this->basisPoints % 100), 2, '0', STR_PAD_LEFT)
        );
    }
}
