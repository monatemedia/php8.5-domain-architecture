<?php
declare(strict_types=1);

namespace Workshop;

/**
 * One chargeable labour line on a job card.
 *
 * REFERENCE IMPLEMENTATION — Lesson 1.4's class, with ONE ADDITION.
 *
 * WHAT CHANGED IN 2.1, AND WHAT DELIBERATELY DID NOT
 * ---------------------------------------------------
 * Added:    implements Line, a LineId, and id().
 * Unchanged: the create() signature, every guard, every other method.
 *
 * The signature constraint is not tidiness. It means:
 *
 *   - every existing caller keeps working;
 *   - Lesson 1.4's acceptance checks still pass, so finishing 2.1 does not
 *     un-finish 1.4;
 *   - a concept crossed from VALUE OBJECT to ENTITY without a single breaking
 *     change.
 *
 * That last point is the lesson. In 1.4 two labour lines with the same
 * description, duration and rate were interchangeable, and nothing needed to
 * refer to one in particular. An aggregate that can remove a line needs to
 * refer to one in particular, so the concept acquired identity — driven by a
 * new requirement, not by the class getting more complicated.
 *
 * It remains `final readonly`. An entity with immutable attributes is not a
 * contradiction: identity makes it an entity, and immutability is what makes
 * JobCard's collection safe to hand out. See example 02.
 */
final readonly class LabourLine implements Line
{
    private function __construct(
        private LineId      $id,
        private string      $description,
        private LabourUnits $units,
        private Money       $ratePerTenth,
    ) {}

    /**
     * @throws \InvalidArgumentException the description is empty
     */
    public static function create(string $description, LabourUnits $units, Money $ratePerTenth): self
    {
        $description = trim($description);

        if ($description === '') {
            throw new \InvalidArgumentException(
                'A labour line needs a description. An unnamed charge on an invoice is a '
                . 'question the account holder will ring up about.'
            );
        }

        return new self(LineId::generate(), $description, $units, $ratePerTenth);
    }

    public function id(): LineId
    {
        return $this->id;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function units(): LabourUnits
    {
        return $this->units;
    }

    public function ratePerTenth(): Money
    {
        return $this->ratePerTenth;
    }

    public function total(): Money
    {
        return $this->ratePerTenth->times($this->units->tenths());
    }

    public function __toString(): string
    {
        return sprintf('%s — %s at %s = %s', $this->description, $this->units, $this->ratePerTenth, $this->total());
    }
}
