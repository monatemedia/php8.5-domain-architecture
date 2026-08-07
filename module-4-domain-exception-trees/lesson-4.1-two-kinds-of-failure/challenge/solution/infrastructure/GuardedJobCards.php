<?php
declare(strict_types=1);

namespace Infrastructure;

use Workshop\JobCard;
use Workshop\JobCardId;
use Workshop\JobCards;
use Workshop\VehicleId;
use Workshop\WorkshopFailure;

/**
 * A `JobCards` that stops driver exceptions escaping into the application.
 *
 * REFERENCE IMPLEMENTATION. The first DECORATOR in this course, and it enforces
 * exactly one rule:
 *
 *     A WorkshopFailure passes through untouched.
 *     Any other \Exception becomes a StorageFailure.
 *     \Error is not caught at all.
 *
 * Three sentences. Everything below is those three sentences applied seven
 * times, plus the reasons each one is written the way it is.
 *
 * WHY A DECORATOR RATHER THAN try/catch IN SqliteJobCards
 * --------------------------------------------------------
 * The mechanical alternative is a try/catch inside every method of every
 * implementation. It works. Three things it does not give you:
 *
 * 1. IT WORKS ON ANY IMPLEMENTATION. In-memory, SQLite, the HTTP-backed one
 *    somebody writes next year. This class knows nothing beyond the interface,
 *    which is Lesson 3.4's substitutability being spent rather than admired.
 *
 * 2. THE POLICY IS IN ONE FILE. One place to read what gets translated, one
 *    place to change it. Spread across two implementations it is a convention,
 *    and conventions are missing from the method somebody adds in a hurry.
 *
 * 3. IT COMPOSES. A retrying decorator, a logging decorator and a caching
 *    decorator stack the same way, in an order chosen at wiring time:
 *
 *        new GuardedJobCards(new LoggingJobCards(new SqliteJobCards(...)))
 *
 *    Note that the order is a real decision. Guarding outside logging means the
 *    log sees the PDOException; guarding inside means it sees a StorageFailure.
 *    The first is usually what you want.
 *
 * THE COST, STATED HONESTLY
 * --------------------------
 * Seven methods that do nothing but delegate. It is real boilerplate, PHP has
 * no way to avoid it, and it is one more file between a caller and the thing
 * doing the work — which makes a stack trace two frames deeper and an
 * unfamiliar reader's first `get()` lookup land here rather than on the SQL.
 *
 * Whether that is worth it depends on whether driver exceptions actually leak
 * in your codebase. If there is one implementation and it is never going to
 * change, try/catch in the repository is a defensible answer and this file is
 * ceremony. Rule 6.
 *
 * WHAT IT DOES NOT DO
 * --------------------
 * It does not retry, log, cache, or open transactions. A decorator that does
 * two things is two decorators that somebody merged.
 */
final class GuardedJobCards implements JobCards
{
    /**
     * The parameter is typed to the INTERFACE, not to a concrete class. Type it
     * to SqliteJobCards and you have written a class that guards one
     * implementation, which is a try/catch with extra steps.
     */
    public function __construct(private readonly JobCards $inner) {}

    public function add(JobCard $jobCard): void
    {
        $this->guard('add', function () use ($jobCard): null {
            $this->inner->add($jobCard);

            return null;
        });
    }

    public function get(JobCardId $id): JobCard
    {
        return $this->guard('get', fn(): JobCard => $this->inner->get($id));
    }

    public function contains(JobCardId $id): bool
    {
        return $this->guard('contains', fn(): bool => $this->inner->contains($id));
    }

    public function remove(JobCardId $id): void
    {
        $this->guard('remove', function () use ($id): null {
            $this->inner->remove($id);

            return null;
        });
    }

    /** @return list<JobCard> */
    public function forVehicle(VehicleId $vehicleId): array
    {
        return $this->guard('forVehicle', fn(): array => $this->inner->forVehicle($vehicleId));
    }

    /** @return list<JobCard> */
    public function awaitingAuthorisation(): array
    {
        return $this->guard('awaitingAuthorisation', fn(): array => $this->inner->awaitingAuthorisation());
    }

    public function count(): int
    {
        return $this->guard('count', fn(): int => $this->inner->count());
    }

    /**
     * The rule, written once.
     *
     * PRIVATE, deliberately. A public `guard()` would make this class a
     * `JobCards` plus an escape hatch, and the eighth thing anybody does with
     * an escape hatch is route around the interface. The checks assert the
     * public surface is exactly the seven interface methods.
     *
     * ── `throw $e;` AND NOT `throw new SomethingElse($e->getMessage(), 0, $e)`
     *
     * Rethrowing the same object is not a shortcut; it is the requirement.
     * Building a new exception here would:
     *
     *   - report THIS file and THIS line in every stack trace, so every log
     *     entry points at the decorator instead of at the guard that fired;
     *   - flatten the subclass. A JobCardIsTerminal rewrapped as a generic
     *     failure reaches Lesson 4.4's boundary as a 500 instead of a 409, and
     *     nothing in the type system notices;
     *   - discard whatever structured context the original was carrying —
     *     which, from 4.2 onward, is most of what the exception is for.
     *
     * `checks.php` asserts identity with `===` for exactly this reason.
     *
     * ── `catch (\Exception)` AND NOT `catch (\Throwable)`
     *
     * This is the line to argue with, and the argument is section 2 of the
     * README. \Error and its subclasses — TypeError, DivisionByZeroError, a
     * method call on null — are category (c): bugs in this codebase.
     *
     * Wrapping a TypeError from the mapper as a StorageFailure tells the
     * operator on call that the database is unhealthy. The database is fine.
     * They will spend an hour on it before anyone reads the `previous` chain.
     *
     * Widening this to \Throwable is a two-character change and it is the most
     * expensive one available in this module.
     *
     * ── ORDER OF THE CLAUSES
     *
     * PHP takes the first matching clause. WorkshopFailure must therefore come
     * first: swap them and every domain failure that happens to extend
     * \DomainException — which is all of them — gets translated, and the
     * decorator becomes a machine for turning 409s into 500s.
     *
     * @template T
     *
     * @param \Closure(): T $call
     *
     * @return T
     */
    private function guard(string $operation, \Closure $call): mixed
    {
        try {
            return $call();
        } catch (WorkshopFailure $e) {
            throw $e;
        } catch (\Exception $e) {
            throw StorageFailure::during($operation, $e);
        }
    }
}
