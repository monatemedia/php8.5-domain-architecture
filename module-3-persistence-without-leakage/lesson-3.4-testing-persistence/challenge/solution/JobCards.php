<?php
declare(strict_types=1);

namespace Workshop;

/**
 * The workshop's job cards.
 *
 * REFERENCE IMPLEMENTATION. The first REPOSITORY INTERFACE in the course, and
 * the whole lesson is that this file is in `domain/workshop/` while everything
 * that implements it is not.
 *
 * READ THE METHOD NAMES OUT LOUD
 * -------------------------------
 * "Job cards awaiting authorisation." "Job cards for this vehicle." Those are
 * sentences the service manager would recognise, and that is the test: the
 * interface is DOMAIN VOCABULARY, and the SQL that answers it never appears
 * here.
 *
 * Compare what this interface deliberately does not say:
 *
 *     findBy(array $criteria)        a query language, leaking through a name
 *     findWhereStatusEquals(...)     the storage model, wearing a method name
 *     query(): QueryBuilder          the database, handed to the caller
 *     getConnection()                no comment
 *
 * Each of those makes the domain's callers think in terms of storage. Once one
 * exists, the layer boundary is decorative.
 *
 * IT DEALS IN AGGREGATES, NOT ROWS
 * ---------------------------------
 * Every method takes or returns `JobCard` — never an array of columns, never a
 * DTO, never a `stdClass` from a fetch. A repository that returns rows has not
 * inverted anything; it is a data access object with a nicer name, and every
 * caller now has to know how to turn rows into objects, which means every
 * caller can do it slightly differently.
 *
 * COLLECTION-ORIENTED, AND THE PROBLEM THAT CAUSES
 * -------------------------------------------------
 * `add()` and `remove()`, not `save()` and `delete()`. This interface pretends
 * to be a collection of job cards that happens to outlive the request, which is
 * the shape that keeps storage out of the caller's vocabulary.
 *
 * It has a well-known cost, and it is better to meet it now than to be
 * surprised by it in 3.2. An in-memory implementation holds references, so a
 * change to an aggregate you already added is visible immediately and nothing
 * further is needed. A SQLite implementation cannot do that — it has no way to
 * know you changed anything.
 *
 * The classical answer is a UNIT OF WORK: something that tracks which
 * aggregates were loaded, compares them at the end of the request, and writes
 * what changed. That is real machinery, and most PHP codebases decide it is not
 * worth it and add an explicit `save()` instead — which is honest, and which
 * quietly moves one word of storage vocabulary back into the caller.
 *
 * This course takes the collection-oriented shape in 3.1 and confronts the
 * consequence in 3.2 rather than designing around it in advance. Watch for it.
 *
 * WHY THE INTERFACE IS HERE AND THE IMPLEMENTATION IS NOT
 * -------------------------------------------------------
 * This is the DEPENDENCY INVERSION PRINCIPLE with something at stake. The
 * domain declares what it needs, in its own words. Infrastructure supplies it.
 * The arrow points inward, and `domain/workshop/` imports nothing from
 * `infrastructure/` — which the capstone proves by scanning the imports.
 */
interface JobCards
{
    /**
     * Put a job card into the collection.
     *
     * @throws \DomainException a job card with that identity is already here
     */
    public function add(JobCard $jobCard): void;

    /**
     * The job card with this identity.
     *
     * For when the caller knows it exists — a job card being invoiced, a job
     * card named in a URL that was generated from a list.
     *
     * @throws JobCardNotFound
     */
    public function get(JobCardId $id): JobCard;

    /**
     * For when the caller does not know. See the note in JobCardNotFound about
     * why both shapes exist.
     */
    public function contains(JobCardId $id): bool;

    /**
     * @throws JobCardNotFound
     */
    public function remove(JobCardId $id): void;

    /**
     * Every job card for a vehicle — its service history, in the workshop's
     * words.
     *
     * @return list<JobCard> most recently added last
     */
    public function forVehicle(VehicleId $vehicleId): array;

    /**
     * Every job card waiting on an account holder to approve additional work.
     *
     * This is the one to read aloud. It is a question the service manager asks
     * every morning, and the interface asks it in their words — not
     * `findByStatus('awaiting_authorisation')`, which is the storage model
     * wearing a method name.
     *
     * @return list<JobCard>
     */
    public function awaitingAuthorisation(): array;

    public function count(): int;
}
