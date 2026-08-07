<?php
declare(strict_types=1);

namespace Workshop;

/**
 * A vehicle registration number.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * INVARIANT I2 (DOMAIN.md): stored normalised — uppercase, separators
 * stripped. Two registrations that normalise the same ARE the same
 * registration.
 *
 * WHY THIS EARNS ITS FILE
 * -----------------------
 * Lesson 1.2, section 6 says a value object must enforce a rule the primitive
 * does not. This one has two, and the first is the important one:
 *
 *   1. NORMALISATION. 'AB12CDE', 'ab12cde', 'AB-12-CDE' and '  ab 12 cde ' are
 *      one registration. Without a type, that normalisation is duplicated at
 *      every comparison in the system, and one of the copies will forget the
 *      hyphen. With a type it happens once, here, and every comparison
 *      afterwards is a plain string equality that cannot be got wrong.
 *
 *   2. FORMAT. A registration is not any string. '' is not one, and neither is
 *      a forty-character sentence someone pasted into the wrong field.
 *
 * A DELIBERATE SIMPLIFICATION, STATED HONESTLY
 * ---------------------------------------------
 * Real registration formats are national and this one rule fits none of them
 * exactly. It requires at least one letter and at least one digit, which is
 * true of most European formats and false of several elsewhere — plates that
 * are all digits exist, and this class would reject them.
 *
 * That is a bug in the RULE, not in the modelling, and it is left in on purpose
 * because it is the most common way validation goes wrong in practice:
 * somebody writes the pattern from a specification rather than from the data,
 * it passes every test, and it rejects a real customer eighteen months later.
 *
 * The thing to take away is not the pattern. It is the SHAPE: normalise at
 * construction, validate at construction, compare by the normalised form. Swap
 * the rule for a per-country strategy and everything around it still holds.
 */
final readonly class RegistrationNumber
{
    private const int MIN_LENGTH = 2;
    private const int MAX_LENGTH = 8;

    private function __construct(private string $value) {}

    /**
     * @throws \InvalidArgumentException the value is not a registration number
     */
    public static function fromString(string $raw): self
    {
        $normalised = self::normalise($raw);

        // Three checks rather than one clever pattern. It is more lines, and it
        // lets each failure say which rule it broke — which matters a great
        // deal when the message reaches somebody typing at a service desk.
        if ($normalised === '') {
            throw new \InvalidArgumentException('A registration number cannot be empty.');
        }

        $length = strlen($normalised);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'A registration number must be %d to %d characters once separators are '
                . 'removed; "%s" is %d.',
                self::MIN_LENGTH,
                self::MAX_LENGTH,
                $normalised,
                $length
            ));
        }

        if (preg_match('/^[A-Z0-9]+$/', $normalised) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'A registration number may contain only letters and digits; "%s" does not.',
                $normalised
            ));
        }

        if (preg_match('/[A-Z]/', $normalised) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'A registration number must contain at least one letter; "%s" does not.',
                $normalised
            ));
        }

        if (preg_match('/[0-9]/', $normalised) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'A registration number must contain at least one digit; "%s" does not.',
                $normalised
            ));
        }

        return new self($normalised);
    }

    /**
     * The normalised form. This is the only form that exists — the raw string
     * the caller supplied is not kept, on purpose.
     *
     * Keeping the original "as typed" is a reasonable thing to want, and it is
     * a reasonable thing to add. It is also the first step towards two fields
     * that can disagree, so if you add it, be clear that the normalised one is
     * the value and the original is a display detail.
     */
    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Trim, strip every space and hyphen anywhere in the string, uppercase.
     *
     * Note that `str_replace` runs over the whole string, not just the ends —
     * 'A B 1 2 C D E' is one registration typed with spaces between every
     * character, which is exactly how a plate reads out loud.
     */
    private static function normalise(string $raw): string
    {
        return strtoupper(str_replace([' ', '-'], '', trim($raw)));
    }
}
