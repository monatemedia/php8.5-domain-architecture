<?php
declare(strict_types=1);

namespace Workshop;

/**
 * One chargeable labour line on a job card.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * THREE CHECKS, IN THREE PLACES, EACH THE ONLY WAY IN
 * ----------------------------------------------------
 * Example 03 starts from the same concept with NINE checks across four places,
 * two of which disagree with each other. Here:
 *
 *   1. "labour is a valid quantity"  ->  LabourUnits, at construction
 *   2. "the rate is a valid amount"  ->  Money, at construction
 *   3. "a line has a description"    ->  here, at construction
 *
 * There is no fourth. The invariant "the total matches the line" needs no guard
 * at all, because the total is derived — see below.
 *
 * The check count went DOWN, and that is the part people do not expect.
 * "Validate everywhere" is not more thorough; it is the same rule copied until
 * the copies disagree, at which point it is LESS thorough, because nobody can
 * tell which copy is the rule.
 *
 * THE TOTAL IS DERIVED, NOT STORED
 * ---------------------------------
 * A stored total can disagree with its parts. A derived one cannot, so the
 * invariant is not enforced — it is unrepresentable.
 *
 *     The cheapest invariant is one that cannot be written down wrong.
 *
 * Reach for that before reaching for a check.
 *
 * The cost is real and is not hidden: deriving a total across a thousand lines
 * on every read is not free. Lesson 2.1 comes to the OPPOSITE conclusion for
 * the job card's total, for a reason worth watching for — and the fact that two
 * lessons in the same course disagree about this is not an inconsistency. It is
 * what "it depends" looks like when both sides are stated.
 *
 * WHY THE RATE IS PER TENTH
 * --------------------------
 * The workshop quotes an hourly rate. This class takes a rate per tenth, and
 * the reason is that Money has no divide() — deliberately, because dividing
 * money raises a remainder question a value object cannot answer.
 *
 * So the conversion from "90 an hour" to "9 per tenth" happens ONCE, where the
 * rate is configured, rather than on every line. Same move as normalising a
 * registration at construction: do the awkward thing at one boundary, and
 * everything downstream is arithmetic that cannot go wrong.
 *
 * The downside, stated plainly: the stored rate is no longer the number the
 * business says out loud, so somebody has to know about the conversion. If
 * hourly rates are not always divisible by ten, the conversion itself has a
 * remainder problem and the boundary has to decide what to do with it — which
 * is the same question moved somewhere it can be answered, not abolished.
 *
 * WHY THIS IS A VALUE OBJECT AND NOT AN ENTITY
 * ---------------------------------------------
 * Two labour lines with the same description, duration and rate are
 * interchangeable — there is no "this one, specifically". It becomes an entity
 * in Module 2, when a job card needs to remove one particular line, and
 * watching WHY that changes is a better demonstration of the entity/value
 * distinction than any definition.
 */
final readonly class LabourLine
{
    private function __construct(
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

        // No guard on $units and none on $ratePerTenth. There cannot be an
        // invalid one of either — that is the whole return on Lesson 1.2.
        return new self($description, $units, $ratePerTenth);
    }

    public function description(): string
    {
        return $this->description;
    }

    public function units(): LabourUnits
    {
        return $this->units;
    }

    /**
     * Note: per TENTH of an hour, not per hour. See the class note.
     */
    public function ratePerTenth(): Money
    {
        return $this->ratePerTenth;
    }

    /**
     * Derived on every call. There is no stored total to drift.
     *
     * Money::times() takes an int and returns Money, so the currency comes
     * along and cannot be lost.
     */
    public function total(): Money
    {
        return $this->ratePerTenth->times($this->units->tenths());
    }

    public function __toString(): string
    {
        return sprintf('%s — %s at %s = %s', $this->description, $this->units, $this->ratePerTenth, $this->total());
    }
}
