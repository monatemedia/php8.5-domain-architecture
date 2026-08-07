<?php
declare(strict_types=1);

/**
 * checks.php — Lesson 3.3 acceptance checks
 * ==========================================
 *
 * THE SPECIFICATION for Infrastructure\JobCardSummary and
 * Infrastructure\JobCardSummaryQuery.
 *
 * DO NOT EDIT.
 *
 * The placement checks are the point. A read model that drifts into the domain,
 * or a domain type that drifts into a read model, is the failure this lesson
 * exists to prevent.
 */

use Infrastructure\JobCardSummary;
use Infrastructure\JobCardSummaryQuery;

acceptance('Lesson 3.3 — Querying without leaking');


// ═══════════════════════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════════════════════

/**
 * @return array<string, mixed>
 */
function aRow(
    string $id,
    string $registration = 'AB12CDE',
    string $status = 'in_progress',
    int $lineCount = 2,
    int $totalMinor = 10450,
    string $currency = 'EUR',
    int $pending = 0,
): array {
    return [
        'job_card_id'                 => $id,
        'registration'                => $registration,
        'status'                      => $status,
        'line_count'                  => $lineCount,
        'total_minor'                 => $totalMinor,
        'currency'                    => $currency,
        'pending_authorisation_count' => $pending,
    ];
}

/** Five job cards: two live, one awaiting authorisation, one invoiced, one cancelled. */
function aDashboard(): JobCardSummaryQuery
{
    return new JobCardSummaryQuery([
        aRow('JC-1', 'AB12CDE', 'in_progress', 2, 10450),
        aRow('JC-2', 'XY98ZAB', 'booked', 0, 0),
        aRow('JC-3', 'CD34EFG', 'awaiting_authorisation', 3, 24000, 'EUR', 1),
        aRow('JC-4', 'EF56GHI', 'invoiced', 4, 99000),
        aRow('JC-5', 'GH78IJK', 'cancelled', 1, 4500),
    ]);
}

function fileOfClass(string $class): string
{
    $file = (new ReflectionClass($class))->getFileName();

    return $file === false ? '' : str_replace('\\', '/', $file);
}


// ═══════════════════════════════════════════════════════════════════════════
// WHICH SIDE OF THE LINE — the point of the lesson
// ═══════════════════════════════════════════════════════════════════════════

check(
    'Both classes exist, in the Infrastructure namespace',
    static fn(): bool => class_exists(JobCardSummary::class)
        && class_exists(JobCardSummaryQuery::class)
        && (new ReflectionClass(JobCardSummary::class))->getNamespaceName() === 'Infrastructure'
        && (new ReflectionClass(JobCardSummaryQuery::class))->getNamespaceName() === 'Infrastructure'
);

check(
    'NEITHER is in domain/workshop/ — a read model is not a domain concept',
    static function (): bool {
        foreach ([JobCardSummary::class, JobCardSummaryQuery::class] as $class) {
            if (str_contains(fileOfClass($class), 'domain/workshop/')) {
                return false;
            }
        }

        return true;
    }
);

check(
    'THE READ MODEL IMPORTS NOTHING FROM THE DOMAIN — no Money, no JobCardId, no enum',
    static function (): bool {
        $source = (string) file_get_contents(fileOfClass(JobCardSummary::class));

        preg_match_all('/^use\s+([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)/m', $source, $matches);

        foreach ($matches[1] as $imported) {
            if (str_starts_with(ltrim($imported, '\\'), 'Workshop\\')) {
                return false;
            }
        }

        // Also catches a fully-qualified reference with no import.
        return !str_contains(class_source_without_comments(JobCardSummary::class), 'Workshop\\');
    }
);

check(
    'The QUERY imports nothing from the domain either',
    static function (): bool {
        $code = class_source_without_comments(JobCardSummaryQuery::class);

        return !str_contains($code, 'Workshop\\');
    }
);

check(
    'Neither touches PDO or SQL — a read model is a query and a struct',
    static function (): bool {
        foreach ([JobCardSummary::class, JobCardSummaryQuery::class] as $class) {
            $code = class_source_without_comments($class);

            foreach (['PDO', 'SELECT', 'sqlite'] as $needle) {
                if (str_contains($code, $needle)) {
                    return false;
                }
            }
        }

        return true;
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// JobCardSummary — anemic, on purpose
// ═══════════════════════════════════════════════════════════════════════════

check(
    'JobCardSummary is final readonly',
    static function (): bool {
        $r = new ReflectionClass(JobCardSummary::class);

        return $r->isFinal() && $r->isReadOnly();
    }
);

check(
    'Its properties are PUBLIC — it is a projection, and there is nothing to protect',
    static function (): bool {
        $properties = (new ReflectionClass(JobCardSummary::class))->getProperties();

        if ($properties === []) {
            return false;
        }

        foreach ($properties as $property) {
            if (!$property->isPublic()) {
                return false;
            }
        }

        return true;
    }
);

check(
    'Every property is a scalar — no domain types anywhere',
    static function (): bool {
        foreach ((new ReflectionClass(JobCardSummary::class))->getProperties() as $property) {
            if (!in_array((string) $property->getType(), ['string', 'int', 'bool'], true)) {
                return false;
            }
        }

        return true;
    }
);

check(
    'fromRow() builds one from a joined row',
    static function (): bool {
        $summary = JobCardSummary::fromRow(aRow('JC-1', 'AB12CDE', 'in_progress', 2, 10450, 'EUR', 1));

        return $summary->jobCardId === 'JC-1'
            && $summary->registration === 'AB12CDE'
            && $summary->status === 'in_progress'
            && $summary->lineCount === 2
            && $summary->totalMinor === 10450
            && $summary->currencyCode === 'EUR'
            && $summary->pendingAuthorisationCount === 1;
    }
);

check(
    'IT CARRIES THE REGISTRATION — which no JobCard aggregate has',
    static function (): bool {
        // JobCard holds a VehicleId, not a Vehicle. A list on a screen needs
        // the registration, so the QUERY joins for it. That single field is the
        // argument for read models.
        return property_exists(JobCardSummary::class, 'registration')
            && JobCardSummary::fromRow(aRow('JC-1', 'XY98ZAB'))->registration === 'XY98ZAB';
    }
);

check(
    'formattedTotal(): EUR two places, JPY none, KWD three',
    static function (): bool {
        return JobCardSummary::fromRow(aRow('JC-1', 'AB', 'booked', 1, 10450, 'EUR'))->formattedTotal() === 'EUR 104.50'
            && JobCardSummary::fromRow(aRow('JC-2', 'AB', 'booked', 1, 4500, 'JPY'))->formattedTotal() === 'JPY 4500'
            && JobCardSummary::fromRow(aRow('JC-3', 'AB', 'booked', 1, 4500, 'KWD'))->formattedTotal() === 'KWD 4.500'
            && JobCardSummary::fromRow(aRow('JC-4', 'AB', 'booked', 1, 7, 'EUR'))->formattedTotal() === 'EUR 0.07';
    }
);

check(
    'statusLabel(): awaiting_authorisation -> "Awaiting Authorisation"',
    static fn(): bool => JobCardSummary::fromRow(aRow('JC-1', 'AB', 'awaiting_authorisation'))->statusLabel()
        === 'Awaiting Authorisation'
);

check(
    'isOpen(): everything except invoiced and cancelled',
    static function (): bool {
        $expected = [
            'booked'                 => true,
            'in_progress'            => true,
            'awaiting_authorisation' => true,
            'awaiting_parts'         => true,
            'completed'              => true,
            'invoiced'               => false,
            'cancelled'              => false,
        ];

        foreach ($expected as $status => $isOpen) {
            if (JobCardSummary::fromRow(aRow('JC-1', 'AB', $status))->isOpen() !== $isOpen) {
                return false;
            }
        }

        return true;
    }
);


// ═══════════════════════════════════════════════════════════════════════════
// JobCardSummaryQuery
// ═══════════════════════════════════════════════════════════════════════════

check(
    'JobCardSummaryQuery is final readonly and takes rows',
    static function (): bool {
        $r      = new ReflectionClass(JobCardSummaryQuery::class);
        $params = $r->getConstructor()?->getParameters() ?? [];

        return $r->isFinal()
            && $r->isReadOnly()
            && count($params) === 1
            && (string) $params[0]->getType() === 'array';
    }
);

check(
    'An empty result set is not an error',
    static function (): bool {
        $query = new JobCardSummaryQuery([]);

        return $query->all() === []
            && $query->open() === []
            && $query->count() === 0
            && $query->totalOpenValueMinor() === 0;
    }
);

check(
    'all() returns every row, as a list of summaries',
    static function (): bool {
        $all = aDashboard()->all();

        return count($all) === 5
            && array_keys($all) === [0, 1, 2, 3, 4]
            && $all[0] instanceof JobCardSummary;
    }
);

check(
    'open() excludes invoiced and cancelled',
    static function (): bool {
        $open = aDashboard()->open();

        if (count($open) !== 3 || array_keys($open) !== [0, 1, 2]) {
            return false;
        }

        foreach ($open as $summary) {
            if (!$summary->isOpen()) {
                return false;
            }
        }

        return true;
    }
);

check(
    'openByValueDescending() sorts largest first',
    static function (): bool {
        $sorted = aDashboard()->openByValueDescending();

        $totals = array_map(static fn(JobCardSummary $s): int => $s->totalMinor, $sorted);

        return $totals === [24000, 10450, 0];
    }
);

check(
    'awaitingAuthorisation() finds the ones with outstanding approvals',
    static function (): bool {
        $waiting = aDashboard()->awaitingAuthorisation();

        return count($waiting) === 1 && $waiting[0]->jobCardId === 'JC-3';
    }
);

check(
    'totalOpenValueMinor() sums the open ones only: 10450 + 0 + 24000',
    static fn(): bool => aDashboard()->totalOpenValueMinor() === 34450
);

check(
    'count() counts everything, open or not',
    static fn(): bool => aDashboard()->count() === 5
);

check(
    'A single query answers the whole dashboard — no aggregate is loaded',
    static function (): bool {
        // The point, stated as an assertion: none of these methods can possibly
        // have loaded a JobCard, because nothing here can reach one.
        $code = class_source_without_comments(JobCardSummaryQuery::class);

        return !str_contains($code, 'JobCard::')
            && !str_contains($code, 'JobCards')
            && !str_contains($code, 'repository');
    }
);


acceptance_report();
