<?php
declare(strict_types=1);

namespace Infrastructure;

/**
 * One row of a job card list, ready to render.
 *
 * REFERENCE IMPLEMENTATION. A READ MODEL, and almost everything about it
 * contradicts Module 1.
 *
 * IT IS ANEMIC ON PURPOSE
 * -----------------------
 * Public readonly properties, no behaviour beyond formatting, no invariants,
 * nothing refused. Lesson 1.1 spent a whole lesson arguing against exactly this
 * shape — and said, in section 7, that a DTO whose only job is to be rendered
 * is the one place it is correct.
 *
 * This is that place. There is no rule to protect, because nothing here is ever
 * changed, saved, or reasoned about. It is a projection of state that already
 * exists, on its way to a screen.
 *
 * IT IMPORTS NOTHING FROM THE DOMAIN
 * -----------------------------------
 * No Money, no JobCardId, no JobCardStatus. Scalars only. The checks assert it,
 * and the reason is not purity:
 *
 *     A read model that can reach the domain will eventually contain a rule.
 *
 * Give it a `Money` and someone adds `withDiscount()`. Give it a
 * `JobCardStatus` and someone adds `canBeInvoiced()` — and now the invoicing
 * rule exists in two places, one of which is a display object nobody thinks of
 * as authoritative. That is Lesson 1.4's scattered guards, arriving through a
 * door marked "presentation".
 *
 * THE HONEST COUNTER-ARGUMENT
 * ---------------------------
 * Plenty of good codebases do put value objects in read models, and gain real
 * things: `Money` formats correctly per currency without duplicating the logic
 * here, and a typed id cannot be passed to the wrong route.
 *
 * The cost is the coupling above, and it is a slope rather than a cliff. This
 * course takes the strict line because the failure it prevents — a business
 * rule quietly living in a view object — is expensive and hard to spot. If you
 * take the other line, put the boundary somewhere and say where.
 *
 * NOTE WHAT IT CARRIES THAT NO AGGREGATE HAS
 * -------------------------------------------
 * `registration`. A job card holds a VehicleId, not a Vehicle — Lesson 2.1 was
 * emphatic about it. But a list on a screen has to show the registration, so
 * the QUERY joins and the read model carries it.
 *
 * That is the whole argument for read models in one field: the shape a screen
 * needs and the shape a consistency boundary needs are different shapes, and
 * trying to serve both from one object compromises the boundary.
 */
final readonly class JobCardSummary
{
    public function __construct(
        public string $jobCardId,
        public string $registration,
        public string $status,
        public int    $lineCount,
        public int    $totalMinor,
        public string $currencyCode,
        public int    $pendingAuthorisationCount,
    ) {}

    /**
     * @param array<string, mixed> $row a joined row, already shaped by the query
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['job_card_id'],
            (string) $row['registration'],
            (string) $row['status'],
            (int) $row['line_count'],
            (int) $row['total_minor'],
            (string) $row['currency'],
            (int) $row['pending_authorisation_count'],
        );
    }

    /**
     * Presentation, and only presentation.
     *
     * Note this duplicates a little of Currency::format(). That is a real cost
     * and it is accepted deliberately: importing the enum would put a domain
     * type in a view object, and this is two lines that will not change.
     *
     * If it were twenty lines, or if it encoded a business rule, the trade
     * would go the other way — a shared formatter in the presentation layer,
     * still not the domain enum.
     */
    public function formattedTotal(): string
    {
        $places = match ($this->currencyCode) {
            'JPY' => 0,
            'KWD' => 3,
            default => 2,
        };

        if ($places === 0) {
            return $this->currencyCode . ' ' . $this->totalMinor;
        }

        $divisor = 10 ** $places;

        return sprintf(
            '%s %d.%s',
            $this->currencyCode,
            intdiv($this->totalMinor, $divisor),
            str_pad((string) ($this->totalMinor % $divisor), $places, '0', STR_PAD_LEFT)
        );
    }

    /** 'awaiting_authorisation' -> 'Awaiting Authorisation' */
    public function statusLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->status));
    }

    /**
     * A screen concept, not a domain one.
     *
     * The domain says Invoiced and Cancelled are terminal, which it expresses
     * as an empty transition list. A list view wants "is this still live?", and
     * the two happen to coincide today.
     *
     * They may not always, and that is the point: this is the DASHBOARD's
     * definition of open, and if the workshop ever decides a cancelled job card
     * should still appear in the morning list, this changes and JobCardStatus
     * does not.
     */
    public function isOpen(): bool
    {
        return !in_array($this->status, ['invoiced', 'cancelled'], true);
    }
}
