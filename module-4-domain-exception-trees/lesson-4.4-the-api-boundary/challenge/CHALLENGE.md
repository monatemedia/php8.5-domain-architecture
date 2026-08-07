# Challenge — Lesson 4.4: The API Boundary

> **You write:** `infrastructure/ProblemDetails.php` · `infrastructure/ProblemDetailsMapper.php`
> **You edit:** nothing
> **You run:** `php module-4-domain-exception-trees/lesson-4.4-the-api-boundary/challenge/starter.php`
> **Then run:** `php regression.php`
> **Done when both are green.**

Look at that file list again. **`domain/workshop/` is not touched by this lesson.** Everything the
boundary needs was already there as `failureCode()` and `context()`, and neither knows what HTTP is.

That is the result being demonstrated. The mapper is just the thing that demonstrates it.

---

## Read `checks.php` first. Section 4 is the lesson.

---

## 1 — `infrastructure/ProblemDetails.php`

```php
final readonly class ProblemDetails implements \JsonSerializable
{
    public const array RESERVED = ['type', 'title', 'status', 'detail', 'instance'];
    public const string MEDIA_TYPE = 'application/problem+json';

    public static function of(
        string $type,
        string $title,
        int $status,
        ?string $detail = null,
        ?string $instance = null,
        array $extensions = [],
    ): self;

    #[\NoDiscard('Returns a new ProblemDetails — the original is unchanged')]
    public function withInstance(string $instance): self;

    public function jsonSerialize(): array;
    public function toJson(): string;
}
```

Four things the checks assert, each a decision:

**`of()` refuses an extension member that shadows a reserved one.** Extension members are top level,
so a `status` key in a context would overwrite the HTTP status with the string `"invoiced"`. A
collision should be a loud failure in a test, not a quiet one in production.

**`of()` refuses a status outside 4xx–5xx.** A 2xx problem document is a contradiction.

**`jsonSerialize()` omits null members** rather than writing `"detail": null`, and puts the five
reserved members **before** the extensions. Not required — JSON objects are unordered — but a human
reading a log wants the shape before the particulars.

**`withInstance()` uses `clone($this, [...])`.** A mapper does not know the request path and should
not; a route does. So `instance` is attached last, by whoever has it.

`toJson()` uses `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`, so the type URI reads as a URI
and an em dash stays an em dash.

---

## 2 — `infrastructure/ProblemDetailsMapper.php`

```php
final readonly class ProblemDetailsMapper
{
    public const string TYPE_PREFIX = 'https://workshop.example/problems/';

    public function __construct(private array $statusOverrides = []) {}

    #[\NoDiscard('Returns a new mapper — the original is unchanged')]
    public function withStatusFor(string $failureCode, int $status): self;

    public function map(\Throwable $failure, ?string $instance = null): ProblemDetails;

    public function fromDecision(Decision $decision, ?string $instance = null): ProblemDetails;
}
```

### The catalogue

Nine entries, one per leaf of the tree. The statuses fall out of the branch:

| Code | Status | Why |
|------|--------|-----|
| `job-card-not-found` | 404 | the URL names a thing that is not there |
| `line-not-found` | 404 | same |
| `job-card-already-exists` | 409 | understood; the state forbids it |
| `illegal-status-transition` | 409 | same |
| `job-card-is-terminal` | 409 | same |
| `nothing-awaiting-authorisation` | 409 | same |
| `outstanding-authorisations` | 409 | same |
| `line-currency-mismatch` | 422 | the content of the request is wrong |
| `missing-work-description` | 422 | same — and this one is `InvalidWorkshopInput` |

**Key it by `failureCode()`, not by class.** `match ($failure::class)` puts
`Workshop\IllegalStatusTransition` into an infrastructure file, so renaming a domain class becomes a
change to the boundary — and the day somebody splits that class in two, this file silently stops
matching and everything becomes a 500.

### `map()` takes `\Throwable`

Not `WorkshopFailure`. The checks assert the parameter type.

A mapper that only accepted domain failures would push the "what about everything else" decision out
to every caller, and one of them would write `$e->getMessage()` into a 500.

```php
if (!$failure instanceof CarriesContext) {
    return $this->opaque($instance);
}
```

That one branch is the security model. Everything else — `StorageFailure`, `PDOException`,
`TypeError`, an exception written next year by somebody who never read Module 4 — falls to `opaque()`
and reveals nothing.

### The opaque body

```json
{"type": "about:blank", "title": "Internal Server Error", "status": 500}
```

No detail. No extensions. `about:blank` is the RFC's default, and when `type` is `about:blank` the
RFC says `title` should be the status phrase — which is exactly what that is.

**Do not put `$failure->getMessage()` in there "just for debugging."** That message is
`SQLSTATE[HY000] [2002] Connection refused`, or a file path, or half a query with a customer's email
in it. The operator gets it from the log, where 4.1's `previous` chain put it.

---

## 3 — The `status` collision

`JobCardIsTerminal` carries `status` in its context — the *job card's* status, `"invoiced"`. RFC
9457 reserves `status` for the HTTP code.

**Do not fix this in the domain.** "Status" is the workshop's word for the thing a job card is in
(Rule 4), and it was their word before HTTP existed. Changing it would also break Lesson 4.2's
checks, which assert `context()['status'] === 'invoiced'` and run in the regression sweep.

The mapper renames it to `jobCardStatus` on the way out. One entry in a rename table, and it is
worth writing down in a comment that the mechanism is crude — it renames by key name alone, so a
second failure carrying a different `status` would break one of them.

---

## 4 — `fromDecision()`

Lesson 4.3's ask-path, at the boundary. Every refusal in one body, under an `errors` extension
member.

**The document's status is the FIRST refusal's**, not the highest or most severe. Deliberately: a
client that skips the check and just submits gets the same status it would have got here. The checks
assert `fromDecision($d)->status === map($thrownByOrThrow)->status`.

**It throws on an allowed Decision.** There is no problem to report, and a problem document for a
success is a lie the type system can prevent.

---

## Acceptance criteria

- [ ] Both classes are in `infrastructure/`; `domain/workshop/` is unchanged
- [ ] `of()` refuses reserved-member collisions and non-4xx/5xx statuses
- [ ] `jsonSerialize()` omits nulls and orders reserved members first
- [ ] `withInstance()` and `withStatusFor()` return new objects and carry `#[\NoDiscard]`
- [ ] `map()` accepts `\Throwable`
- [ ] **All nine leaves map to a 4xx** — the exhaustiveness check
- [ ] Every `InvalidWorkshopInput` is a 422
- [ ] Every non-`CarriesContext` becomes an opaque 500 with no detail and no extensions
- [ ] **No body leaks** a namespace separator, a filename, a path, `SQLSTATE`, a class name or a
      stack frame
- [ ] `JobCardIsTerminal`'s `status` arrives as `jobCardStatus`, and `status` is the integer 409
- [ ] `fromDecision()` reports every refusal and agrees with `orThrow()` on the status
- [ ] `php regression.php` is green

---

## Hints

<details>
<summary>Hint 1 — the shape of map()</summary>

```php
public function map(\Throwable $failure, ?string $instance = null): ProblemDetails
{
    if (!$failure instanceof CarriesContext) {
        return $this->opaque($instance);
    }

    $entry = self::CATALOGUE[$failure->failureCode()] ?? null;

    if ($entry === null) {
        return $this->opaque($instance);
    }

    return ProblemDetails::of(
        type: self::TYPE_PREFIX . $failure->failureCode(),
        title: $entry['title'],
        status: $this->statusOverrides[$failure->failureCode()] ?? $entry['status'],
        detail: $failure->getMessage(),
        instance: $instance,
        extensions: $this->extensionsFrom($failure),
    );
}
```

Two early returns and one construction. If yours is longer, something that belongs in the catalogue
is in a conditional.

</details>

<details>
<summary>Hint 2 — the rename</summary>

```php
private const array RENAMES = ['status' => 'jobCardStatus'];

private function rename(array $context): array
{
    $renamed = [];

    foreach ($context as $key => $value) {
        $renamed[self::RENAMES[$key] ?? $key] = $value;
    }

    return $renamed;
}
```

</details>

<details>
<summary>Hint 3 — if the leak check fails on a body you think is clean</summary>

Read what the check reports — it names the substring and the string it found it in.

Two likely causes:

- **`kind` or some other member is being built from `$failure::class`.** Use `failureCode()`.
- **You are testing `toJson()` rather than `jsonSerialize()`.** The course's checks inspect the
  decoded structure precisely because an encoder turns a quoted part name into `\"`. If you wrote
  your own raw-text assertion, it is finding JSON escaping, not a leak.

</details>

<details>
<summary>Hint 4 — the exhaustiveness check failing</summary>

One of the nine codes has no catalogue entry, so it fell through to 500. Compare the keys of your
catalogue against the nine `failureCode()` strings from Lesson 4.2. A typo in a kebab-case string is
the most likely cause and the hardest to see.

That is the check doing exactly its job: it exists so that a tenth exception, added a year from now
without a catalogue entry, fails the build instead of quietly becoming a 500.

</details>

---

## When it passes

Compare with `solution/`. Worth looking at:

- The doc block on `withStatusFor()` — the GET-versus-POST case, which is the complete answer to
  "why not `httpStatus()` on the exception"
- The comment on `opaque()` about what the operator gets versus what the client gets
- Why `map()` takes `\Throwable`, and why the unknown-domain-failure case is a 500

---

## Then, without running anything

**Q1.** The mapper's catalogue lives in `infrastructure/`. Lesson 4.2's quiz raised the objection
that it will drift from the tree it describes. State how this lesson answers that, and then say what
the answer does **not** cover. (There is a gap. It is about the `title` strings.)

**Q2.** An unmapped domain failure becomes a 500 — a 4xx reported as a 5xx, which is exactly the
mistake this module opened with. Defend the choice, then describe the one situation in which you
would take the other side.

**Q3.** `detail` carries `$failure->getMessage()` for domain failures, and the lesson argues that is
safe because those messages were written for somebody who runs a workshop. Write down the code
review rule that keeps it true, and say which of Module 4's lessons would have to be violated for it
to stop being true.

**Q4.** The `errors` extension member is a convention, not a registered RFC 9457 member. Look up
what "registered" means here — the IANA HTTP Problem Type registry — and say what you would have to
do to make `errors` official, and whether you would.

**Q5.** You now have `failureCode()` in the domain and a status table in infrastructure. A colleague
proposes a third place: a translations file mapping failure codes to user-facing messages in five
languages, so `detail` can be localised. Where does that file belong, what does it do to the
argument in section 6 about domain messages being safe to send, and does `detail` stay?
