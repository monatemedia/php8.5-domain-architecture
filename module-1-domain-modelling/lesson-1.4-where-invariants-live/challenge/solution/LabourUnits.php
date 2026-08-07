<?php
declare(strict_types=1);

namespace Workshop;

/**
 * Billable labour on one line, in tenths of an hour.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * INVARIANT I3 (DOMAIN.md): labour is billed in whole tenths of an hour,
 * rounded up, with a minimum of five tenths per line.
 *
 * THREE RULES IN ONE SENTENCE, TAKEN APART
 * -----------------------------------------
 *   "whole tenths"      INVARIANT. A fractional tenth is not a thing that
 *                       exists in this business. Enforced by the type: the
 *                       state is an int and there is no way in that is not
 *                       an int.
 *
 *   "rounded up"        INVARIANT. Never down, for any duration, for any
 *                       customer. Enforced in fromMinutes().
 *
 *   "minimum five"      ARGUABLE, and treated as an invariant here because
 *                       DOMAIN.md states it as one. See the note below —
 *                       this is the interesting part of the lesson.
 *
 * THE MINIMUM IS PROBABLY A POLICY
 * ---------------------------------
 * A half-hour minimum has every mark of a policy: it is a commercial decision,
 * it differs between workshops, a fleet contract might waive it, and somebody
 * chose the number in a meeting. By Lesson 1.4's own test — "could two
 * customers reasonably have different answers?" — the answer is plainly yes.
 *
 * It is an invariant here for one reason: DOMAIN.md says so, and DOMAIN.md is
 * standing in for the business. That is a WEAKER justification than it looks,
 * and it is left visible rather than tidied away, because it is exactly how
 * policies get welded into domain models in real projects. Somebody writes a
 * specification, the specification states a rule, and nobody asks whether the
 * rule is a fact or a preference.
 *
 * What it would look like as a policy:
 *
 *     LabourUnits::fromMinutes(int $minutes, int $minimumTenths)
 *
 * That is one parameter, and it is the correct design if the workshop would
 * ever say "not for Northgate". The question to ask is not which is prettier —
 * it is which sentence the service manager would agree with.
 *
 * NO FLOAT, ANYWHERE
 * ------------------
 * `ceil($minutes / 6)` would introduce a float into billing arithmetic for no
 * reason at all. `intdiv($minutes + 5, 6)` is the same value in integers:
 *
 *      0 -> 0      1 -> 1      6 -> 1      7 -> 2     12 -> 2     13 -> 3
 */
final readonly class LabourUnits
{
    /** See the class note. This being a const rather than a parameter is the point at issue. */
    private const int MINIMUM_TENTHS = 5;

    private const int MINUTES_PER_TENTH = 6;

    private function __construct(private int $tenths) {}

    /**
     * Measure labour from a duration a technician actually recorded.
     *
     * @throws \InvalidArgumentException the duration is negative
     */
    public static function fromMinutes(int $minutes): self
    {
        if ($minutes < 0) {
            throw new \InvalidArgumentException(
                "Labour cannot take negative time; got {$minutes} minutes."
            );
        }

        $tenths = intdiv($minutes + self::MINUTES_PER_TENTH - 1, self::MINUTES_PER_TENTH);

        return new self(max($tenths, self::MINIMUM_TENTHS));
    }

    /**
     * Rebuild from storage.
     *
     * Stricter than it first appears: it refuses anything below the minimum,
     * INCLUDING values fromMinutes() would have rounded up to. That is
     * deliberate. Five tenths is the least a line can bill, so a stored 3 is
     * not a small line — it is corrupt data, and silently accepting it would
     * mean the model trusts the database more than it trusts itself.
     *
     * This is the same argument as Lesson 3.2's reconstitution problem, met
     * early: rebuilding an object from storage must not be a way around its
     * own rules.
     *
     * @throws \InvalidArgumentException below the per-line minimum
     */
    public static function ofTenths(int $tenths): self
    {
        if ($tenths < self::MINIMUM_TENTHS) {
            throw new \InvalidArgumentException(sprintf(
                'A labour line bills at least %d tenths of an hour; got %d. A stored value '
                . 'below the minimum is corrupt data, not a small line.',
                self::MINIMUM_TENTHS,
                $tenths
            ));
        }

        return new self($tenths);
    }

    public function tenths(): int
    {
        return $this->tenths;
    }

    /**
     * Note this goes through the private constructor rather than ofTenths().
     *
     * The sum of two valid LabourUnits is always at least twice the minimum, so
     * the guard could not fire — and routing through ofTenths() would suggest
     * to a reader that it might. Say what you mean.
     */
    public function plus(self $other): self
    {
        return new self($this->tenths + $other->tenths);
    }

    public function equals(self $other): bool
    {
        return $this->tenths === $other->tenths;
    }

    /**
     * Tenths are single digits by definition, so no padding is needed.
     *
     *     5 -> '0.5 h'      10 -> '1.0 h'      23 -> '2.3 h'
     */
    public function __toString(): string
    {
        return sprintf('%d.%d h', intdiv($this->tenths, 10), $this->tenths % 10);
    }
}
