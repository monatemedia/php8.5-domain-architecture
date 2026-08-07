<?php
declare(strict_types=1);

namespace Infrastructure\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Workshop\CompleteJobCard;
use Workshop\InvoiceJobCard;
use Workshop\JobCardId;
use Workshop\JobCards;
use Workshop\OpenJobCard;
use Workshop\StartWork;

/**
 * HTTP in, HTTP out.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * WHAT A CONTROLLER IS ALLOWED TO DO
 * -----------------------------------
 * Read the request, call one thing, shape the response. Nothing else, and
 * every method here is four lines because there is nothing else to do.
 *
 * There is no `try` in this file. Not one. Every failure — a job card that does
 * not exist, a transition the lifecycle forbids, work awaiting authorisation —
 * travels past here untouched and is turned into a problem document by the
 * middleware, which is the only place that knows what HTTP thinks of anything.
 *
 * A controller with a `catch` in it has taken a decision that belongs one layer
 * out, and it will take that decision slightly differently in the next
 * controller.
 *
 * WHAT IT DOES NOT KNOW
 * ----------------------
 * That there is a database. That `JobCards` is backed by SQLite, wrapped in
 * `GuardedJobCards`, mapped by `JobCardMapper`. It asks the container for use
 * cases and a repository interface, and the container answers.
 *
 * WHY show() USES THE REPOSITORY DIRECTLY
 * ----------------------------------------
 * The three writes go through use cases because they ORCHESTRATE — load,
 * change, store — and that sequence has to live somewhere that is not a
 * controller.
 *
 * A read orchestrates nothing. `ViewJobCard` would be a class whose entire body
 * is `return $this->jobCards->get($id);`, which is a layer added because the
 * diagram has one. Rule 6.
 *
 * The honest alternative is Lesson 3.3's read model, and section 7 of the
 * README explains why the capstone does not use it here: `JobCardSummary` wants
 * a registration number, which lives on `Vehicle`, which this course never
 * persisted. Inventing a vehicles table at the end of the capstone would be
 * scope with no lesson in it.
 */
final readonly class JobCardsController
{
    public function __construct(
        private OpenJobCard $openJobCard,
        private StartWork $startWork,
        private CompleteJobCard $completeJobCard,
        private InvoiceJobCard $invoiceJobCard,
        private JobCards $jobCards,
    ) {}

    /** POST /job-cards */
    public function open(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();

        $id = ($this->openJobCard)(
            (string) ($body['vehicleId'] ?? ''),
            (string) ($body['accountHolderId'] ?? ''),
            (string) ($body['currency'] ?? ''),
        );

        return $this->json(
            $response->withStatus(201)->withHeader('Location', '/job-cards/' . $id),
            ['jobCardId' => $id->value()]
        );
    }

    /**
     * GET /job-cards/{id}
     *
     * @param array<string, string> $args
     */
    public function show(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $jobCard = $this->jobCards->get(JobCardId::fromString($args['id'] ?? ''));

        return $this->json($response, [
            'jobCardId'              => $jobCard->id()->value(),
            'vehicleId'              => $jobCard->vehicleId()->value(),
            'accountHolderId'        => $jobCard->accountHolderId()->value(),
            'status'                 => $jobCard->status()->value,
            'statusLabel'            => $jobCard->status()->label(),
            'lineCount'              => $jobCard->lineCount(),
            'totalMinor'             => $jobCard->total()->amountMinor(),
            'currency'               => $jobCard->currency()->value,
            'pendingAuthorisations'  => $jobCard->pendingAuthorisations(),
        ]);
    }

    /**
     * POST /job-cards/{id}/work
     *
     * @param array<string, string> $args
     */
    public function startWork(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        ($this->startWork)($args['id'] ?? '');

        return $response->withStatus(204);
    }

    /**
     * POST /job-cards/{id}/completion
     *
     * A noun, not a verb. `/completion` is a thing that can be created; a POST
     * to it creates one. `POST /job-cards/{id}/complete` reads as RPC over
     * HTTP, which works fine and tells a client nothing about what it can cache
     * or retry.
     *
     * @param array<string, string> $args
     */
    public function complete(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        ($this->completeJobCard)($args['id'] ?? '');

        return $response->withStatus(204);
    }

    /**
     * POST /job-cards/{id}/invoice
     *
     * @param array<string, string> $args
     */
    public function invoice(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        ($this->invoiceJobCard)($args['id'] ?? '');

        return $response->withStatus(204);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(ResponseInterface $response, array $body): ResponseInterface
    {
        $response->getBody()->write(
            (string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        return $response->withHeader('Content-Type', 'application/json');
    }
}
