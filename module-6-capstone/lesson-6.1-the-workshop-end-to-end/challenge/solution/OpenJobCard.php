<?php
declare(strict_types=1);

namespace Workshop;

/**
 * Book a vehicle in.
 *
 * REFERENCE IMPLEMENTATION. The first APPLICATION SERVICE in the course, and
 * the layer the previous five modules never needed.
 *
 * WHAT AN APPLICATION SERVICE IS FOR
 * -----------------------------------
 * One use case, one method, and it does exactly three things:
 *
 *   1. turn primitives from the edge into domain types
 *   2. call the domain
 *   3. put the result where it goes
 *
 * It contains NO business rules. Read the body: there is not a single `if`.
 * Every refusal comes from a domain type — `JobCardId::fromString()` rejects an
 * empty id, `Currency::from()` rejects a currency that does not exist,
 * `JobCards::add()` rejects a duplicate — and this class simply lets them
 * through.
 *
 * The moment an `if` appears here, ask what it is deciding. If it is a business
 * rule it belongs on an aggregate, and the reason it ended up here is almost
 * always that the aggregate was inconvenient to change.
 *
 * WHY IT IS IN `Workshop\` AND NOT `Infrastructure\`
 * ---------------------------------------------------
 * Because it imports nothing infrastructural, and the import scan proves it.
 * It knows `JobCards` — the interface Lesson 3.1 put in the domain — and
 * nothing about SQLite, HTTP or a container.
 *
 * BE HONEST ABOUT THE COMPROMISE, THOUGH: a use case is properly an
 * APPLICATION layer concern, not a domain one, and a three-layer project would
 * give it `Application\` of its own. This course has two namespace roots and
 * adding a third for four classes would be ceremony — Rule 6 — so it shares the
 * domain's root and submits to the domain's scan.
 *
 * That is strictly a harder standard than it would face in `Application\`, so
 * nothing is being smuggled past. Section 4 of the README argues it properly.
 */
final readonly class OpenJobCard
{
    public function __construct(private JobCards $jobCards) {}

    /**
     * @param string $vehicleId       as it arrived from the edge
     * @param string $accountHolderId as it arrived from the edge
     * @param string $currency        a three-letter code
     *
     * @throws InvalidWorkshopInput  one of the strings is not a valid identity
     * @throws JobCardAlreadyExists  that identity is already in the collection
     */
    public function __invoke(
        string $vehicleId,
        string $accountHolderId,
        string $currency,
    ): JobCardId {
        $id = JobCardId::generate();

        $jobCard = JobCard::open(
            $id,
            VehicleId::fromString($vehicleId),
            AccountHolderId::fromString($accountHolderId),
            Currency::from($currency),
        );

        $this->jobCards->add($jobCard);

        // The events recorded by open() are released and dropped on the floor.
        //
        // A real application dispatches them here, AFTER the write, because a
        // handler that emails a customer cannot be rolled back — Lesson 2.3.
        // The capstone releases them so the aggregate does not accumulate, and
        // does not build a dispatcher, because a dispatcher with no
        // subscribers is a class that exists to be pointed at.
        $jobCard->releaseEvents();

        return $id;
    }
}
