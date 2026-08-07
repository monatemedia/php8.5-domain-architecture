<?php
declare(strict_types=1);

namespace Workshop;

/**
 * A manufacturer's part number.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * Same shape as RegistrationNumber from Lesson 1.2 — normalise at
 * construction, validate at construction, compare by the normalised form — with
 * one deliberate difference: HYPHENS ARE KEPT.
 *
 * A registration written 'AB-12-CDE' is the same registration as 'AB12CDE'; the
 * hyphens are punctuation somebody added for readability. A part number is not
 * like that. K9K-1234 and K9K1234 may well be two different parts, and
 * stripping the hyphen would quietly merge them.
 *
 * The two classes look almost identical and one character of their behaviour
 * differs, for a reason that comes from the business rather than from the code.
 * If you find yourself extracting a shared "NormalisedString" base class,
 * re-read this note: that base class would have to be parameterised by exactly
 * the thing that makes the two concepts different.
 */
final readonly class PartNumber
{
    private const int MIN_LENGTH = 3;
    private const int MAX_LENGTH = 24;

    private function __construct(private string $value) {}

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromString(string $raw): self
    {
        $normalised = strtoupper(str_replace(' ', '', trim($raw)));

        if ($normalised === '') {
            throw new \InvalidArgumentException('A part number cannot be empty.');
        }

        $length = strlen($normalised);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'A part number must be %d to %d characters; "%s" is %d.',
                self::MIN_LENGTH,
                self::MAX_LENGTH,
                $normalised,
                $length
            ));
        }

        if (preg_match('/^[A-Z0-9-]+$/', $normalised) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'A part number may contain only letters, digits and hyphens; "%s" does not.',
                $normalised
            ));
        }

        // A leading or trailing hyphen is almost always a truncated paste or a
        // half-typed entry, and it would compare unequal to the same part
        // number entered properly.
        if (str_starts_with($normalised, '-') || str_ends_with($normalised, '-')) {
            throw new \InvalidArgumentException(sprintf(
                'A part number cannot start or end with a hyphen; "%s" does.',
                $normalised
            ));
        }

        return new self($normalised);
    }

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
}
