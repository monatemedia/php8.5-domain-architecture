<?php
declare(strict_types=1);

namespace Infrastructure\Http;

use Infrastructure\ProblemDetails;
use Infrastructure\ProblemDetailsMapper;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpException;

/**
 * The one place in the application that turns a failure into a response.
 *
 * REFERENCE IMPLEMENTATION. Twenty lines of logic, and Module 4 is the reason
 * it can be twenty lines.
 *
 * THE WHOLE OF MODULE 4, SPENT
 * -----------------------------
 * Every decision this class would otherwise have to make was made earlier, and
 * somewhere better:
 *
 *   WHICH failures are safe to show   4.1's WorkshopFailure marker, checked by
 *                                     the mapper's single `instanceof`
 *   WHAT to call this failure         4.2's failureCode(), declared per class
 *   WHAT fields to include            4.2's context(), scalars only
 *   WHICH status code                 4.4's catalogue, keyed by the code
 *   WHAT a 500 may say                4.4's opaque(), which says nothing
 *
 * So there is no `match` on exception classes here, no table of status codes,
 * and no `if ($e instanceof ...)` chain. There is a try, two catches, and a
 * response builder.
 *
 * That is the test of whether the previous four modules were worth it. **If
 * this class needed a hundred lines of mapping, the exceptions were not
 * carrying their weight.**
 *
 * WHY IT CATCHES \Throwable
 * --------------------------
 * See `UnexpectedFailureLog`. The short version: the outermost boundary of a
 * web application has to catch everything — the alternative is a stack trace
 * served to a customer — and Lesson 4.1's "let it be loud" is satisfied by
 * reporting to an operator rather than by letting it reach a user.
 *
 * The important line is the one deciding WHICH failures get reported: anything
 * the mapper turned into a 5xx. A 4xx is the system working, and reporting
 * those is how an error tracker becomes noise nobody reads.
 */
final readonly class ProblemDetailsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ProblemDetailsMapper $mapper,
        private ResponseFactoryInterface $responses,
        private UnexpectedFailureLog $unexpected,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $path = $request->getUri()->getPath();

        try {
            return $handler->handle($request);
        } catch (HttpException $routing) {
            // Slim's own — an unmatched route, a method that is not allowed.
            // Not a domain failure and not a bug: the request never reached
            // anything of ours. Its status is already correct, so it is
            // rendered as a problem document for consistency and nothing
            // further is said about it.
            return $this->respond(
                ProblemDetails::of(
                    'about:blank',
                    $routing->getTitle(),
                    $routing->getCode(),
                    null,
                    $path,
                )
            );
        } catch (\Throwable $failure) {
            $problem = $this->mapper->map($failure, $path);

            if ($problem->status >= 500) {
                // The user gets the opaque body below. The operator gets this.
                $this->unexpected->record($failure);
            }

            return $this->respond($problem);
        }
    }

    private function respond(ProblemDetails $problem): ResponseInterface
    {
        $response = $this->responses->createResponse($problem->status);

        $response->getBody()->write($problem->toJson());

        // application/problem+json, not application/json. The part most
        // implementations forget, and it is how a client knows to look for
        // `type` and `title` without guessing.
        return $response->withHeader('Content-Type', ProblemDetails::MEDIA_TYPE);
    }
}
