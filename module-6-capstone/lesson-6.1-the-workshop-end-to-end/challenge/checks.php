<?php
declare(strict_types=1);

/**
 * checks.php — Capstone acceptance checks
 * ========================================
 *
 * THE SPECIFICATION for the assembled application.
 *
 *     Workshop\OpenJobCard, StartWork, CompleteJobCard, InvoiceJobCard
 *     Infrastructure\ImportScan
 *     Infrastructure\WorkshopApp
 *     Infrastructure\Http\JobCardsController
 *     Infrastructure\Http\ProblemDetailsMiddleware
 *     Infrastructure\Http\UnexpectedFailureLog
 *
 * DO NOT EDIT.
 *
 * Section 1 is the course's thesis, executable: the domain layer names nothing
 * infrastructural, checked by reading the files.
 *
 * Section 4 drives real PSR-7 requests through a real Slim application against
 * a real SQLite database. Nothing is mocked and nothing is simulated.
 */

use Infrastructure\GuardedJobCards;
use Infrastructure\Http\JobCardsController;
use Infrastructure\Http\ProblemDetailsMiddleware;
use Infrastructure\Http\UnexpectedFailureLog;
use Infrastructure\ImportScan;
use Infrastructure\ProblemDetails;
use Infrastructure\SqliteJobCards;
use Infrastructure\WorkshopApp;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Workshop\CompleteJobCard;
use Workshop\InvoiceJobCard;
use Workshop\JobCards;
use Workshop\OpenJobCard;
use Workshop\StartWork;
use Workshop\VehicleId;
use Workshop\WorkshopFailure;

acceptance('Capstone — the workshop, end to end');


// ═══════════════════════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════════════════════

/** Every directory holding domain classes, in this repository's layout. */
function domainDirectories(): array
{
    $root = dirname(__DIR__, 3);

    // A student has one: domain/workshop/. The reference solutions are spread
    // across the lessons that built them, so the scan takes both and skips the
    // `infrastructure/` and `given/` subdirectories inside each.
    $directories = [$root . '/domain/workshop'];

    foreach (glob($root . '/module-*/lesson-*/challenge/solution', GLOB_ONLYDIR) ?: [] as $dir) {
        $directories[] = $dir;
    }

    return $directories;
}

function infrastructureDirectories(): array
{
    $root = dirname(__DIR__, 3);

    return array_merge(
        [$root . '/infrastructure'],
        glob($root . '/module-*/lesson-*/challenge/solution/infrastructure', GLOB_ONLYDIR) ?: []
    );
}

/** One application, one database, for a group of checks. */
function anApp(?\PDO $connection = null): App
{
    return WorkshopApp::create(WorkshopApp::container($connection));
}

function aDatabase(): \PDO
{
    $connection = new \PDO('sqlite::memory:');
    $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    SqliteJobCards::createSchema($connection);

    return $connection;
}

/** @param array<string, mixed>|null $payload */
function request(string $method, string $path, ?array $payload = null): ServerRequestInterface
{
    $request = (new ServerRequestFactory())
        ->createServerRequest($method, $path)
        ->withHeader('Content-Type', 'application/json');

    if ($payload !== null) {
        $request->getBody()->write((string) json_encode($payload, JSON_THROW_ON_ERROR));
        $request->getBody()->rewind();
    }

    return $request;
}

/** @return array<string, mixed> */
function decoded(ResponseInterface $response): array
{
    $body = (string) $response->getBody();

    return json_decode($body, true) ?? [];
}

/** @param array<string, mixed>|null $payload */
function send(App $app, string $method, string $path, ?array $payload = null): ResponseInterface
{
    return $app->handle(request($method, $path, $payload));
}

/** Open a job card through the API and return its id. */
function openAJobCard(App $app): string
{
    $response = send($app, 'POST', '/job-cards', [
        'vehicleId'       => 'VEH-4471',
        'accountHolderId' => 'AH-0007',
        'currency'        => 'EUR',
    ]);

    return (string) (decoded($response)['jobCardId'] ?? '');
}


// ═══════════════════════════════════════════════════════════════════════════
// 1 — THE THESIS: the domain names nothing infrastructural
// ═══════════════════════════════════════════════════════════════════════════

$domain = ImportScan::of(...domainDirectories());

check('The import scan found the domain layer',
    fn() => $domain->fileCount() > 30);

check('THE DOMAIN LAYER REFERENCES NOTHING INFRASTRUCTURAL',
    function () use ($domain): bool {
        if (!$domain->isClean()) {
            throw new RuntimeException($domain->report());
        }

        return true;
    });

check('...and that is checked against a real list, not an empty one',
    fn() => count(ImportScan::INFRASTRUCTURAL) >= 6
        && in_array('Infrastructure\\', ImportScan::INFRASTRUCTURAL, true)
        && in_array('PDO', ImportScan::INFRASTRUCTURAL, true)
        && in_array('Slim\\', ImportScan::INFRASTRUCTURAL, true));

check('The scan is not vacuous — it does find names',
    fn() => $domain->references() !== []);

/*
 * THE RESULT IS STRONGER THAN THE ONE THAT WAS BEING LOOKED FOR, and it was
 * found by these checks failing.
 *
 * The first version asserted that the scan would turn up `Workshop\JobCards`
 * among the references. It does not, and the reason is worth the whole
 * capstone: every domain class is in the SAME NAMESPACE, so they refer to each
 * other UNQUALIFIED. There is not one `use` statement anywhere in the domain
 * layer.
 *
 * So the question "does the domain import anything infrastructural" turns out
 * to have a sharper answer than no:
 *
 *     the domain layer imports NOTHING AT ALL
 *
 * Its entire external vocabulary is SPL — the exception classes it extends and
 * `\Throwable`. Delete the framework, the driver and the web server and there
 * is nothing left to remove.
 */

check('THE DOMAIN LAYER HAS NO IMPORTS AT ALL — every name is SPL or its own',
    function () use ($domain): bool {
        foreach ($domain->references() as $reference) {
            if (str_contains($reference, '\\') && !str_starts_with($reference, 'Workshop\\')) {
                throw new RuntimeException('unexpected reference: ' . $reference);
            }
        }

        return true;
    });

check('...and the whole of that vocabulary is SPL exception types',
    fn() => in_array('DomainException', $domain->references(), true)
        && in_array('InvalidArgumentException', $domain->references(), true)
        && in_array('Throwable', $domain->references(), true));

check('...a list short enough to read in one go',
    fn() => count($domain->references()) < 25);

/**
 * Write one PHP file into a directory of its own and scan it.
 *
 * The scanner deliberately skips any path segment called `infrastructure`, so
 * it cannot be self-tested by pointing it at this course's own outer layer.
 * A throwaway directory is the honest way round that, and it also lets the two
 * probes below differ by exactly one line.
 */
function scanOf(string $source): ImportScan
{
    $directory = sys_get_temp_dir() . '/import-scan-' . getmypid() . '-' . substr(md5($source), 0, 8);

    @mkdir($directory, 0777, true);
    file_put_contents($directory . '/Probe.php', $source);

    $scan = ImportScan::of($directory);

    unlink($directory . '/Probe.php');
    @rmdir($directory);

    return $scan;
}

check('THE SCANNER CAN FAIL — a domain file that really imports Slim is caught',
    function (): bool {
        // If this comes back clean, the detector is broken and the check above
        // it proves nothing. A test that cannot fail is not a test.
        $scan = scanOf(<<<'PHP'
            <?php
            namespace Workshop;

            use Slim\App;

            final class Probe
            {
                public function run(App $app, \PDO $connection): void {}
            }
            PHP);

        return !$scan->isClean();
    });

check('...and it names the file and the imports, not just "impure"',
    function (): bool {
        $scan = scanOf(<<<'PHP'
            <?php
            namespace Workshop;

            use Infrastructure\SqliteJobCards;

            final class Probe
            {
                public function run(SqliteJobCards $r, \PDO $c): void {}
            }
            PHP);

        $report = $scan->report();

        return str_contains($report, 'Probe.php')
            && str_contains($report, 'Infrastructure\SqliteJobCards')
            && str_contains($report, 'PDO');
    });

check('It reads TOKENS: a doc block naming PDO and Slim is not a violation',
    function (): bool {
        // The same file as the first probe with the `use` removed and the names
        // moved into prose. A grep cannot tell these two apart. This is bug 3
        // and bug 7 from RUN-THIS-FIRST.md, refusing to happen a third time.
        $scan = scanOf(<<<'PHP'
            <?php
            namespace Workshop;

            /**
             * This class must never import \PDO or Slim\App, and it does not
             * know that Infrastructure\SqliteJobCards exists.
             */
            final class Probe
            {
                public function value(): string { return 'PDO is only a word here'; }
            }
            PHP);

        return $scan->isClean() && $scan->fileCount() === 1;
    });

check('...and an alias does not hide an import',
    function (): bool {
        $scan = scanOf(<<<'PHP'
            <?php
            namespace Workshop;

            use Slim\App as Harmless;

            final class Probe
            {
                public function run(Harmless $app): void {}
            }
            PHP);

        return !$scan->isClean();
    });

check('...and a closure\'s `use ($x)` is not mistaken for an import',
    function (): bool {
        $scan = scanOf(<<<'PHP'
            <?php
            namespace Workshop;

            final class Probe
            {
                public function run(string $slim): callable
                {
                    return function () use ($slim): string { return $slim; };
                }
            }
            PHP);

        return $scan->isClean();
    });


// ═══════════════════════════════════════════════════════════════════════════
// 2 — The use cases orchestrate and decide nothing
// ═══════════════════════════════════════════════════════════════════════════

check('Four use cases exist, in Workshop\\',
    fn() => class_exists(OpenJobCard::class)
        && class_exists(StartWork::class)
        && class_exists(CompleteJobCard::class)
        && class_exists(InvoiceJobCard::class));

check('Each takes the JobCards INTERFACE, not an implementation',
    function (): bool {
        foreach ([OpenJobCard::class, StartWork::class, CompleteJobCard::class, InvoiceJobCard::class] as $useCase) {
            $parameter = (new ReflectionClass($useCase))->getConstructor()?->getParameters()[0] ?? null;

            if ((string) $parameter?->getType() !== JobCards::class) {
                return false;
            }
        }

        return true;
    });

check('No use case contains a business rule — not one `if`',
    function (): bool {
        foreach ([OpenJobCard::class, StartWork::class, CompleteJobCard::class, InvoiceJobCard::class] as $useCase) {
            if (str_contains(class_source_without_comments($useCase), 'if (')) {
                return false;
            }
        }

        return true;
    });

check('No use case catches anything',
    function (): bool {
        foreach ([OpenJobCard::class, StartWork::class, CompleteJobCard::class, InvoiceJobCard::class] as $useCase) {
            if (str_contains(class_source_without_comments($useCase), 'catch')) {
                return false;
            }
        }

        return true;
    });


// ═══════════════════════════════════════════════════════════════════════════
// 3 — The composition root wires the whole of Modules 3 and 4.1
// ═══════════════════════════════════════════════════════════════════════════

check('The container resolves JobCards to a GuardedJobCards',
    fn() => WorkshopApp::container(aDatabase())->get(JobCards::class) instanceof GuardedJobCards);

check('The guard is OUTSIDE the SQLite implementation, not inside',
    function (): bool {
        // The decorator order decides whether a PDOException reaches the
        // application. Section 4 proves the consequence; this proves the shape.
        $source = class_source_without_comments(WorkshopApp::class);

        return preg_match('/new\s+GuardedJobCards\s*\(\s*new\s+SqliteJobCards/', $source) === 1;
    });

check('The controller resolves, with its use cases autowired',
    fn() => WorkshopApp::container(aDatabase())->get(JobCardsController::class)
        instanceof JobCardsController);

check('The middleware resolves',
    fn() => WorkshopApp::container(aDatabase())->get(ProblemDetailsMiddleware::class)
        instanceof ProblemDetailsMiddleware);

check('The application is a Slim app',
    fn() => anApp(aDatabase()) instanceof App);

check('The controller has no try/catch anywhere in it',
    fn() => !str_contains(class_source_without_comments(JobCardsController::class), 'catch'));


// ═══════════════════════════════════════════════════════════════════════════
// 4 — END TO END: real requests, real Slim, real SQLite
// ═══════════════════════════════════════════════════════════════════════════

check('POST /job-cards opens one and answers 201 with a Location',
    function (): bool {
        $response = send(anApp(aDatabase()), 'POST', '/job-cards', [
            'vehicleId'       => 'VEH-4471',
            'accountHolderId' => 'AH-0007',
            'currency'        => 'EUR',
        ]);

        $body = decoded($response);

        return $response->getStatusCode() === 201
            && ($body['jobCardId'] ?? '') !== ''
            && str_contains($response->getHeaderLine('Location'), (string) $body['jobCardId']);
    });

check('GET /job-cards/{id} returns it, from the database',
    function (): bool {
        $app = anApp(aDatabase());
        $id  = openAJobCard($app);

        $response = send($app, 'GET', '/job-cards/' . $id);
        $body     = decoded($response);

        return $response->getStatusCode() === 200
            && $body['jobCardId'] === $id
            && $body['status'] === 'booked'
            && $body['vehicleId'] === 'VEH-4471'
            && $body['currency'] === 'EUR';
    });

check('THE WHOLE LIFECYCLE, over HTTP: open -> work -> complete -> invoice',
    function (): bool {
        $app = anApp(aDatabase());
        $id  = openAJobCard($app);

        $statuses = [];

        foreach (['work', 'completion', 'invoice'] as $step) {
            $response = send($app, 'POST', "/job-cards/{$id}/{$step}");

            if ($response->getStatusCode() !== 204) {
                throw new RuntimeException(sprintf(
                    'step "%s" answered %d: %s',
                    $step,
                    $response->getStatusCode(),
                    (string) $response->getBody()
                ));
            }

            $statuses[] = decoded(send($app, 'GET', '/job-cards/' . $id))['status'];
        }

        return $statuses === ['in_progress', 'completed', 'invoiced'];
    });

check('...and the change survived a round trip through SQLite',
    function (): bool {
        $connection = aDatabase();
        $app        = anApp($connection);
        $id         = openAJobCard($app);

        send($app, 'POST', "/job-cards/{$id}/work");

        // Read the row directly. Nothing about the application is involved.
        $statement = $connection->prepare('SELECT status FROM job_cards WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn() === 'in_progress';
    });


// ═══════════════════════════════════════════════════════════════════════════
// 5 — Failures, over HTTP, as RFC 9457
// ═══════════════════════════════════════════════════════════════════════════

check('An illegal transition is a 409 problem+json, not a 500',
    function (): bool {
        $app = anApp(aDatabase());
        $id  = openAJobCard($app);

        // Booked cannot become Invoiced.
        $response = send($app, 'POST', "/job-cards/{$id}/invoice");

        return $response->getStatusCode() === 409
            && $response->getHeaderLine('Content-Type') === ProblemDetails::MEDIA_TYPE;
    });

check('...carrying Module 4\'s code, context and instance',
    function (): bool {
        $app  = anApp(aDatabase());
        $id   = openAJobCard($app);
        $body = decoded(send($app, 'POST', "/job-cards/{$id}/invoice"));

        return str_ends_with((string) $body['type'], 'illegal-status-transition')
            && $body['from'] === 'booked'
            && $body['to'] === 'invoiced'
            && in_array('in_progress', $body['allowed'], true)
            && $body['instance'] === "/job-cards/{$id}/invoice"
            && $body['jobCardId'] === $id;
    });

check('An unknown job card is a 404, not a 500',
    function (): bool {
        $response = send(anApp(aDatabase()), 'GET', '/job-cards/JC-DOES-NOT-EXIST');
        $body     = decoded($response);

        return $response->getStatusCode() === 404
            && str_ends_with((string) $body['type'], 'job-card-not-found');
    });

check('An unmatched route is a 404 problem document, not Slim\'s HTML page',
    function (): bool {
        $response = send(anApp(aDatabase()), 'GET', '/nothing-here');

        return $response->getStatusCode() === 404
            && $response->getHeaderLine('Content-Type') === ProblemDetails::MEDIA_TYPE;
    });

check('A DEAD DATABASE is a 500 that says nothing',
    function (): bool {
        // A connection with no schema. Every query fails, SqliteJobCards throws
        // a PDOException, GuardedJobCards turns it into a StorageFailure, and
        // the mapper refuses to say anything about it.
        $empty = new \PDO('sqlite::memory:');
        $empty->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $response = send(anApp($empty), 'GET', '/job-cards/JC-1042');
        $body     = decoded($response);

        return $response->getStatusCode() === 500
            && $body['type'] === 'about:blank'
            && $body['title'] === 'Internal Server Error'
            && !array_key_exists('detail', $body);
    });

check('...and the 500 body leaks no SQL, no path, no class name',
    function (): bool {
        $empty = new \PDO('sqlite::memory:');
        $empty->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $json = (string) send(anApp($empty), 'GET', '/job-cards/JC-1042')->getBody();

        foreach (['SQLSTATE', 'job_cards', 'SELECT', '.php', 'Infrastructure', 'Workshop\\', '#0 '] as $leak) {
            if (str_contains($json, $leak)) {
                throw new RuntimeException('leaked: ' . $leak);
            }
        }

        return true;
    });

check('...while the OPERATOR is told, with the exception intact',
    function (): bool {
        $empty = new \PDO('sqlite::memory:');
        $empty->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $container = WorkshopApp::container($empty);
        $app       = WorkshopApp::create($container);
        $log       = $container->get(UnexpectedFailureLog::class);

        send($app, 'GET', '/job-cards/JC-1042');

        return $log->count() === 1
            && str_contains($log->all()[0]->getMessage() . ($log->all()[0]->getPrevious()?->getMessage() ?? ''), 'no such table');
    });

check('A 4xx is NOT reported to the operator — a business refusal is not an incident',
    function (): bool {
        $container = WorkshopApp::container(aDatabase());
        $app       = WorkshopApp::create($container);
        $log       = $container->get(UnexpectedFailureLog::class);

        $id = openAJobCard($app);
        send($app, 'POST', "/job-cards/{$id}/invoice");   // 409
        send($app, 'GET', '/job-cards/JC-NOPE');          // 404

        return $log->count() === 0;
    });


// ═══════════════════════════════════════════════════════════════════════════
// 6 — THE FINDING: what assembling it end to end exposed
// ═══════════════════════════════════════════════════════════════════════════
//
// Module 4 gave names to every throw site in JobCard and in the repositories.
// It explicitly left the value objects from Modules 1 and 2 to the reader.
//
// Nothing noticed until now, because nothing had asked a value object to
// refuse something on the far side of an HTTP boundary. These checks assert
// the gap rather than working around it. Section 8 of the README says what the
// fix costs.

check('FINDING: Module 1\'s identities are not in Module 4\'s tree',
    function (): bool {
        try {
            VehicleId::fromString('');
        } catch (\Throwable $thrown) {
            return $thrown instanceof \InvalidArgumentException
                && !($thrown instanceof WorkshopFailure);
        }

        return false;
    });

check('...so a malformed identity reaches the boundary as a 500',
    function (): bool {
        $container = WorkshopApp::container(aDatabase());
        $app       = WorkshopApp::create($container);

        $response = send($app, 'POST', '/job-cards', [
            'vehicleId'       => '',
            'accountHolderId' => 'AH-0007',
            'currency'        => 'EUR',
        ]);

        // 422 is the right answer and the application cannot give it, because
        // the exception carries no failure code for the catalogue to key on.
        return $response->getStatusCode() === 500;
    });

check('...and it IS reported, so the gap is visible rather than silent',
    function (): bool {
        $container = WorkshopApp::container(aDatabase());
        $app       = WorkshopApp::create($container);
        $log       = $container->get(UnexpectedFailureLog::class);

        send($app, 'POST', '/job-cards', [
            'vehicleId'       => '',
            'accountHolderId' => 'AH-0007',
            'currency'        => 'EUR',
        ]);

        return $log->count() === 1;
    });

check('The fix is mechanical: the tree already has the branch it belongs on',
    fn() => class_exists('Workshop\InvalidWorkshopInput')
        && (new ReflectionClass('Workshop\InvalidWorkshopInput'))->isAbstract());


acceptance_report();
