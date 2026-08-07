<?php
declare(strict_types=1);

namespace Workshop;

/**
 * Sign the work off.
 *
 * REFERENCE IMPLEMENTATION, and the class where a decision made in Lesson 3.1
 * finally costs something.
 *
 * LOOK AT store()
 * ----------------
 * `remove()` then `add()`. That is not a mistake and it is not elegant; it is
 * what a COLLECTION-ORIENTED repository forces on a caller that has changed an
 * aggregate.
 *
 * Lesson 3.1 chose `add()`/`remove()` over `save()`/`delete()` deliberately,
 * warned that it had a cost, and refused to design around it in advance. It
 * said: *watch for it.* Two modules later, `SqliteJobCards` could not tell that
 * an aggregate had changed, and 3.4's contract test was written not to depend on
 * it and said so.
 *
 * This is the bill.
 *
 * AND IT IS NOT SAFE UNDER CONCURRENCY
 * -------------------------------------
 * `remove()` commits, then `add()` commits. Between them **the job card does
 * not exist**. A second request reading it in that window gets a
 * `JobCardNotFound` for a job card that is merely being saved, and a crash
 * between the two loses it entirely.
 *
 * The capstone runs single-threaded against `:memory:`, so nothing here will
 * ever show it. That does not make it fine. It makes it the kind of bug that
 * ships.
 *
 * WHAT THE TWO REAL FIXES ARE
 * ----------------------------
 *   A UNIT OF WORK — track what was loaded, compare at the end of the request,
 *   write what changed, in one transaction. Correct, and real machinery: change
 *   tracking, identity map, ordering. Most PHP teams decide it is not worth it.
 *
 *   AN EXPLICIT `save(JobCard $jobCard)` on the interface — honest, three lines,
 *   and it moves one word of storage vocabulary back into every caller. That is
 *   the trade 3.1 described, and having now paid the other side of it you are in
 *   a position to say which you would choose.
 *
 * The course does neither, on purpose, so that this doc block can exist.
 */
final readonly class CompleteJobCard
{
    public function __construct(private JobCards $jobCards) {}

    /**
     * @throws JobCardNotFound
     * @throws OutstandingAuthorisations
     * @throws IllegalStatusTransition
     */
    public function __invoke(string $jobCardId): void
    {
        $id      = JobCardId::fromString($jobCardId);
        $jobCard = $this->jobCards->get($id);

        $jobCard->complete();

        $this->store($jobCard);
    }

    /**
     * Write an aggregate that already exists.
     *
     * See the class doc block. This method is the cost of a collection-oriented
     * repository, written out in full so that nobody can miss it.
     */
    private function store(JobCard $jobCard): void
    {
        $this->jobCards->remove($jobCard->id());
        $this->jobCards->add($jobCard);

        $jobCard->releaseEvents();
    }
}
