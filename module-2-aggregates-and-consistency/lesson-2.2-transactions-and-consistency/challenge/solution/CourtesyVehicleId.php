<?php
declare(strict_types=1);

namespace Workshop;

/**
 * The identity of a courtesy vehicle.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * The fifth identity class in this model, and the one that finally makes the
 * duplication argument from Lesson 2.1's quiz worth revisiting. Four was within
 * the range where duplication is cheaper than the wrong abstraction. Five is
 * where a reasonable person starts to disagree, and if you extracted a shared
 * base here rather than writing this file, that is a defensible call — write it
 * down in PROGRESS.md.
 *
 * WHY NOT USE THE FLEET CODE
 * ---------------------------
 * 'CV-01' is right there, it is unique within the fleet, and the workshop says
 * it out loud. It is a natural key, and Lesson 1.3's test applies:
 *
 *     A natural key is safe only when the business would call it a DIFFERENT
 *     THING if the key changed.
 *
 * Fleet codes get reused. CV-01 is sold, a new car arrives, and it becomes
 * CV-01 — the workshop does not think of that as the same vehicle, but every
 * historical job card keyed on 'CV-01' now points at a car that was never in
 * the workshop. Two different vehicles, one key, and the service history of one
 * attached to the other.
 *
 * So: a surrogate identity, and the fleet code becomes an attribute somebody
 * can reprint on a keyring.
 */
final readonly class CourtesyVehicleId
{
    private function __construct(private string $value) {}

    public static function generate(): self
    {
        return new self('CVEH-' . strtoupper(bin2hex(random_bytes(8))));
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException(
                'A courtesy vehicle id cannot be empty. To create a new one, use '
                . 'CourtesyVehicleId::generate().'
            );
        }

        return new self($value);
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
