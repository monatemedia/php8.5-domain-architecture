<?php
declare(strict_types=1);

namespace Infrastructure;

use Workshop\JobCard;
use Workshop\JobCardId;
use Workshop\JobCardNotFound;
use Workshop\JobCards;
use Workshop\VehicleId;

/**
 * Job cards in SQLite.
 *
 * REFERENCE IMPLEMENTATION. The second implementation of `JobCards`, and the
 * whole point of having a second one is that Lesson 3.4 runs the SAME
 * assertions against both.
 *
 * WHAT IS AND IS NOT IN THIS FILE
 * --------------------------------
 * SQL, PDO, a schema, and the ordering column. No mapping — `JobCardMapper`
 * from Lesson 3.2 does that, and it is handed in rather than constructed here.
 *
 * That split is worth noticing. This class is the boring half: fetch rows, hand
 * them to the mapper, take rows back, write them. Almost every hard decision in
 * Module 3 was made in the mapper, which is why the mapper is the part that can
 * be tested without a database and this part cannot.
 *
 * THE `position` COLUMN, WHICH THE DOMAIN KNOWS NOTHING ABOUT
 * -----------------------------------------------------------
 * `JobCard::lines()` promises insertion order. An array gives that for free; a
 * table does not — a SELECT without ORDER BY returns rows in whatever order the
 * planner finds convenient, and that order can change when an index is added.
 *
 * So this class stores a position and orders by it. The domain has no notion of
 * a line's position, and should not: "third on the list" is not a business
 * concept, it is a consequence of the aggregate's own promise.
 *
 * THIS IS EXACTLY THE DRIFT A FAKE HIDES. An in-memory repository satisfies
 * "insertion order" by accident, so a contract test that did not assert
 * ordering would pass against both while production quietly reordered
 * somebody's invoice. Lesson 3.4's checks assert it deliberately.
 *
 * THE GAP THE CONTRACT TEST CANNOT CLOSE
 * ---------------------------------------
 * `JobCards` is collection-oriented — `add()` and `remove()`, no `save()` —
 * which Lesson 3.1 chose deliberately and warned about.
 *
 * InMemoryJobCards holds a REFERENCE, so a job card changed after `add()` is
 * already up to date. This class wrote rows at `add()` time and has no idea
 * anything changed since, so `get()` returns what was stored.
 *
 * The two implementations therefore DIVERGE, and no assertion written against
 * the interface can hide it. That is not a bug in either class — it is the
 * interface having no moment at which to write, and the honest options are a
 * unit of work or an explicit `save()`. Section 6 of the README argues both.
 *
 * The contract in `checks.php` is written not to depend on it, and says so.
 * Pretending the divergence is not there would be the real failure.
 */
final class SqliteJobCards implements JobCards
{
    public function __construct(
        private readonly \PDO           $connection,
        private readonly JobCardMapper $mapper,
    ) {}

    /**
     * The schema, so a test can build a fresh database in one line.
     *
     * `:memory:` gives every test its own database that disappears when the
     * connection closes — no fixtures to clean up, no ordering dependencies
     * between tests, and no chance of one test seeing another's rows.
     */
    public static function createSchema(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS job_cards (
                id                     TEXT PRIMARY KEY,
                vehicle_id             TEXT NOT NULL,
                account_holder_id      TEXT NOT NULL,
                currency               TEXT NOT NULL,
                status                 TEXT NOT NULL,
                pending_authorisations TEXT NOT NULL
            )
            SQL);

        $connection->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS job_card_lines (
                line_id          TEXT PRIMARY KEY,
                job_card_id      TEXT NOT NULL,
                position         INTEGER NOT NULL,
                kind             TEXT NOT NULL,
                description      TEXT NOT NULL,
                tenths           INTEGER,
                rate_minor       INTEGER,
                part_number      TEXT,
                quantity         INTEGER,
                unit_price_minor INTEGER
            )
            SQL);
    }

    public function add(JobCard $jobCard): void
    {
        if ($this->contains($jobCard->id())) {
            throw new \DomainException(sprintf(
                'Job card %s is already in this collection. To replace it, remove it first.',
                $jobCard->id()
            ));
        }

        // One aggregate, one transaction — Lesson 2.2. A job card whose rows
        // were written and whose lines were not is exactly the half-applied
        // write that lesson was about.
        $this->connection->beginTransaction();

        try {
            $row = $this->mapper->toRow($jobCard);

            $this->connection
                ->prepare(
                    'INSERT INTO job_cards
                         (id, vehicle_id, account_holder_id, currency, status, pending_authorisations)
                     VALUES (:id, :vehicle_id, :account_holder_id, :currency, :status, :pending_authorisations)'
                )
                ->execute($row);

            $insertLine = $this->connection->prepare(
                'INSERT INTO job_card_lines
                     (line_id, job_card_id, position, kind, description,
                      tenths, rate_minor, part_number, quantity, unit_price_minor)
                 VALUES (:line_id, :job_card_id, :position, :kind, :description,
                         :tenths, :rate_minor, :part_number, :quantity, :unit_price_minor)'
            );

            foreach ($this->mapper->toLineRows($jobCard) as $position => $lineRow) {
                // The position is added HERE, not by the mapper. Ordering is a
                // storage concern; the mapper deals in the aggregate's shape.
                $insertLine->execute($lineRow + ['position' => $position]);
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }
    }

    public function get(JobCardId $id): JobCard
    {
        $row = $this->rowFor($id);

        if ($row === null) {
            throw JobCardNotFound::withId($id);
        }

        return $this->mapper->fromRows($row, $this->lineRowsFor($id));
    }

    public function contains(JobCardId $id): bool
    {
        return $this->rowFor($id) !== null;
    }

    public function remove(JobCardId $id): void
    {
        if (!$this->contains($id)) {
            throw JobCardNotFound::withId($id);
        }

        $this->connection->beginTransaction();

        try {
            $this->connection
                ->prepare('DELETE FROM job_card_lines WHERE job_card_id = :id')
                ->execute(['id' => $id->value()]);

            $this->connection
                ->prepare('DELETE FROM job_cards WHERE id = :id')
                ->execute(['id' => $id->value()]);

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }
    }

    /**
     * @return list<JobCard>
     */
    public function forVehicle(VehicleId $vehicleId): array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM job_cards WHERE vehicle_id = :vehicle_id ORDER BY rowid'
        );
        $statement->execute(['vehicle_id' => $vehicleId->value()]);

        return $this->hydrateAll($statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return list<JobCard>
     */
    public function awaitingAuthorisation(): array
    {
        // The status VALUE, not the enum — this file is allowed to know the
        // storage representation, and it is the only file that is.
        $statement = $this->connection->prepare(
            'SELECT * FROM job_cards WHERE status = :status ORDER BY rowid'
        );
        $statement->execute(['status' => 'awaiting_authorisation']);

        return $this->hydrateAll($statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function count(): int
    {
        return (int) $this->connection->query('SELECT COUNT(*) FROM job_cards')->fetchColumn();
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<JobCard>
     */
    private function hydrateAll(array $rows): array
    {
        $jobCards = [];

        foreach ($rows as $row) {
            $id = JobCardId::fromString((string) $row['id']);

            $jobCards[] = $this->mapper->fromRows($row, $this->lineRowsFor($id));
        }

        return $jobCards;
    }

    /**
     * @return ?array<string, mixed>
     */
    private function rowFor(JobCardId $id): ?array
    {
        $statement = $this->connection->prepare('SELECT * FROM job_cards WHERE id = :id');
        $statement->execute(['id' => $id->value()]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * ORDER BY position, and it is not optional.
     *
     * Without it SQLite returns rows in whatever order suits it, which today
     * happens to be insertion order and tomorrow — after an index is added, or
     * a row is updated in place — may not be. `JobCard::lines()` promises
     * insertion order, so this is where that promise is kept.
     *
     * @return list<array<string, mixed>>
     */
    private function lineRowsFor(JobCardId $id): array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM job_card_lines WHERE job_card_id = :id ORDER BY position ASC'
        );
        $statement->execute(['id' => $id->value()]);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }
}
