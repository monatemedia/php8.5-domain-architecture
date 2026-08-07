<?php
declare(strict_types=1);

namespace Infrastructure;

/**
 * The storage underneath a repository failed.
 *
 * REFERENCE IMPLEMENTATION. Category (b): nobody's rule was broken, the request
 * was fine, and the machinery fell over.
 *
 * FOUR DECISIONS, EACH OF WHICH COULD HAVE GONE THE OTHER WAY
 * ------------------------------------------------------------
 *
 * 1. IT EXTENDS \RuntimeException, NOT \DomainException.
 *    A dead connection is not a business rule. The SPL split is imperfect —
 *    see the README — but this is one of the cases where it says the right
 *    thing: only detectable at run time, and not a fix in your code.
 *
 * 2. IT DOES NOT IMPLEMENT WorkshopFailure, AND THAT IS THE WHOLE POINT.
 *    Adding the marker "so the boundary can catch everything in one place"
 *    would defeat this entire lesson. The boundary catches these separately
 *    BECAUSE they are a 500 and the others are not.
 *
 *    The check in `checks.php` that asserts this is one of maybe three checks
 *    in the course whose failure means you have misunderstood rather than
 *    mistyped.
 *
 * 3. IT LIVES IN infrastructure/.
 *    Apply the question from the course README: if you deleted the framework,
 *    the database driver and the web server, would this still make sense to
 *    someone who runs the business? A "storage failure" would not — Marcus has
 *    no storage. He has a filing cabinet, and it does not refuse connections.
 *
 * 4. IT KEEPS THE ORIGINAL AS `previous`.
 *    Translating must not destroy evidence. The user must never see
 *    `SQLSTATE[HY000] [2002]`, and the operator debugging this at 2am needs
 *    exactly that. Both are satisfied by putting it on the chain instead of in
 *    the message: `getPrevious()` reaches it, and a boundary rendering
 *    `getMessage()` does not.
 *
 * WHY THE MESSAGE IS SO PLAIN
 * ----------------------------
 * "Storage failed during get." names the operation and stops. It is deliberate:
 * anything more specific would either duplicate the previous exception or
 * invent detail. The interesting information is one hop away, on the chain,
 * where a logger will find it and a JSON serialiser will not.
 */
final class StorageFailure extends \RuntimeException
{
    /**
     * @param string $operation the repository method that was running — `get`,
     *                          `add`, `count`. Named after the DOMAIN operation
     *                          rather than the SQL, because "storage failed
     *                          during SELECT job_card WHERE id = ?" puts the
     *                          schema into a log line that ops will paste into
     *                          a ticket.
     */
    public static function during(string $operation, \Throwable $cause): self
    {
        return new self(
            sprintf('Storage failed during %s.', $operation),
            0,
            $cause
        );
    }
}
