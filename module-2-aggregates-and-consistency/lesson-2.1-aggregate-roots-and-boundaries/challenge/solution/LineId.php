<?php
declare(strict_types=1);

namespace Workshop;

/**
 * The identity of one line on a job card.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * WHY A LINE NEEDS IDENTITY AT ALL
 * ---------------------------------
 * In Lesson 1.4, LabourLine was a value object, and correctly so: two lines
 * with the same description, duration and rate were interchangeable, and
 * nothing needed to refer to one in particular.
 *
 * An aggregate changes that. `removeLine()` has to remove ONE PARTICULAR line,
 * and "the second one that looks like this" is not something you can ask of a
 * value. The moment a collection needs to be edited rather than replaced
 * wholesale, its members need identity.
 *
 * That is the entity/value distinction happening to a concept in front of you,
 * rather than being asserted about it — and note it was driven by a
 * requirement, not by the class getting more complicated.
 */
final readonly class LineId
{
    private function __construct(private string $value) {}

    public static function generate(): self
    {
        return new self('LN-' . strtoupper(bin2hex(random_bytes(8))));
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException(
                'A line id cannot be empty. To create a new line, use LineId::generate().'
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
