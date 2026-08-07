<?php
declare(strict_types=1);

namespace Workshop;

/**
 * A quantity that is not a quantity.
 *
 * REFERENCE IMPLEMENTATION. The second leaf on the `InvalidWorkshopInput`
 * branch, and it exists because the legacy service it replaces did not check.
 *
 * `ReservationService::reserve($row, -5)` passed its guard — minus five is
 * comfortably less than what is available — and quietly REDUCED the
 * reservation. Nobody was doing that on purpose; it was reachable from a form
 * field with no validation on it.
 *
 * WHY IT IS INPUT AND NOT A BUSINESS RULE
 * ----------------------------------------
 * The caller could have known. Minus five is negative in the controller, in the
 * request, and in the caller's own variable, and nothing about the shelf was
 * consulted to find out. Lesson 4.2's test, and it maps to 422 rather than 409.
 *
 * WHY IT IS NOT A VALUE OBJECT — AND THE ADMISSION THAT IT SHOULD BE
 * -------------------------------------------------------------------
 * A `Quantity` value object would make the invalid case unrepresentable rather
 * than merely refused, which is Lesson 1.2's argument and strictly better.
 *
 * It is not built here because this is a STRANGLER. Every public method on the
 * new aggregate has to accept exactly what the legacy call sites already pass,
 * and those pass `int`. Introducing a value object means touching every caller
 * in the same change — which is how a two-day extraction becomes a six-week
 * branch.
 *
 * Add it when the call sites are yours. That is a real cost of working this
 * way, and it is worth seeing one stated rather than implied.
 */
final class InvalidQuantity extends InvalidWorkshopInput
{
    public static function mustBePositive(int $given): self
    {
        return new self(
            sprintf('A quantity must be a positive whole number; %d was given.', $given),
            ['given' => $given]
        );
    }

    public function failureCode(): string
    {
        return 'invalid-quantity';
    }
}
