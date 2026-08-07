<?php
declare(strict_types=1);

namespace Infrastructure;

/**
 * The workshop dashboard, answered without loading a single aggregate.
 *
 * REFERENCE IMPLEMENTATION. A QUERY SERVICE.
 *
 * WHY THIS IS NOT ON THE JobCards REPOSITORY
 * -------------------------------------------
 * Lesson 3.1 was strict: every query the domain needs is a named method on a
 * repository, written in the workshop's words. That was right, and it does not
 * extend to this.
 *
 * `JobCards` promises AGGREGATES. Answering "the dashboard, sorted by value"
 * through it means loading every open job card, with every line, to produce a
 * table of six columns — and then throwing the objects away. At forty job cards
 * that is merely wasteful; at four thousand it is the slowest page in the
 * system, and the cost is entirely structural.
 *
 * The tell, from Lesson 3.1's own quiz: WHAT COMES BACK IS NOT AN AGGREGATE. It
 * is a table. Forcing a table through an interface that promises aggregates is
 * the wrong shape twice over.
 *
 * WHY THE INTERFACE IS NOT IN THE DOMAIN EITHER
 * ----------------------------------------------
 * This is the part that surprises people, because 3.1 insisted the repository
 * interface belonged in `domain/workshop/`.
 *
 * The difference is what the thing IS. `JobCards::awaitingAuthorisation()` is a
 * question the service manager asks every morning — a domain concept, with a
 * name, that would exist if the software did not. `JobCardSummaryQuery` serves
 * a SCREEN. Its columns are whatever that screen shows this month, its sort
 * order is whatever somebody preferred, and it will change when the screen
 * does.
 *
 * A domain interface that changes when a screen changes is not a domain
 * interface. So the whole thing — query, read model, and any interface over it
 * — lives in infrastructure, and the domain does not know it exists.
 *
 * THE TEST, WHICH IS THE SAME ONE AS ALWAYS
 * ------------------------------------------
 * Delete the database and the framework. Does `awaitingAuthorisation()` still
 * mean something to the workshop? Yes. Does "the dashboard, sorted by total
 * descending, showing the registration"? No — it describes a page.
 *
 * WHY IT TAKES ROWS
 * -----------------
 * Same reason as the mapper in 3.2: no PDO here, so every branch is testable
 * without a database. In production the rows come from one query with a join
 * and a couple of aggregates; here they are handed in.
 *
 * The SQL that produces them is not in this course, and that is deliberate —
 * writing it is the least interesting part, and pretending otherwise is how
 * "read model" acquires a reputation for ceremony. A read model is a query and
 * a struct. That is all it is.
 */
final readonly class JobCardSummaryQuery
{
    /** @var list<JobCardSummary> */
    private array $summaries;

    /**
     * @param list<array<string, mixed>> $rows joined rows, one per job card
     */
    public function __construct(array $rows)
    {
        $summaries = [];

        foreach ($rows as $row) {
            $summaries[] = JobCardSummary::fromRow($row);
        }

        $this->summaries = $summaries;
    }

    /** @return list<JobCardSummary> */
    public function all(): array
    {
        return $this->summaries;
    }

    /**
     * Everything still live, in the dashboard's sense of live.
     *
     * @return list<JobCardSummary>
     */
    public function open(): array
    {
        return array_values(array_filter(
            $this->summaries,
            static fn(JobCardSummary $summary): bool => $summary->isOpen()
        ));
    }

    /**
     * Largest first — the order the service manager wants at 8am.
     *
     * Sorting here rather than in SQL is a choice, and in production it would
     * be an ORDER BY. It is done in PHP so this class stays testable without a
     * database; the shape of the answer is identical either way.
     *
     * @return list<JobCardSummary>
     */
    public function openByValueDescending(): array
    {
        $open = $this->open();

        usort(
            $open,
            static fn(JobCardSummary $a, JobCardSummary $b): int => $b->totalMinor <=> $a->totalMinor
        );

        return $open;
    }

    /**
     * @return list<JobCardSummary>
     */
    public function awaitingAuthorisation(): array
    {
        return array_values(array_filter(
            $this->summaries,
            static fn(JobCardSummary $summary): bool => $summary->pendingAuthorisationCount > 0
        ));
    }

    /**
     * The money currently on the workshop floor.
     *
     * NOTE WHAT THIS IS NOT: it is not a domain calculation, and it must never
     * be treated as one. It sums minor units across job cards without checking
     * that they share a currency, which `Money` would refuse to do — and
     * refusing is right for the domain and useless for a dashboard, where the
     * workshop trades in one currency and wants a number.
     *
     * If that number ever becomes an input to a decision — a credit limit, a
     * bonus calculation — it stops being a dashboard figure and belongs in the
     * domain, with Money and its refusals. Until then it is a headline on a
     * page.
     *
     * That distinction is Lesson 1.4's: evidence versus input to a decision.
     */
    public function totalOpenValueMinor(): int
    {
        $total = 0;

        foreach ($this->open() as $summary) {
            $total += $summary->totalMinor;
        }

        return $total;
    }

    public function count(): int
    {
        return count($this->summaries);
    }
}
