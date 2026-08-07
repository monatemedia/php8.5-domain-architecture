<?php
declare(strict_types=1);

namespace Workshop;

/**
 * Anything that can appear as a chargeable line on a job card.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * THE NARROWNESS IS THE POINT
 * ----------------------------
 * Three methods. JobCard needs to identify a line, describe it on an invoice,
 * and add it up — and it needs nothing else, because it is not the job card's
 * business whether a line is labour or parts.
 *
 * Everything that was tempting and is absent:
 *
 *   quantity()      meaningless for labour
 *   unitPrice()     meaningless for labour
 *   units()         meaningless for parts
 *   isLabour()      a type check wearing a method name; whatever branches on it
 *                   is logic that belongs on the line, not on the caller
 *
 * A wide interface here would force one line type to answer questions it has no
 * answer to, and the usual result is methods that return null or throw. That is
 * the Interface Segregation Principle from the previous course, and it bites
 * hardest at exactly this boundary.
 *
 * WHY AN INTERFACE AND NOT AN ABSTRACT CLASS
 * -------------------------------------------
 * There is nothing to share. LabourLine bills tenths of an hour against a rate;
 * PartLine bills a quantity against a unit price. They have no common
 * implementation, only a common role — which is precisely the decision table
 * from Lesson 1.2 of the previous course.
 */
interface Line
{
    public function id(): LineId;

    public function description(): string;

    public function total(): Money;
}
