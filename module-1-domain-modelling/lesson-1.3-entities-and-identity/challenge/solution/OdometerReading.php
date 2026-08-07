<?php
declare(strict_types=1);

namespace Workshop;

/**
 * A single odometer reading.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * WHAT THIS CLASS DOES NOT KNOW
 * ------------------------------
 * Invariant I4 — "an odometer reading may never decrease for a given vehicle" —
 * is NOT enforced here, and cannot be. It is a rule about a SEQUENCE of
 * readings, and a single reading has no sequence. Holding this object and
 * nothing else, there is no way to tell whether 84,500 is a valid next reading.
 *
 * So the rule lives on Vehicle, which is the thing that has the previous one.
 * That split is the pattern for most interesting entity rules:
 *
 *     the value knows what a valid VALUE is
 *     the entity knows what a valid CHANGE is
 *
 * A GUARD THAT IS NOT A BUSINESS RULE
 * ------------------------------------
 * The two-million bound is a SANITY CHECK. Its job is to catch a transposed
 * digit at the service desk, not to state a fact about vehicles.
 *
 * That distinction matters, and it is the challenge's third follow-up question.
 * The day a haulage contract brings in a truck on 2.4 million kilometres, this
 * guard refuses a true reading. Nobody debates it, because it looks like
 * validation — so somebody types 999,999 instead, and the service history is
 * quietly wrong forever.
 *
 * A sanity check that cannot be overridden has become a business rule by
 * accident. The honest options are a much higher bound, or a bound with a
 * documented override path. Lesson 1.4 is about noticing which of your guards
 * are which BEFORE the truck arrives.
 */
final readonly class OdometerReading
{
    /**
     * Not a business rule. See the class note above.
     */
    private const int MAX_PLAUSIBLE_KM = 2_000_000;

    private function __construct(private int $kilometres) {}

    /**
     * @throws \InvalidArgumentException
     */
    public static function of(int $kilometres): self
    {
        if ($kilometres < 0) {
            throw new \InvalidArgumentException(
                "An odometer reading cannot be negative; got {$kilometres}."
            );
        }

        if ($kilometres > self::MAX_PLAUSIBLE_KM) {
            throw new \InvalidArgumentException(sprintf(
                'An odometer reading of %s km is above the plausible bound of %s km. '
                . 'This guard exists to catch a transposed digit — if the reading is '
                . 'genuine, the bound is what needs changing, not the reading.',
                number_format($kilometres),
                number_format(self::MAX_PLAUSIBLE_KM)
            ));
        }

        return new self($kilometres);
    }

    public function kilometres(): int
    {
        return $this->kilometres;
    }

    public function isLessThan(self $other): bool
    {
        return $this->kilometres < $other->kilometres;
    }

    public function equals(self $other): bool
    {
        return $this->kilometres === $other->kilometres;
    }

    public function __toString(): string
    {
        return number_format($this->kilometres) . ' km';
    }
}
