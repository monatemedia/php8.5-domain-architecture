<?php
declare(strict_types=1);

namespace Workshop;

/**
 * A failure that can explain itself in fields rather than in a sentence.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * WHY THIS IS A SECOND INTERFACE AND NOT TWO METHODS ON WorkshopFailure
 * ----------------------------------------------------------------------
 * The obvious move is to add `failureCode()` and `context()` to
 * `WorkshopFailure`, which already exists and already means "ours". Lesson
 * 4.1's checks forbid it — they assert that interface declares no methods at
 * all — and that constraint is deliberate rather than incidental.
 *
 * `WorkshopFailure` answers one question: *may I catch this as a business
 * refusal?* Every throwable the domain will ever produce has to answer it, and
 * the answer is the same shape forever. That is why it can be frozen.
 *
 * This interface answers a different question: *what do you know, in fields?*
 * Not everything needs to. A failure with no data beyond its identity is a
 * legitimate thing to have, and forcing it to declare `context(): array` so it
 * can `return []` is ceremony charged to every future class.
 *
 * Merge the two and the marker stops being freezable: every addition to the
 * data contract becomes a breaking change for every implementer, including the
 * ones that had no data. Interface segregation, which is usually taught with
 * an example nobody would write, arriving here as an actual decision.
 *
 * WHY A CODE, WHEN THERE IS ALREADY A CLASS NAME
 * -----------------------------------------------
 * `$e::class` gives you `Workshop\IllegalStatusTransition` for free, so the
 * code looks redundant. Two reasons it is not.
 *
 * 1. THE CLASS NAME IS INTERNAL AND MUST STAY THAT WAY. Lesson 4.4 asserts
 *    that no internal name reaches the wire — a client that switches on
 *    `Workshop\IllegalStatusTransition` has your namespace layout in its
 *    codebase, and renaming a class becomes a breaking API change.
 *
 * 2. THEY CHANGE FOR DIFFERENT REASONS. A class gets renamed during a
 *    refactor; a wire code must not. Splitting one class into two is an
 *    internal decision; a client's `switch` should keep working. Two names,
 *    because there are two contracts.
 *
 * WHY THE CODE IS DECLARED PER CLASS AND NOT DERIVED
 * ---------------------------------------------------
 * `strtolower(preg_replace(...))` on the short class name would generate every
 * code automatically and is genuinely tempting. It also re-couples the wire
 * format to the class name — the exact coupling the previous paragraph exists
 * to prevent — with the coupling now invisible, because there is no line of
 * code that says so.
 *
 * Declared explicitly, a rename shows up in review as a changed string in a
 * diff, and somebody asks whether clients depend on it. Derived, it shows up as
 * nothing at all.
 *
 * WHAT context() MAY CONTAIN
 * ---------------------------
 * Scalars, and lists of scalars. Nothing else, and specifically not domain
 * objects.
 *
 * The reason is Lesson 4.4: this becomes RFC 9457 extension members, which have
 * to be JSON. Handing a boundary a `JobCardId` means the boundary has to know
 * how to serialise one, which puts domain types in the mapper — and the mapper
 * is infrastructure. `$id->value()` here, once, rather than a `jsonSerialize()`
 * on every value object.
 */
interface CarriesContext extends WorkshopFailure
{
    /**
     * A stable, machine-readable identifier for this kind of failure.
     *
     * Kebab-case, no namespace, no class name: `job-card-not-found`,
     * `illegal-status-transition`.
     *
     * This is part of your public API from the moment a client reads it.
     * Treat changing one as you would treat changing a URL.
     */
    public function failureCode(): string;

    /**
     * The facts, as fields.
     *
     * Keys are camelCase. Values are JSON-safe: string, int, float, bool, null,
     * or a list of those.
     *
     * @return array<string, string|int|float|bool|null|list<string|int|float|bool|null>>
     */
    public function context(): array;
}
