<?php
declare(strict_types=1);

namespace Infrastructure;

use DI\ContainerBuilder;
use Infrastructure\Http\JobCardsController;
use Infrastructure\Http\ProblemDetailsMiddleware;
use Infrastructure\Http\UnexpectedFailureLog;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Workshop\JobCards;

/**
 * The composition root: the one file that knows what everything really is.
 *
 * REFERENCE IMPLEMENTATION, and the last file in the course.
 *
 * WHAT A COMPOSITION ROOT IS FOR
 * -------------------------------
 * Every other file in this application names interfaces and asks for what it
 * needs. Exactly one file has to know that `JobCards` is a `GuardedJobCards`
 * wrapped around a `SqliteJobCards` holding a `\PDO` and a `JobCardMapper`.
 *
 * This is that file, and it is the ONLY place in the codebase where those four
 * names appear together. That property is the whole point: to swap SQLite for
 * something else you change one entry here, and nothing else in the application
 * has an opinion.
 *
 * READ THE `JobCards::class` DEFINITION AS A SUMMARY OF MODULE 3 AND 4.1
 * ----------------------------------------------------------------------
 *     JobCards           the interface the DOMAIN declared          (3.1)
 *       GuardedJobCards  translates driver failures, passes ours    (4.1)
 *         SqliteJobCards the real implementation                    (3.4)
 *           PDO          the driver
 *           JobCardMapper aggregate <-> rows                        (3.2)
 *
 * The decorator order is a decision, not an accident. `GuardedJobCards` is
 * OUTSIDE, so a `PDOException` from anywhere in the stack below it becomes a
 * `StorageFailure` before it reaches the application — and a `JobCardNotFound`
 * from `SqliteJobCards` passes through as the same object, so the boundary maps
 * it to 404 rather than 500.
 *
 * Put the guard inside and a driver exception escapes untranslated. One line,
 * two very different applications.
 *
 * ON PHP-DI's AUTOWIRING
 * -----------------------
 * The use cases and the controller are NOT defined below. PHP-DI reads their
 * constructor type hints and builds them, which works because every one of
 * those hints is either an interface with a definition here or a class it can
 * construct itself.
 *
 * That is worth noticing rather than taking for granted: autowiring is only
 * possible because the constructors ask for TYPES. A class that reached for a
 * global, a static, or a service locator could not be wired this way, and the
 * previous course spent a module on exactly that.
 */
final class WorkshopApp
{
    /**
     * @param \PDO|null $connection an existing connection, or null for a fresh
     *        in-memory database with the schema already created. The capstone's
     *        checks pass their own so they can inspect the rows afterwards;
     *        production would pass a real one.
     */
    public static function container(?\PDO $connection = null): ContainerInterface
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            \PDO::class => static function () use ($connection): \PDO {
                if ($connection !== null) {
                    return $connection;
                }

                $fresh = new \PDO('sqlite::memory:');
                $fresh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                SqliteJobCards::createSchema($fresh);

                return $fresh;
            },

            JobCardMapper::class => static fn(): JobCardMapper => new JobCardMapper(),

            // THE LINE THE WHOLE OF MODULE 3 WAS FOR.
            JobCards::class => static fn(ContainerInterface $c): JobCards => new GuardedJobCards(
                new SqliteJobCards(
                    $c->get(\PDO::class),
                    $c->get(JobCardMapper::class),
                )
            ),

            ProblemDetailsMapper::class => static fn(): ProblemDetailsMapper => new ProblemDetailsMapper(),

            ResponseFactoryInterface::class
                => static fn(): ResponseFactoryInterface => new \Slim\Psr7\Factory\ResponseFactory(),

            // Autowirable, but named here so a test can reach the same instance
            // the middleware is writing to.
            UnexpectedFailureLog::class => static fn(): UnexpectedFailureLog => new UnexpectedFailureLog(),
        ]);

        return $builder->build();
    }

    /**
     * The application, ready to handle a request.
     *
     * MIDDLEWARE ORDER IS LIFO in Slim: the last thing added runs first. So
     * `ProblemDetailsMiddleware` is added last, which puts it OUTERMOST — where
     * it can catch a failure thrown by routing, by body parsing, or by anything
     * a route calls.
     *
     * Add it first and it sits inside the router, which means an unmatched
     * route never reaches it and the application returns Slim's own HTML error
     * page to a client that asked for JSON. That is a real bug and it is one
     * line away in either direction.
     */
    public static function create(ContainerInterface $container): App
    {
        AppFactory::setContainer($container);

        $app = AppFactory::create();

        $app->addRoutingMiddleware();
        $app->addBodyParsingMiddleware();
        $app->add($container->get(ProblemDetailsMiddleware::class));

        $app->post('/job-cards', [JobCardsController::class, 'open']);
        $app->get('/job-cards/{id}', [JobCardsController::class, 'show']);
        $app->post('/job-cards/{id}/work', [JobCardsController::class, 'startWork']);
        $app->post('/job-cards/{id}/completion', [JobCardsController::class, 'complete']);
        $app->post('/job-cards/{id}/invoice', [JobCardsController::class, 'invoice']);

        return $app;
    }
}
