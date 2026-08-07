<?php
declare(strict_types=1);

namespace Workshop;

/**
 * Every failure this domain produces on purpose.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * WHAT IT IS FOR
 * --------------
 * One type that a boundary can name in a catch clause, meaning:
 *
 *     the workshop refused something, on purpose, for a reason a person
 *     who runs the business would recognise
 *
 * ...and meaning that and nothing else. Not a dead database. Not a bug.
 *
 * WHY IT HAS TO BE AN INTERFACE
 * ------------------------------
 * Because a base class cannot work here, and it is worth being precise about
 * why rather than treating this as a style choice.
 *
 * The domain throws failures from both halves of SPL's hierarchy:
 *
 *     Money::of(-1)          \InvalidArgumentException   an argument is wrong
 *     $jobCard->invoice()    \DomainException            a rule was broken
 *
 * Those two are SIBLINGS. Their only common ancestor is \LogicException, and
 * catching that also catches every \BadFunctionCallException thrown by every
 * package in `vendor/`. There is no class you can put in a catch clause that
 * means "mine".
 *
 * PHP gives each class exactly one inheritance slot, and SPL has already spent
 * it. Interfaces are the one thing a class can have several of.
 *
 * `extends \Throwable` DOES TWO THINGS
 * -------------------------------------
 * It lets a handler call getMessage(), getPrevious() and getCode() on whatever
 * it caught, without an instanceof dance.
 *
 * And it stops this interface being implemented by something that is not
 * throwable at all. PHP enforces that at compile time — a plain class
 * implementing a \Throwable-derived interface is a fatal error. The marker
 * cannot drift into meaning something else.
 *
 * ON EMPTY INTERFACES
 * -------------------
 * The usual objection is that an interface with no methods is an annotation
 * wearing a type, and the usual objection is usually right.
 *
 * It does not apply here, because the purpose of this type IS to be named in a
 * `catch` clause, and a catch clause takes a type and nothing else. The
 * alternative is:
 *
 *     catch (\Throwable $e) {
 *         if ($e instanceof HasIsDomainFailure && $e->isDomainFailure()) { ... }
 *     }
 *
 * ...which is worse in every direction, starting with the fact that it catches
 * \Error and therefore swallows bugs.
 *
 * Lesson 4.2 puts abstract classes with real behaviour underneath this marker.
 * The marker stays, because it is the only thing that spans both SPL branches.
 *
 * WHAT IT DELIBERATELY DOES NOT COVER
 * ------------------------------------
 *   - Infrastructure\StorageFailure. The database falling over is not a
 *     business rule, and a boundary that renders it as a 409 with a helpful
 *     message is lying to the user.
 *   - \Error and its subclasses. A TypeError is a bug in this codebase. It
 *     should be loud, and it should reach whoever wrote the file.
 */
interface WorkshopFailure extends \Throwable
{
}
