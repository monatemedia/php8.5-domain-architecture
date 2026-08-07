# Challenge — Lesson 3.3: The Dashboard Read Model

> **You write:** `infrastructure/JobCardSummary.php` · `infrastructure/JobCardSummaryQuery.php`
> **You run:** `php module-3-persistence-without-leakage/lesson-3.3-querying-without-leaking/challenge/starter.php`
> **Done when it prints:** `ACCEPTANCE: all checks passed`

Short, and the placement checks are the exam. **Nothing goes in `domain/workshop/` this time** —
which is the opposite of Lesson 3.1, for a reason worth being able to state.

---

## Read `checks.php` first.

---

## 1 — `infrastructure/JobCardSummary.php` — the read model

`final readonly class`, namespace `Infrastructure`, **public** properties.

| Property | Type |
|----------|------|
| `jobCardId` · `registration` · `status` · `currencyCode` | `string` |
| `lineCount` · `totalMinor` · `pendingAuthorisationCount` | `int` |

Every property is a **scalar**. The checks assert it.

| Method | |
|--------|--|
| `fromRow(array $row): self` | keys: `job_card_id`, `registration`, `status`, `line_count`, `total_minor`, `currency`, `pending_authorisation_count` |
| `formattedTotal(): string` | `EUR 104.50` · `JPY 4500` · `KWD 4.500` · `EUR 0.07` |
| `statusLabel(): string` | `awaiting_authorisation` → `Awaiting Authorisation` |
| `isOpen(): bool` | everything except `invoiced` and `cancelled` |

### It imports nothing from the domain

No `Money`, no `JobCardId`, no `JobCardStatus`. The checks assert there is no `Workshop\` anywhere in
the file, imported or fully qualified.

> **A read model that can reach the domain will eventually contain a rule.**

Give it a `Money` and somebody adds `withDiscount()`. Give it a `JobCardStatus` and somebody adds
`canBeInvoiced()` — and the invoicing rule now lives in two places, one of which nobody regards as
authoritative.

README §5 gives the honest counter-argument. Plenty of good codebases do the opposite; what is not
defensible is doing it without deciding.

### Yes, that means duplicating a little

`formattedTotal()` repeats some of `Currency::format()`. `isOpen()` repeats something
`JobCardStatus::isTerminal()` knows. Both are accepted deliberately — and `isOpen()` is not even the
same question. See §6.

### It carries the registration

Which no `JobCard` has. That single field is the argument for read models.

---

## 2 — `infrastructure/JobCardSummaryQuery.php` — the query service

`final readonly class`, constructor takes `array $rows`.

| Method | |
|--------|--|
| `all(): array` | `list<JobCardSummary>` |
| `open(): array` | excludes invoiced and cancelled |
| `openByValueDescending(): array` | largest total first |
| `awaitingAuthorisation(): array` | `pendingAuthorisationCount > 0` |
| `totalOpenValueMinor(): int` | sum of the open ones |
| `count(): int` | everything |

**No PDO, no SQL** — the checks assert it. In production the rows come from one query with a join
and a `SUM`; here they are handed in, so every branch is testable without a database. Same reason as
the mapper in 3.2.

### Why there is no interface in the domain for this

3.1 insisted the *repository* interface belonged in `domain/workshop/`. This one does not, and being
able to say why is the point of the lesson:

| | `JobCards::awaitingAuthorisation()` | this |
|---|---|---|
| A business concept? | yes — asked every morning | no — it describes a page |
| Would exist without software? | yes, on a clipboard | no |
| Changes when | the business changes | the screen changes |

**A domain interface that changes when a screen changes is not a domain interface.**

### `totalOpenValueMinor()` sums across currencies without complaining

`Money` would refuse that, and refusing is right for the domain and useless for a dashboard, where
the workshop trades in one currency and wants a number on a page.

If that number ever becomes an **input to a decision** — a credit limit, a bonus — it stops being a
dashboard figure and belongs in the domain, with `Money` and its refusals. Lesson 1.4's distinction:
evidence versus input to a decision.

---

## Acceptance criteria

- [ ] Both files in `infrastructure/`, namespace `Infrastructure`
- [ ] Neither is in `domain/workshop/`
- [ ] Neither contains `Workshop\` anywhere — imported or fully qualified
- [ ] Neither contains `PDO`, `SELECT` or `sqlite`
- [ ] Every `JobCardSummary` property is public and scalar
- [ ] An empty result set is not an error
- [ ] `php regression.php` is green

---

## Hints

<details>
<summary>Hint 1 — formattedTotal without importing Currency</summary>

```php
$places = match ($this->currencyCode) {
    'JPY' => 0,
    'KWD' => 3,
    default => 2,
};
```

A `match` on the code. Yes, it duplicates the enum — see §6, and note it is four lines that will not
change.

</details>

<details>
<summary>Hint 2 — build the summaries once</summary>

The class is `readonly`, so map the rows in the constructor and keep the list. Every method then
filters an array of objects rather than re-parsing rows.

</details>

<details>
<summary>Hint 3 — sorting</summary>

```php
usort($open, static fn(JobCardSummary $a, JobCardSummary $b): int => $b->totalMinor <=> $a->totalMinor);
```

`$b <=> $a` for descending. Sort a **copy** — `$this->open()` already returns one, but be sure you
are not sorting a property.

</details>

---

## When it passes

Compare with `solution/`. Worth looking at:

- The note on `totalOpenValueMinor()` about when it stops being a dashboard figure
- The note on `isOpen()` about it being a screen concept rather than a domain one
- Whether they sorted in PHP or left a comment about `ORDER BY`

---

## Then, without running anything

**Q1.** A second screen wants job cards grouped by account holder, with a count and no totals. Do you
add methods here, or write a second read model? Justify it in two sentences.

**Q2.** The dashboard join gets slow at 50,000 job cards. Name two options that do not involve
touching the domain, and say which you would try first.

**Q3.** Somebody proposes `JobCardSummary::canBeInvoiced()` so the dashboard can show an "Invoice"
button only where it would work. What is wrong with it, and what would you do instead?

**Q4.** This lesson duplicates `Currency::format()` and `JobCardStatus::isTerminal()`. Argue that the
duplication is a mistake and the read model should import the domain. Then say which you believe.

Q3 is the one to think hardest about — it is the exact mechanism by which a read model stops being
one.
