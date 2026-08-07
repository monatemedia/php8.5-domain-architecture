<?php
declare(strict_types=1);

namespace Infrastructure;

use Workshop\AccountHolderId;
use Workshop\Currency;
use Workshop\JobCard;
use Workshop\JobCardId;
use Workshop\JobCardStatus;
use Workshop\LabourLine;
use Workshop\LabourUnits;
use Workshop\Line;
use Workshop\LineId;
use Workshop\Money;
use Workshop\PartLine;
use Workshop\PartNumber;
use Workshop\VehicleId;

/**
 * Flat rows in, aggregate out. And back again.
 *
 * REFERENCE IMPLEMENTATION.
 *
 * WHY THIS FILE IS THE HARD PART OF MODULE 3
 * -------------------------------------------
 * Everything Modules 1 and 2 built to keep the model honest is, from here, an
 * obstacle: private constructors, readonly lines, identities that must survive
 * a round trip, a status that must not be re-transitioned to, and an `open()`
 * that announces a business event.
 *
 * That is not a design failure. It is the bill, and this is where it is paid.
 *
 * WHY IT TAKES ARRAYS RATHER THAN A PDOStatement
 * -----------------------------------------------
 * There is no PDO in this file, no SQL, and no connection. A mapper that took a
 * statement would need a database to test, and mapping is precisely the part
 * worth testing exhaustively — every line type, every status, every empty case.
 *
 * The repository fetches rows; the mapper turns rows into aggregates. Lesson
 * 3.4 supplies the SQLite half, and it is the boring half.
 *
 * THE ROW SHAPES
 * --------------
 * One row for the job card, and a row per line:
 *
 *   job card   id, vehicle_id, account_holder_id, currency, status,
 *              pending_authorisations (JSON)
 *
 *   line       line_id, kind, description,
 *              tenths, rate_minor                          (labour only)
 *              part_number, quantity, unit_price_minor     (parts only)
 *
 * THE `kind` COLUMN IS A MAPPING CONCERN AND NOTHING ELSE
 * -------------------------------------------------------
 * The domain has no notion of "kind". `Line` has three methods and none of them
 * says whether a line is labour or parts, because JobCard does not care —
 * Lesson 2.1 rejected `isLabour()` on the interface for exactly that reason.
 *
 * Storage does care, because a row has to be turned back into the right class.
 * So the discriminator lives HERE, along with the `instanceof` that produces
 * it. A type check in a mapper is serialisation; the same check in a domain
 * method is logic that belongs on the object.
 *
 * ON THE NULLABLE COLUMNS
 * -----------------------
 * A labour row has no part number; a part row has no tenths. Half of every line
 * row is null, which is Lesson 0.2's shared-model tell — a class whose
 * populated fields depend on where it came from.
 *
 * It is acceptable here for one reason: THIS IS NOT A MODEL. It is a wire
 * format, it is never handed to anything, and the aggregate it produces has no
 * nullable fields at all. The tell warns about a MODEL shaped that way, and a
 * storage layout is allowed to be ugly as long as the ugliness stops here.
 *
 * (The alternative is a table per line type, which is cleaner and needs a join
 * or two queries. At two line types, this is the cheaper trade. At six, it
 * would not be.)
 */
final readonly class JobCardMapper
{
    // ── Aggregate -> rows ───────────────────────────────────────────────────

    /**
     * @return array<string, string> the job card's own row
     */
    public function toRow(JobCard $jobCard): array
    {
        return [
            'id'                     => $jobCard->id()->value(),
            'vehicle_id'             => $jobCard->vehicleId()->value(),
            'account_holder_id'      => $jobCard->accountHolderId()->value(),
            'currency'               => $jobCard->currency()->value,
            'status'                 => $jobCard->status()->value,

            // JSON in a column, and it is a compromise worth naming. A separate
            // table would be more correct and would need a second query, a
            // second insert and an ordering column — for a list that is almost
            // always empty and is never queried across job cards.
            //
            // The rule of thumb: JSON is acceptable for something you only ever
            // read back as a whole, with the aggregate that owns it. The day
            // somebody wants "all outstanding authorisations across the
            // workshop", this becomes a table.
            'pending_authorisations' => json_encode($jobCard->pendingAuthorisations(), JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @return list<array<string, string|int|null>> one row per line
     */
    public function toLineRows(JobCard $jobCard): array
    {
        $rows = [];

        foreach ($jobCard->lines() as $line) {
            $rows[] = $this->toLineRow($jobCard->id()->value(), $line);
        }

        return $rows;
    }

    /**
     * @return array<string, string|int|null>
     */
    private function toLineRow(string $jobCardId, Line $line): array
    {
        $base = [
            'job_card_id' => $jobCardId,
            'line_id'     => $line->id()->value(),
            'description' => $line->description(),
        ];

        // The `instanceof` the domain interface refused to expose. It belongs
        // here: turning an object back into a row is the one job that
        // legitimately needs to know which class it is.
        if ($line instanceof LabourLine) {
            return $base + [
                'kind'             => 'labour',
                'tenths'           => $line->units()->tenths(),
                'rate_minor'       => $line->ratePerTenth()->amountMinor(),
                'part_number'      => null,
                'quantity'         => null,
                'unit_price_minor' => null,
            ];
        }

        if ($line instanceof PartLine) {
            return $base + [
                'kind'             => 'part',
                'tenths'           => null,
                'rate_minor'       => null,
                'part_number'      => $line->partNumber()->value(),
                'quantity'         => $line->quantity(),
                'unit_price_minor' => $line->unitPrice()->amountMinor(),
            ];
        }

        // A third Line implementation would land here, loudly, rather than
        // being silently dropped from somebody's invoice.
        throw new \RuntimeException(sprintf(
            'Cannot map line %s: %s is not a line type this mapper knows how to store.',
            $line->id(),
            $line::class
        ));
    }

    // ── Rows -> aggregate ───────────────────────────────────────────────────

    /**
     * @param array<string, mixed>       $row      the job card's own row
     * @param list<array<string, mixed>> $lineRows its lines, in the order they should appear
     */
    public function fromRows(array $row, array $lineRows): JobCard
    {
        $currency = Currency::from((string) $row['currency']);

        $lines = [];
        foreach ($lineRows as $lineRow) {
            $lines[] = $this->lineFromRow($lineRow, $currency);
        }

        /** @var list<string> $pending */
        $pending = json_decode((string) $row['pending_authorisations'], true, 512, JSON_THROW_ON_ERROR);

        return JobCard::reconstitute(
            JobCardId::fromString((string) $row['id']),
            VehicleId::fromString((string) $row['vehicle_id']),
            AccountHolderId::fromString((string) $row['account_holder_id']),
            $currency,
            JobCardStatus::from((string) $row['status']),
            $lines,
            $pending,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function lineFromRow(array $row, Currency $currency): Line
    {
        $lineId      = LineId::fromString((string) $row['line_id']);
        $description = (string) $row['description'];

        return match ($row['kind']) {
            // Note which factories are used INSIDE reconstitute(): the ordinary
            // ones. LabourUnits::ofTenths() and Money::of() run their full
            // guards, and they should — those are cheap, total, and true of
            // every value that has ever existed. Reconstitution relaxes the
            // rules of the AGGREGATE, not of the values it holds.
            'labour' => LabourLine::reconstitute(
                $lineId,
                $description,
                LabourUnits::ofTenths((int) $row['tenths']),
                Money::of((int) $row['rate_minor'], $currency),
            ),

            'part' => PartLine::reconstitute(
                $lineId,
                PartNumber::fromString((string) $row['part_number']),
                $description,
                (int) $row['quantity'],
                Money::of((int) $row['unit_price_minor'], $currency),
            ),

            // An unknown discriminator means the schema has moved and this
            // class has not. Failing here is far better than returning a job
            // card that is quietly missing a line — and a missing line means a
            // wrong total on an invoice.
            default => throw new \RuntimeException(sprintf(
                'Unknown line kind "%s" on line %s.',
                (string) $row['kind'],
                (string) $row['line_id']
            )),
        };
    }
}
