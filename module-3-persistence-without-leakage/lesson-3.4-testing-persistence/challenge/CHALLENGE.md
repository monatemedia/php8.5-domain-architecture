# Challenge — Lesson 3.4: `SqliteJobCards` and the Contract

> **You write:** `infrastructure/SqliteJobCards.php`
> **You run:** `php module-3-persistence-without-leakage/lesson-3.4-testing-persistence/challenge/starter.php`
> **Done when it prints:** `ACCEPTANCE: all checks passed`

One class. Everything else in this lesson is the **contract** — the same assertions run against your
in-memory repository from 3.1 and your SQLite one, to prove they are substitutable.

The in-memory half should pass immediately. It already did in 3.1.

---

## Read `checks.php` first — and notice its shape

Most of it is written **once** and run **twice**:

```php
foreach (implementations() as $name => $make) {
    foreach (contractFor($make) as [$label, $assertion]) {
        check(sprintf('[%-8s] %s', $name, $label), $assertion);
    }
}
```

There is even a check asserting that the two label lists are identical — because the moment somebody
special-cases one implementation, it has stopped being a contract test.

---

## `infrastructure/SqliteJobCards.php`

```php
namespace Infrastructure;

final class SqliteJobCards implements JobCards
{
    public function __construct(private \PDO $connection, private JobCardMapper $mapper) {}

    public static function createSchema(\PDO $connection): void;
    // ...the seven interface methods
}
```

**It takes a `PDO` and a `JobCardMapper`.** It does not build either — the checks assert the
constructor's types. Injecting the connection is what lets a test hand it `sqlite::memory:`;
injecting the mapper is what keeps the mapping testable without a database, which was 3.2's whole
argument.

### The schema

```sql
job_cards        id, vehicle_id, account_holder_id, currency, status, pending_authorisations
job_card_lines   line_id, job_card_id, position, kind, description,
                 tenths, rate_minor, part_number, quantity, unit_price_minor
```

`createSchema(\PDO $connection): void` builds both. `CREATE TABLE IF NOT EXISTS` so it is safe to
call twice.

### `position` — the column the domain knows nothing about

`JobCard::lines()` promises insertion order. **An array gives that free; a table does not.** A
`SELECT` with no `ORDER BY` returns rows in whatever order suits the planner, and that order can
change when an index is added.

So store a position and `ORDER BY position ASC` when reading. The mapper does **not** produce it —
ordering is a storage concern, and the checks assert `JobCard` never mentions the word.

This is the drift a fake hides most completely, because SQLite *usually* returns insertion order
anyway. The contract asserts ordering deliberately.

### One aggregate, one transaction

`add()` writes a job card row and one row per line. Wrap it — a job card whose header was written and
whose lines were not is exactly the half-applied write Lesson 2.2 opened with.

`remove()` deletes lines then the job card, in a transaction, for the same reason.

### `add()` refuses a duplicate

Throw `\DomainException` **before** touching the database. A `PRIMARY KEY` violation would also stop
it, and it would surface as a `PDOException` — an infrastructure failure where the interface promised
a domain one. That distinction is Lesson 4.1's whole subject, and getting it right here costs one
`contains()` call.

---

## Acceptance criteria

- [ ] `infrastructure/SqliteJobCards.php`, namespace `Infrastructure`, implementing `JobCards`
- [ ] Constructor takes `\PDO` and `JobCardMapper`, in that order
- [ ] `createSchema()` works on a fresh `sqlite::memory:` database
- [ ] `job_card_lines` has a `position` column; `JobCard` never mentions one
- [ ] The SQL is here; the mapping stays in `JobCardMapper`
- [ ] Every contract assertion passes for **both** implementations
- [ ] `php regression.php` is green

---

## Hints

<details>
<summary>Hint 1 — the position, added at insert time</summary>

```php
foreach ($this->mapper->toLineRows($jobCard) as $position => $lineRow) {
    $insertLine->execute($lineRow + ['position' => $position]);
}
```

`toLineRows()` returns a list, so the key *is* the position. The mapper stays unaware.

</details>

<details>
<summary>Hint 2 — reading back</summary>

```php
$statement = $this->connection->prepare(
    'SELECT * FROM job_card_lines WHERE job_card_id = :id ORDER BY position ASC'
);
```

Then hand the job card row and the line rows straight to `$this->mapper->fromRows(...)`. This class
does no mapping at all.

</details>

<details>
<summary>Hint 3 — heredoc for the schema</summary>

```php
$connection->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS job_cards (
        id TEXT PRIMARY KEY,
        ...
    )
    SQL);
```

Nowdoc — `<<<'SQL'` with quotes — so nothing in the SQL is interpolated.

</details>

<details>
<summary>Hint 4 — the transaction</summary>

```php
$this->connection->beginTransaction();

try {
    // ...inserts...
    $this->connection->commit();
} catch (\Throwable $e) {
    $this->connection->rollBack();
    throw $e;
}
```

Rethrow. Swallowing it would leave the caller believing the job card was stored.

</details>

---

## When it passes

Compare with `solution/`. Worth looking at:

- Whether they checked for a duplicate before or inside the transaction
- Whether `forVehicle()` and `awaitingAuthorisation()` share a hydration helper
- The class note on the gap the contract cannot close

---

## Then, without running anything

**Q1.** The contract does not assert that `get()` returns the same instance you added — Lesson 3.1's
checks did. Explain why that assertion was right there and wrong here.

**Q2.** Change a job card after `add()`-ing it, then `get()` it. The two implementations give
different answers. Whose bug is it, and what are the three ways to resolve it?

**Q3.** `SqliteJobCards::awaitingAuthorisation()` queries `status = 'awaiting_authorisation'` — a
string literal that duplicates `JobCardStatus`'s backing value. Is that a leak? Argue both ways.

**Q4.** Your suite is green against SQLite and the production database is PostgreSQL. Name two things
that could still fail, and say what it would cost to find out.

Q2 is Module 3's honest ending, and your answer to it is worth writing down before Module 5 — which
is about exactly this kind of compromise, at a scale where you do not get to choose.
