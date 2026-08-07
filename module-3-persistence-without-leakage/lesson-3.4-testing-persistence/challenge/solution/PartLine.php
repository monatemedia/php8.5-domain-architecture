<?php
declare(strict_types=1);

namespace Workshop;

/**
 * A part fitted to a vehicle and charged out on a job card.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * An ENTITY with IMMUTABLE ATTRIBUTES, which sounds contradictory and is not.
 * Identity is what makes it an entity — the job card must be able to remove one
 * particular line. Immutability is a separate decision, and here it is what
 * makes the job card's collection safe to hand out: example 02 shows an
 * aggregate whose every guard is correct and whose lines are mutable, and it
 * enforces nothing at all.
 *
 * Changing a line therefore means removing it and adding another. That reads as
 * heavy-handed until you notice it forces every change through the root, which
 * is where the rules are.
 *
 * WHY total() IS DERIVED
 * ----------------------
 * Same argument as LabourLine in Lesson 1.4: a stored total can disagree with
 * its parts, a derived one cannot, and the cheapest invariant is one that
 * cannot be written down wrong.
 *
 * It is safe to derive here for a specific reason worth naming: Money is
 * immutable, and this line holds its own copy of the unit price it was created
 * with. Recomputing in five years gives the same answer, because the inputs are
 * frozen. If the CALCULATION could change — a new tax treatment, a different
 * rounding rule — deriving would silently re-price history, and the total would
 * have to be stored. Section 6 of the README argues that at length.
 */
final readonly class PartLine implements Line
{
    private function __construct(
        private LineId     $id,
        private PartNumber $partNumber,
        private string     $description,
        private int        $quantity,
        private Money      $unitPrice,
    ) {}

    /**
     * @throws \InvalidArgumentException
     */
    public static function create(
        PartNumber $partNumber,
        string     $description,
        int        $quantity,
        Money      $unitPrice,
    ): self {
        $description = trim($description);

        if ($description === '') {
            throw new \InvalidArgumentException(
                'A part line needs a description. A part number alone is not something an '
                . 'account holder can check against the work that was done.'
            );
        }

        // Zero is not allowed. A line for no parts is not a small line, it is a
        // line that should not exist — and it would print on an invoice.
        if ($quantity < 1) {
            throw new \InvalidArgumentException(sprintf(
                'A part line must be for at least one part; got %d.',
                $quantity
            ));
        }

        // No guard on $unitPrice. There cannot be an invalid Money, and a zero
        // one is legitimate: parts supplied under warranty are charged at nil
        // and still have to appear on the job card.
        return new self(LineId::generate(), $partNumber, $description, $quantity, $unitPrice);
    }

    /**
     * Rebuild a line that already exists, from storage.
     *
     * ADDED IN LESSON 3.2. Same shape and same reasoning as
     * LabourLine::reconstitute() — see the long note there.
     *
     * The quantity guard is skipped here for the same reason the description
     * guard is skipped there: this row already satisfied it once, and a model
     * that re-validates its own storage will refuse to load history the day a
     * rule tightens.
     */
    public static function reconstitute(
        LineId     $id,
        PartNumber $partNumber,
        string     $description,
        int        $quantity,
        Money      $unitPrice,
    ): self {
        return new self($id, $partNumber, $description, $quantity, $unitPrice);
    }

    public function id(): LineId
    {
        return $this->id;
    }

    public function partNumber(): PartNumber
    {
        return $this->partNumber;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function total(): Money
    {
        return $this->unitPrice->times($this->quantity);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s x%d  %s (%s) = %s',
            $this->partNumber,
            $this->quantity,
            $this->description,
            $this->unitPrice,
            $this->total()
        );
    }
}
