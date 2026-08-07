<?php
declare(strict_types=1);

namespace Workshop;

/**
 * The currencies this workshop trades in.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * WHY AN ENUM AND NOT A STRING
 * ----------------------------
 * Two reasons, and the second is the one that matters.
 *
 * 1. A closed set. `string $currency` accepts 'EUR', 'eur', 'euro', 'EURO' and
 *    ''. An enum accepts exactly five things, and the check happens once, at
 *    the boundary, rather than everywhere.
 *
 * 2. It has behaviour. How many decimal places a currency has is knowledge
 *    about the currency, so it belongs on the currency. A codebase that
 *    hard-codes `/ 100` somewhere else is correct until its first JPY
 *    transaction, at which point it is wrong by a factor of a hundred in a way
 *    that looks like a data-entry problem for several weeks.
 *
 * This is Lesson 1.1's argument — move the rule to the thing that owns it —
 * applied to something most people would have left as a string constant.
 *
 * NOT MODELLED, on purpose: exchange rates. A rate needs a date, a source and a
 * rounding policy, all of which come from outside the domain. Money refuses to
 * mix currencies precisely so that conversion has to be an explicit operation
 * somebody thinks about, rather than something that happens silently inside an
 * addition.
 */
enum Currency: string
{
    case EUR = 'EUR';
    case USD = 'USD';
    case GBP = 'GBP';
    case JPY = 'JPY';
    case KWD = 'KWD';

    /**
     * How many decimal places this currency is subdivided into.
     *
     * Not every currency has two. JPY has none — there is no such thing as a
     * hundredth of a yen. KWD has three. Both are in this enum for exactly that
     * reason: a course that only ever showed two-decimal currencies would teach
     * the `/ 100` habit while claiming to teach the opposite.
     */
    public function minorUnits(): int
    {
        return match ($this) {
            self::JPY => 0,
            self::KWD => 3,
            self::EUR, self::USD, self::GBP => 2,
        };
    }

    /**
     * Render an amount in minor units for a human.
     *
     * Note there is no float anywhere in here. `intdiv()` and `%` split the
     * amount into its two halves exactly, and `str_pad()` handles the zeros.
     * The moment a float appears in money-formatting code, 4,500 minor units
     * becomes 44.99999999999999 on some machine somewhere.
     *
     *     Currency::EUR->format(4500)   'EUR 45.00'
     *     Currency::EUR->format(7)      'EUR 0.07'
     *     Currency::JPY->format(4500)   'JPY 4500'
     *     Currency::KWD->format(4500)   'KWD 4.500'
     */
    public function format(int $amountMinor): string
    {
        $places = $this->minorUnits();

        // Zero minor units: there is no fractional part, so there must be no
        // decimal point either. 'JPY 4500.' and 'JPY 4500.0' are both wrong.
        if ($places === 0) {
            return $this->value . ' ' . $amountMinor;
        }

        $divisor  = 10 ** $places;
        $major    = intdiv($amountMinor, $divisor);
        $fraction = $amountMinor % $divisor;

        return sprintf(
            '%s %d.%s',
            $this->value,
            $major,
            str_pad((string) $fraction, $places, '0', STR_PAD_LEFT)
        );
    }
}
