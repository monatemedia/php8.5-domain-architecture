<?php
declare(strict_types=1);

namespace Workshop;

/**
 * Anything that went wrong about a particular job card.
 *
 * REFERENCE IMPLEMENTATION. The intermediate node of the tree, and the one
 * worth defending, because intermediate nodes are where exception hierarchies
 * usually go wrong.
 *
 * WHY THIS NODE EXISTS
 * ---------------------
 * Not because a diagram wanted three levels. Two concrete reasons, and a node
 * with neither should not exist:
 *
 * 1. IT HOLDS SOMETHING REAL. Every failure below it is about one job card, so
 *    every one of them needs the identity — in the message, in the context, and
 *    in whatever the boundary renders. Declaring `JobCardId $jobCardId` eight
 *    times is the duplication a shared parent is for.
 *
 * 2. SOMEBODY WANTS TO CATCH EXACTLY THIS SET. A job-card use case can write
 *    `catch (JobCardException $e)` and know it has every failure about the
 *    aggregate it is working on, and nothing about the fleet or the tax rate.
 *    That is a real clause somebody writes, not a hypothetical one.
 *
 * THE TEST FOR ADDING A NODE
 * ---------------------------
 *     Name the catch clause that would use it, and the field it holds.
 *     If you can do neither, you are drawing a diagram.
 *
 * WHAT THE COURSE DELIBERATELY DOES NOT ADD
 * ------------------------------------------
 * A `CourtesyVehicleException` node, sitting between `WorkshopException` and a
 * single `CourtesyVehicleUnavailable`.
 *
 * It is tempting — the fleet is the second aggregate root, and symmetry is
 * pleasant. It fails both parts of the test. Nobody writes
 * `catch (CourtesyVehicleException)` when there is one subclass, because
 * catching the subclass is strictly more precise. And with one child there is
 * no duplication to hoist.
 *
 * A node with one child is a rename with extra steps. Add it when the second
 * class arrives, which is a two-minute change at that point and costs nothing
 * today. The challenge lists the fleet's throw sites so you can do exactly that
 * on your own copy.
 *
 * ABSTRACT, AND SO IS EVERY NODE
 * -------------------------------
 * `throw new JobCardException(...)` would be a failure whose type says "job
 * card" and nothing else, which is most of the way back to
 * `throw new \DomainException`. The compiler refuses it.
 *
 * WHY THE IDENTITY IS A PROPERTY AND NOT ONLY A CONTEXT KEY
 * ----------------------------------------------------------
 * `$e->jobCardId` is typed; `$e->context()['jobCardId']` is a string that might
 * be missing. PHP code catching this wants the first — a retry handler passing
 * it to a repository, a use case deciding what to reload.
 *
 * The context entry exists as well, because Lesson 4.4 serialises context and
 * cannot serialise a `JobCardId`. Both, and the duplication is deliberate: one
 * for callers inside the process, one for the wire.
 */
abstract class JobCardException extends WorkshopException
{
    /**
     * `public private(set)` — readable everywhere, writable only inside this
     * class. Promotion assigns it here, in this constructor, which is the one
     * place allowed to.
     *
     * The alternative, `public readonly`, would also work. `private(set)` is
     * chosen because it is what the rest of the course uses for "the world may
     * look, only I may write", and consistency in that idiom is worth more than
     * saving a word.
     *
     * @param array<string, string|int|float|bool|null|list<string|int|float|bool|null>> $context
     */
    protected function __construct(
        public private(set) JobCardId $jobCardId,
        string $message,
        array $context = [],
    ) {
        // The identity goes into the context for every subclass, once, here.
        // Written FIRST so a subclass cannot accidentally overwrite it with a
        // key of the same name — later keys in the union operator lose.
        parent::__construct($message, ['jobCardId' => $jobCardId->value()] + $context);
    }
}
