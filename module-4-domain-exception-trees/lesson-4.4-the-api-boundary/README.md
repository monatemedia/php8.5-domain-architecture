# Lesson 4.4 — The API Boundary

> **Module 4 · Lesson 4 of 4**
> Prerequisites: 4.1, 4.2, 4.3. This lesson adds **nothing** to `domain/workshop/`, and that is the
> result being demonstrated.

---

## 1. Where the module has been going

Three lessons of groundwork, and this is what it was for:

```php
try {
    $jobCard->invoice();
    return $response->withStatus(204);
} catch (WorkshopFailure $e) {
    return $this->problem($mapper->map($e, $request->getUri()->getPath()));
} catch (\Exception $e) {
    $this->logger->error('unhandled', ['exception' => $e]);
    return $this->problem($mapper->map($e));
}
```

Two clauses. The first produces a 409 with a sentence the service manager could have written and
fields a client can act on. The second produces a 500 that says nothing.

Compare Lesson 4.1's opening:

```php
catch (\Throwable $e) {
    return new Response(500, [], 'Internal Server Error');
}
```

Everything between those two snippets is Module 4.

---

## 2. RFC 9457, and the one you should stop citing

**RFC 9457, "Problem Details for HTTP APIs", July 2023.** It **obsoletes RFC 7807**, which is what
most articles, several framework packages and a good deal of internal documentation still cite.

The wire format did not change, so 7807 code is not broken — but the registry and the guidance did,
and citing a withdrawn RFC in a design document is the kind of thing a reviewer notices. Cite the
current one.

### The five members

| Member | Is | Changes per occurrence? |
|--------|-----|------------------------|
| `type` | a URI reference identifying the problem **type**; default `about:blank` | no |
| `title` | a short human summary of the **type** | no |
| `status` | the HTTP status, duplicated into the body | no |
| `detail` | human-readable, about **this** occurrence | yes |
| `instance` | a URI reference identifying **this** occurrence | yes |

### `title` versus `detail` is the one people get wrong

`title` describes the *kind* of problem. `detail` describes *this* one.

Put the job card id in the title and every occurrence becomes a distinct problem type, so a
dashboard grouping by title is useless and an alert on "more than 50 of these an hour" never fires.
The RFC says clients should not parse `detail` for the same reason Lesson 4.2 said they should not
parse a message.

### Extension members

Anything else, **at the top level** — not nested under an `extensions` key:

```json
{
  "type": "https://workshop.example/problems/illegal-status-transition",
  "title": "Illegal status transition",
  "status": 409,
  "detail": "Job card JC-1042 is In Progress and cannot become Invoiced.",
  "instance": "/job-cards/JC-1042/invoice",
  "jobCardId": "JC-1042",
  "from": "in_progress",
  "to": "invoiced",
  "allowed": ["awaiting_authorisation", "awaiting_parts", "completed", "cancelled"],
  "kind": "state"
}
```

**That is `context()` from Lesson 4.2, serialised.** Which is why that lesson insisted on scalars
and lists of scalars: this is the moment a `JobCardId` object would have needed the mapper to know
how to serialise a domain type.

### The media type

`application/problem+json`, not `application/json`. It is how a client knows to look for these
members without guessing, and it is the part most implementations forget.

---

## 3. Status codes, and why not just 400

| | Meaning | Branch of the tree |
|---|---|---|
| **404** | the thing named in the **URL** is not there | `JobCardNotFound`, `LineNotFound` |
| **409** | understood; the current **state** forbids it | `WorkshopException` |
| **422** | understood; the **content** of the request is wrong | `InvalidWorkshopInput` |

**409 versus 422 is Lesson 4.2's two roots, arriving at the wire.**

- *"Fix the world, then try again"* → 409
- *"Fix your request, then try again"* → 422

That difference matters to a client that automates retries: a corrected payload can turn a 422 into
a success; retrying a 409 with the same payload fails identically until something else changes. Send
both as 400 and the difference is gone.

**Why not 400 at all?** 400 means the request was *malformed* — bad JSON, a missing header, an
unparseable date. Anything reaching your domain parsed fine. It was understood and refused. Using
400 for everything is the HTTP equivalent of `throw new \DomainException`.

---

## 4. The mapper switches on the code, not the class

```php
$entry = self::CATALOGUE[$failure->failureCode()] ?? null;   // yes
$entry = match ($failure::class) { ... };                    // no
```

`match ($failure::class)` works and is wrong. It puts `Workshop\IllegalStatusTransition` into an
infrastructure file, so renaming a domain class becomes a change to the boundary — and the day
somebody splits that class in two, this file silently stops matching and everything becomes a 500.

Switching on `failureCode()` couples to a string the domain declared as a public contract on
purpose. Lesson 4.2 section 6 argued for that string before there was anything reading it. This is
the thing reading it.

### The unknown case is a 500, and it is asserted against

A domain failure with no catalogue entry becomes an opaque 500. That is uncomfortable — a 4xx
reported as a 5xx is exactly the mistake this module opened with — and it is still right, because
the alternative is guessing. **A wrong 4xx tells a client something false with confidence; a 500
tells it we do not know, which is true.**

The real answer is that it must never happen, and that is a **test**: `checks.php` walks all nine
leaves of the tree and asserts each maps to something other than 500. Add a tenth exception without
a catalogue entry and the build fails.

That test is the answer to the objection raised in Lesson 4.2's quiz — that a table in
infrastructure will drift from the domain it describes. It will not, because something checks.

---

## 5. Same failure, two correct statuses

The strongest argument for putting `httpStatus()` on the exception, and the thing that defeats it:

```
GET  /job-cards/JC-9999              JobCardNotFound -> 404
                                     The URL names a thing that is not there.

POST /invoices {"jobCardId": "..."}  JobCardNotFound -> 422
                                     The URL is fine. The BODY names a thing
                                     that is not there.
```

Same exception, same failure code, two correct answers — because **the status is about the request,
and the domain does not know what was asked.**

So the catalogue is a *default*, and a route that knows better overrides it:

```php
$mapper->withStatusFor('job-card-not-found', 422)->map($e);
```

This is also the complete answer to "the domain knows better than the boundary whether it is a 404
or a 409." It does not. It could not.

---

## 6. Nothing internal reaches the wire

The rule, and the reason this lesson has a leak test rather than a paragraph:

> **Only a `CarriesContext` produces a readable body. Everything else produces a 500 that says
> nothing.**

Everything else means: `StorageFailure`, `PDOException`, `TypeError`, a `LogicException` from a
library, and an exception written next year by somebody who never read Module 4.

```php
public function map(\Throwable $failure, ?string $instance = null): ProblemDetails
{
    if (!$failure instanceof CarriesContext) {
        return $this->opaque($instance);
    }
    // ...
}
```

Note the parameter type is `\Throwable`, not `WorkshopFailure`. A mapper that only accepted domain
failures would push the "what about everything else" decision out to every caller, and one of them
would write `$e->getMessage()` into a 500.

### The opaque body

```json
{"type": "about:blank", "title": "Internal Server Error", "status": 500}
```

No detail. No extensions. No class name, no message, no path.

The temptation is `'detail' => $failure->getMessage()`, "just for debugging". That message is
`SQLSTATE[HY000] [2002] Connection refused`, or a file path, or half a query with a customer's email
in it. The operator gets it from the log, where Lesson 4.1's `previous` chain put it. The client
gets nothing, because the client can do nothing with it.

`type` stays `about:blank` — the RFC's default, meaning "no more specific type than the status
code" — and when it is `about:blank` the RFC says `title` should be the status phrase.

### Why the domain messages *are* safe to send

Because Lesson 4.2 moved every message onto a class in `Workshop\`. Those messages name job cards
and statuses. They never name a class, a file or a table, because they were written for somebody who
runs a workshop.

That is a property worth stating out loud, because it is the thing that makes `detail:
$failure->getMessage()` defensible rather than reckless — and it stops being true the moment
somebody writes `throw new SomeDomainException('SQL error: ' . $pdo->errorInfo()[2])`.

### How the test is written

The checks inspect the **decoded structure**, not the JSON text, and that is a deliberate choice
worth copying.

An encoder escapes things: a quoted part name becomes `\"`, and without `JSON_UNESCAPED_UNICODE` an
em dash becomes `—`. A raw-text search for a backslash therefore reports a leak for a message
that merely quotes a part number. **A leak test that fires on correct output is a leak test somebody
switches off.**

Testing the values means the encoding cannot cause a false alarm or hide a real one:
`Workshop\JobCard` is a backslash in the decoded string whatever the encoder did to it.

For the same reason the forbidden list does **not** contain the bare word "Error" — the opaque
title is "Internal Server Error", which is the correct answer.

And the suite tests the detector itself: one check feeds it a body containing a class name and a
file path and asserts it fires. A leak test that cannot fail is not a test.

---

## 7. The collision the domain refused to fix

`JobCardIsTerminal` carries `status` in its context — the *job card's* status, `"invoiced"`. RFC
9457 reserves `status` for the HTTP code.

Serialise the context unchanged and the body says `"status": "invoiced"`: not an integer, not 409,
and every client's error handling breaks differently.

**The fix is not in the domain.** "Status" is the workshop's word for the thing a job card is in —
Rule 4, use their words — and it was their word before HTTP existed. The wire format's reservation
is the wire format's problem, so the mapper renames it to `jobCardStatus` on the way out.

`ProblemDetails::of()` also *refuses* an extension member that would shadow a reserved one, rather
than letting it overwrite silently. A collision should be a loud failure in a test, not a quiet one
in production.

The rename table has one entry and the reference implementation says plainly that the mechanism is
crude: it renames by key name alone, so a second failure carrying a different `status` would break
one of them. At that point the honest fix is a per-code table, or renaming in the domain and
accepting the cost. One entry does not justify either yet.

---

## 8. A `Decision` at the boundary

Lesson 4.3's ask-path produces every reason at once. At the boundary that becomes:

```json
{
  "type":   "https://workshop.example/problems/outstanding-authorisations",
  "status": 409,
  "detail": "Job card JC-1042 cannot be completed: 1 item(s) ...",
  "errors": [
    {"code": "outstanding-authorisations", "type": "...", "detail": "...", "descriptions": ["..."]},
    {"code": "illegal-status-transition",  "type": "...", "detail": "...", "from": "..."}
  ]
}
```

**The status is the first refusal's**, not the highest or the most severe. Deliberately: a client
that skips the check and just submits gets the same status it would have got here. Two paths, one
answer — the drift argument from 4.3, extended to the wire.

`errors` as a plural extension member is the conventional shape for multiple problems, and RFC 9457
section 3 discusses the case. It is a **convention, not a registered member** — worth knowing before
you tell a client it is standard.

And `fromDecision()` throws if handed an *allowed* Decision. There is no problem to report, and
building a problem document for a success is a lie the type system can prevent.

---

## 9. What is deliberately not here

**No Slim, no PSR-7, no middleware.** The mapper is a pure function from a `\Throwable` to a value,
which is what makes it testable in an assertion harness with no web server. The capstone wires it
into a real request cycle, and by then there will be nothing interesting left to decide.

**Nothing added to `domain/workshop/`.** Check the challenge's file list: both classes are in
`infrastructure/`. Everything the boundary needed was already in the domain as `failureCode()` and
`context()`, and neither knows what HTTP is.

That is the result. Not the mapper — the fact that building it required no changes to the domain at
all.

---

## 10. Where else this shows up

| Domain | 404 | 409 | 422 |
|--------|-----|-----|-----|
| E-commerce | unknown SKU in the URL | basket already checked out | coupon code malformed |
| Insurance | policy number not found | claim outside cover period | claim amount not a number |
| Payments | unknown payment intent | already captured | currency not supported |
| Booking | unknown reservation | seat taken since you loaded the page | date in the past |

Column two is worth the most attention. Every row is *"we understood you, and the world has moved
or was never that way."* If your API returns 400 for those, clients cannot distinguish "try again
differently" from "try again later", and the retry logic they write will be wrong in whichever
direction hurts you most.

---

## 11. Examples

```bash
php module-4-domain-exception-trees/lesson-4.4-the-api-boundary/examples/01-what-a-client-sees.php
php module-4-domain-exception-trees/lesson-4.4-the-api-boundary/examples/02-the-leak.php
php module-4-domain-exception-trees/lesson-4.4-the-api-boundary/examples/03-one-failure-two-statuses.php
```

Example 02 builds four leaky boundaries, each leaking something different, and runs the same
detector over all of them. It is the one to run if section 6 reads like paranoia.

---

## 12. Then

- `challenge/CHALLENGE.md` — two classes, both in `infrastructure/`
- `quiz/QUIZ.md` — 20 questions, threshold 15
- `GATES.md` — **Gate 4**, which carries forward to Modules 1, 2 and 3

Module 4 is done. Next is **Module 5**, which asks what any of this survives contact with Laravel.
