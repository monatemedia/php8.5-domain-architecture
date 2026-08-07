# Challenge — Capstone: The Workshop, End to End

> **You write nine files and no business rules.**
>
> `domain/workshop/OpenJobCard.php` · `StartWork.php` · `CompleteJobCard.php` · `InvoiceJobCard.php`
> `infrastructure/ImportScan.php` · `WorkshopApp.php`
> `infrastructure/Http/JobCardsController.php` · `ProblemDetailsMiddleware.php` · `UnexpectedFailureLog.php`
>
> **You run:** `php module-6-capstone/lesson-6.1-the-workshop-end-to-end/challenge/starter.php`
> **Then run:** `php regression.php`

The first challenge that needs Slim and PHP-DI for real. The checks drive actual PSR-7 requests
through an actual Slim application against an actual SQLite database. Nothing is mocked.

**If assembling this requires changing anything in `domain/workshop/`, the domain was wrong.** That
is the test, and it is the one you can only run at the end.

---

## Read `checks.php` first. Section 1 is the thesis.

---

## 1 — `infrastructure/ImportScan.php`

```php
final readonly class ImportScan
{
    public const array INFRASTRUCTURAL = [
        'Infrastructure\\', 'PDO', 'Slim\\', 'Psr\\', 'DI\\',
        'PHPUnit\\', 'Doctrine\\', 'Illuminate\\',
    ];

    public static function of(string ...$directories): self;

    public function fileCount(): int;
    public function references(): array;                 // every name, sorted, unique
    public function violations(?array $forbidden = null): array;   // file => offending names
    public function isClean(): bool;
    public function report(): string;
}
```

**Use `token_get_all()`, not a regular expression.** Two bugs in this project came from source greps
that could not tell one thing from another — a dependency from a doc block explaining it, and
`PartStock` from `PartStockRow`. Both are in `RUN-THIS-FIRST.md`, and both produced the same rule:

> A check that reads source is a parser with the parsing left out.

Collect two kinds of reference:

- **`use` statements** — stopping at `as` (the alias is a local nickname; the original is the
  dependency) and ignoring a closure's `use (...)`, which is not an import at all.
- **`T_NAME_FULLY_QUALIFIED` tokens anywhere** — `\PDO`, `\Slim\App`. A file with no `use` statements
  can still type-hint `\PDO $connection`, and a scan that only reads imports would call it clean.

Skip any path segment called `infrastructure` or `given`. A reference solution keeps its own outer
layer beside it, and scanning that would report infrastructure for being infrastructure.

`report()` must name **the file and the import**. "The domain is impure" is not an error message
anybody can act on.

---

## 2 — Four use cases, in `domain/workshop/`

```php
final readonly class OpenJobCard
{
    public function __construct(private JobCards $jobCards) {}

    public function __invoke(string $vehicleId, string $accountHolderId, string $currency): JobCardId;
}
```

Plus `StartWork`, `CompleteJobCard` and `InvoiceJobCard`, each taking a `string $jobCardId` and
returning `void`.

Three assertions worth reading before you start:

- **Each takes the `JobCards` interface**, not an implementation. Checked by reflection.
- **Not one `if`** in any of the four. Checked against the source. Every refusal comes from a domain
  type; a use case that decides something has taken a rule off an aggregate.
- **Not one `catch`.** Failures travel to the boundary untouched.

They go in `domain/workshop/` and are therefore **scanned**. A use case is properly an application
concern and a three-layer project would give it its own root — this one has two, and adding a third
for four classes is ceremony. Sharing the domain's root means submitting to a harder standard, not an
easier one.

### `remove()` then `add()`

The three write use cases have to store an aggregate that already exists, and `JobCards` has no
`save()`. Lesson 3.1 chose that deliberately and said *watch for it*.

Write it out. Do not hide it behind a base class or a trait — README section 8 is about what it costs,
and hiding it would hide the argument.

---

## 3 — `infrastructure/WorkshopApp.php`

The composition root, and the only file in the codebase where these four names appear together:

```php
JobCards::class => static fn(ContainerInterface $c): JobCards => new GuardedJobCards(
    new SqliteJobCards($c->get(\PDO::class), $c->get(JobCardMapper::class))
),
```

**The guard goes OUTSIDE.** A check asserts it, because the order decides whether a `PDOException`
reaches the application as a `StorageFailure` or as itself.

Routes:

```
POST /job-cards
GET  /job-cards/{id}
POST /job-cards/{id}/work
POST /job-cards/{id}/completion
POST /job-cards/{id}/invoice
```

**Middleware order is LIFO in Slim.** Add `ProblemDetailsMiddleware` **last**, so it runs outermost
and can catch a failure from routing, from body parsing, or from anything a route calls. Add it first
and an unmatched route returns Slim's HTML error page to a client that asked for JSON.

Do not define the use cases or the controller in the container. PHP-DI reads their constructor type
hints, which works only because those constructors ask for types.

---

## 4 — `infrastructure/Http/`

**`JobCardsController`** — read the request, call one thing, shape the response. A check asserts the
file contains no `catch` at all.

`show()` uses the repository directly rather than a `ViewJobCard` use case. A read orchestrates
nothing, and a class whose entire body is `return $this->jobCards->get($id);` is a layer added because
the diagram has one.

**`ProblemDetailsMiddleware`** — a try and two catches. `Slim\Exception\HttpException` first, because
its status is already correct and the request never reached anything of yours; then `\Throwable`,
mapped by `ProblemDetailsMapper`.

**`UnexpectedFailureLog`** — record anything the mapper turned into a **5xx**, and nothing else. A
4xx is the system working. This is what reconciles Lesson 4.1's "let it be loud" with an outermost
`catch (\Throwable)`: the user gets an opaque 500, the operator gets the exception.

---

## Acceptance criteria

- [ ] `ImportScan` finds the domain layer and reports it **clean**
- [ ] The scanner is proved able to fail — an import of Slim is caught, an alias does not hide one
- [ ] A doc block naming `\PDO` and `Slim\App` is **not** a violation
- [ ] A closure's `use ($x)` is not mistaken for an import
- [ ] Four use cases, each taking the interface, none with an `if` or a `catch`
- [ ] `GuardedJobCards` wraps `SqliteJobCards`, not the other way round
- [ ] The whole lifecycle runs over HTTP and the change is visible in a raw SQL read
- [ ] An illegal transition is a 409 `application/problem+json` with `from`, `to` and `allowed`
- [ ] An unknown job card is 404; an unmatched route is a 404 problem document
- [ ] A dead database is an opaque 500 that leaks nothing, and **is** reported to the operator
- [ ] A 4xx is **not** reported to the operator
- [ ] `php regression.php` is green

---

## Hints

<details>
<summary>Hint 1 — the two kinds of reference</summary>

```php
foreach (token_get_all($source) as $token) {
    if (!is_array($token)) {
        continue;
    }

    if ($token[0] === T_NAME_FULLY_QUALIFIED) {
        $references[] = ltrim($token[1], '\\');
    }

    // T_USE needs a small state machine — see hint 2.
}
```

`T_NAME_FULLY_QUALIFIED` is PHP 8.0+ and covers `\PDO`, `\Slim\App` and `#[\NoDiscard]` in one case.

</details>

<details>
<summary>Hint 2 — reading a use statement</summary>

Walk forward from `T_USE` until `;`, and handle three things:

- `(` — this is a closure's `use ($x)`. Stop, record nothing.
- `T_AS` — everything after it is a local nickname. Stop recording, but keep what you have.
- `,` — a grouped import. Record what you have and start again.

</details>

<details>
<summary>Hint 3 — driving Slim without a web server</summary>

```php
$request = (new ServerRequestFactory())
    ->createServerRequest('POST', '/job-cards')
    ->withHeader('Content-Type', 'application/json');

$request->getBody()->write((string) json_encode($payload));
$request->getBody()->rewind();

$response = $app->handle($request);
```

`Slim\App` is a PSR-15 `RequestHandlerInterface`, so `handle()` runs the whole middleware stack.
`rewind()` matters — body parsing reads from the current position.

</details>

<details>
<summary>Hint 4 — if the lifecycle check fails at "completion"</summary>

The job card is probably still `booked`, which means `POST /work` did not persist. Check that your
write use cases do `remove()` then `add()`: `SqliteJobCards` cannot tell that an aggregate you are
holding has changed, and the collection-oriented interface gives it nowhere to find out.

That is the cost Lesson 3.1 warned about, arriving.

</details>

<details>
<summary>Hint 5 — if an unmatched route returns HTML</summary>

Middleware order. `$app->add()` is LIFO, so the last thing added runs first. Your middleware must be
added **after** `addRoutingMiddleware()`, which puts it outside the router.

</details>

---

## When it passes

Compare with `solution/`. Worth reading in this order:

1. `WorkshopApp::container()` — the `JobCards` definition, as a summary of Modules 3 and 4.1
2. `ProblemDetailsMiddleware::process()` — twenty lines, and why it can be twenty lines
3. `CompleteJobCard::store()` — the bill for Lesson 3.1's interface
4. `ImportScan` — the doc block on why it reads tokens

---

## Then, without running anything

**Q1.** The scan reports that the domain layer's entire external vocabulary is four SPL names.
Write down what that result would look like in a codebase you have actually worked on, and what the
first three entries would be.

**Q2.** `CompleteJobCard` does `remove()` then `add()`, and between them the job card does not exist.
Choose a unit of work or an explicit `save()`, and justify the choice in terms of what Lesson 3.1
said the trade was.

**Q3.** `POST /job-cards` with an empty `vehicleId` returns 500. Write down the change that makes it
422, how many files it touches, and why nobody noticed for two modules.

**Q4.** The middleware catches `\Throwable`, which Lesson 4.1 called the most expensive
two-character change in the module. Reconcile the two positions in three sentences.

**Q5.** The scan cannot catch `$container->get('Slim\App')`. Name one other thing it cannot catch,
and say whether either is worth closing.

**Q6.** You are now going to apply this to something real. Write down the first thing you will do,
and check it against Module 0 and Lesson 5.1 before you start.
