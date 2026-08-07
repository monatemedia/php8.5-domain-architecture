<?php
declare(strict_types=1);

namespace Workshop;

/**
 * The identity of a job card.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * The same shape as VehicleId from Lesson 1.3, for the same reason: a
 * `string $id` parameter accepts any string, including an identifier of a
 * completely different kind, and the resulting bug is SILENT — the wrong string
 * in the right parameter does not throw, it attaches the wrong record.
 *
 * With four of these in the model, `attach(JobCardId, VehicleId)` cannot be
 * called with its arguments the wrong way round. That is the entire return, and
 * it is worth more than it looks, because identifiers are passed around more
 * than any other value in a system.
 *
 * ON THE DUPLICATION, WHICH IS REAL
 * ----------------------------------
 * This is the fourth near-identical class. The obvious move is a shared base
 * class or trait, and the challenge asks you to argue both sides.
 *
 * The case against extracting one: these classes are identical TODAY and have
 * no reason to stay that way. A JobCardId may acquire a human-readable
 * sequential form because the workshop quotes it over the phone; an
 * AccountHolderId may come from an accounting system in a format nobody here
 * chooses. A shared base couples four independent concepts through their least
 * interesting property, and the coupling is discovered when one of them needs
 * to change.
 *
 * The case for is equally real: four files, thirty lines each, that a reader
 * must confirm are actually identical. There is no correct answer, only a
 * choice you can defend. This course keeps them separate; write down in
 * PROGRESS.md if you would not.
 */
final readonly class JobCardId
{
    private function __construct(private string $value) {}

    public static function generate(): self
    {
        return new self('JC-' . strtoupper(bin2hex(random_bytes(8))));
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException(
                'A job card id cannot be empty. To create a new one, use JobCardId::generate().'
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
