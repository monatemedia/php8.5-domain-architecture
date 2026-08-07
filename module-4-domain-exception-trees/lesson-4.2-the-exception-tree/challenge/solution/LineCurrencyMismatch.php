<?php
declare(strict_types=1);

namespace Workshop;

/**
 * A line in one currency was offered to a job card in another.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * A GUARD THAT EXISTS ONLY TO IMPROVE A MESSAGE
 * ----------------------------------------------
 * `Money::plus()` already refuses to add EUR to GBP, so `total()` would throw
 * without this check. Lesson 2.1 kept the guard anyway, and said why: the
 * message from Money complains about arithmetic, and the caller's actual
 * mistake was putting a line on the wrong job card.
 *
 * The tree makes that argument stronger rather than weaker. It is not just a
 * better sentence now — it is a different TYPE, with the job card's currency
 * and the line's currency as fields, which a client can render as "this line is
 * priced in GBP; job card 1042 bills in EUR" without parsing anything.
 *
 * The cost stated in 2.1 has not changed: a reader cannot tell which of the two
 * checks is really enforcing the rule, and if Money's guard were ever relaxed
 * this would silently become the only one. Worth re-reading Lesson 1.4 section
 * 5 on one rule with two presentations.
 */
final class LineCurrencyMismatch extends JobCardException
{
    public static function between(
        JobCardId $jobCardId,
        Currency $jobCardCurrency,
        string $lineDescription,
        Currency $lineCurrency,
    ): self {
        return new self(
            $jobCardId,
            sprintf(
                'Job card %s is in %s; the line "%s" is in %s.',
                $jobCardId,
                $jobCardCurrency->value,
                $lineDescription,
                $lineCurrency->value
            ),
            [
                'jobCardCurrency' => $jobCardCurrency->value,
                'lineCurrency'    => $lineCurrency->value,
                'lineDescription' => $lineDescription,
            ]
        );
    }

    public function failureCode(): string
    {
        return 'line-currency-mismatch';
    }
}
