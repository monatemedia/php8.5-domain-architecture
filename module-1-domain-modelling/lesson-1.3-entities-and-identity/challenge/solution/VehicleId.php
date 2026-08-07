<?php
declare(strict_types=1);

namespace Workshop;

/**
 * The identity of a vehicle.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * WHY THIS EXISTS AT ALL
 * ----------------------
 * By Lesson 1.2's own test — "can you name a rule this type enforces that the
 * primitive does not?" — this class should not exist. It wraps a string and
 * checks that it is not empty. That is barely a rule.
 *
 * It earns its place for a different reason: identifiers are passed around more
 * than any other value in a system, and the bug a typed identifier prevents is
 * SILENT. A `string $vehicleId` parameter accepts a job card reference, an
 * account reference, or somebody's surname. Nothing throws. The wrong record is
 * simply attached, and it is found weeks later by someone who cannot explain it.
 *
 * Being able to say why an exception to a rule is justified is the point.
 * "The rule says no but I did it anyway" is not the same thing.
 *
 * WHY TWO NAMED FACTORIES
 * -----------------------
 * `generate()` MINTS a new identity. `fromString()` RECOGNISES an existing one.
 * They mean opposite things, and a single constructor cannot say which — so
 * every caller would have to know from context, and the day somebody calls the
 * wrong one, a vehicle acquires a second identity and its history splits in two
 * with nothing to indicate it happened.
 *
 * WHY THE APPLICATION GENERATES IDENTITY
 * ---------------------------------------
 * Rule 1. If the database assigned it, a Vehicle could not be fully constructed
 * without infrastructure, `?VehicleId $id = null` would spread through the
 * model, and every test would need a database. See example 02 for the trade-off
 * in full — it is a real trade-off, not a free win.
 */
final readonly class VehicleId
{
    private function __construct(private string $value) {}

    /**
     * Mint a new identity. No arguments, no database, no coordination.
     *
     * NOT A UUID, and should not be called one. Sixteen hex characters from a
     * cryptographically secure source is ample for a course; a real system
     * should use UUID v7 from ramsey/uuid or symfony/uid, so that identifiers
     * sort by creation time and do not fragment database indexes.
     *
     * This course has no Composer dependencies before Module 3, which is why
     * the generator is written out here.
     */
    public static function generate(): self
    {
        return new self('VEH-' . strtoupper(bin2hex(random_bytes(8))));
    }

    /**
     * Recognise an identity that already exists — from storage, from a URL,
     * from another system.
     *
     * @throws \InvalidArgumentException
     */
    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException(
                'A vehicle id cannot be empty. If you meant to create a new vehicle, '
                . 'use VehicleId::generate().'
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
