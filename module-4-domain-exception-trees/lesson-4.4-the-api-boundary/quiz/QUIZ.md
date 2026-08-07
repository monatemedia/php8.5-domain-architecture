# Quiz — Lesson 4.4: The API Boundary
> Closed book. **20 questions. Threshold: 15.**

---

## Section A — Multiple choice

**Q1.** RFC 9457 (July 2023):

- A) Introduced Problem Details for the first time.
- B) Obsoletes RFC 7807 — which most articles and several packages still cite, though the wire
  format did not change.
- C) Is a draft.
- D) Applies only to gRPC.

---

**Q2.** The correct media type is:

- A) `application/json`
- B) `application/problem+json`
- C) `text/problem`
- D) `application/vnd.problem+json`

---

**Q3.** `title` and `detail` differ in that:

- A) `title` is shorter.
- B) `title` describes the problem TYPE and never varies between occurrences; `detail` describes
  THIS occurrence and always does.
- C) `detail` is optional.
- D) `title` is machine-readable.

---

**Q4.** Putting the job card id in `title` breaks:

- A) JSON encoding.
- B) Any client grouping, counting or alerting by problem type — every occurrence becomes a distinct
  type, and the alert never fires.
- C) The status code.
- D) Nothing; it is a style preference.

---

**Q5.** Extension members are:

- A) Nested under an `extensions` key.
- B) Top-level members, which is why one named `status` would overwrite the HTTP status.
- C) Limited to strings.
- D) Not permitted by the RFC.

---

**Q6.** 409 versus 422 corresponds to:

- A) Read versus write.
- B) `WorkshopException` versus `InvalidWorkshopInput` — "fix the world" versus "fix your request".
- C) 4xx versus 5xx.
- D) Nothing in the domain.

---

**Q7.** 400 is the wrong status for a refused business rule because:

- A) It is deprecated.
- B) 400 means the request was malformed; anything reaching the domain parsed fine and was
  understood, then refused.
- C) 400 is for GET only.
- D) Clients ignore it.

---

**Q8.** The mapper keys its catalogue by `failureCode()` rather than by class because:

- A) Strings compare faster.
- B) Keying by class puts a domain class name into infrastructure, so a rename becomes a boundary
  change — and a class split silently stops matching.
- C) `match` cannot switch on a class.
- D) Failure codes are shorter.

---

**Q9.** `map()` takes `\Throwable` rather than `WorkshopFailure` because:

- A) It is more convenient.
- B) Otherwise every caller has to decide what to do with everything else, and one of them will put
  `getMessage()` into a 500.
- C) `WorkshopFailure` is not a type.
- D) PSR-7 requires it.

---

**Q10.** The opaque 500 body contains:

- A) The exception message, for debugging.
- B) `type: about:blank`, `title: Internal Server Error`, `status: 500`, and nothing else.
- C) The class name and a trace.
- D) The failure code.

---

**Q11.** When `type` is `about:blank`, the RFC says `title` should be:

- A) Omitted.
- B) The HTTP status phrase.
- C) The class name.
- D) A URI.

---

**Q12.** Domain messages are safe to put in `detail` because:

- A) They are short.
- B) Lesson 4.2 moved every message onto a class in `Workshop\`, and they name job cards and
  statuses — never a class, a file or a table.
- C) They are escaped.
- D) They are translated.

---

**Q13.** The leak test inspects the decoded structure rather than the JSON text because:

- A) It is faster.
- B) An encoder turns a quoted part name into `\"` and an em dash into `\uXXXX`, so a raw-text search
  for a backslash reports a leak for correct output.
- C) `json_encode` can fail.
- D) The structure is smaller.

---

**Q14.** The word "Error" is deliberately **not** in the forbidden-substring list because:

- A) It is too common.
- B) The correct opaque title is "Internal Server Error", and a leak test that fails on the right
  answer gets switched off.
- C) `\Error` is not an exception.
- D) It would be case-sensitive.

---

**Q15.** `JobCardIsTerminal` carries `status` in its context. The fix is:

- A) Rename it in the domain.
- B) Rename it in the mapper — "status" is the workshop's word, and the wire format's reservation is
  the wire format's problem.
- C) Drop the key.
- D) Nest the context.

---

## Section B — Short answer

**Q16.** A `JobCardNotFound` should be a 404 from `GET /job-cards/{id}` and a 422 from
`POST /invoices` with an unknown id in the body. Explain why, and say what that fact rules out.

---

**Q17.** An unmapped domain failure becomes an opaque 500. Defend that choice in two sentences, then
name the mechanism that makes it never happen.

---

**Q18.** `fromDecision()` sets the document's status from the **first** refusal rather than the
highest or most severe. Give the reason.

---

**Q19.** `ProblemDetails::of()` throws when an extension member would shadow a reserved one, rather
than silently letting it win or silently dropping it. Argue for throwing.

---

**Q20.** This lesson added two classes and changed nothing in `domain/workshop/`. Explain what had to
be true of Lessons 4.1–4.3 for that to be possible, naming one specific decision from each.

---
---

# Answer key

**Q1 — B.** Citing a withdrawn RFC in a design document is the kind of thing a reviewer notices.

**Q2 — B.** The part most implementations forget.

**Q3 — B.**

**Q4 — B.**

**Q5 — B.**

**Q6 — B.** It matters to a client that automates retries: a corrected payload can turn a 422 into a
success, and retrying a 409 with the same payload fails identically until something else changes.

**Q7 — B.** Using 400 for everything is the HTTP equivalent of `throw new \DomainException`.

**Q8 — B.**

**Q9 — B.**

**Q10 — B.**

**Q11 — B.**

**Q12 — B.** And it stops being true the moment somebody writes
`throw new SomeDomainException('SQL error: ' . $pdo->errorInfo()[2])`, which is why it is worth
stating as a rule rather than assuming.

**Q13 — B.**

**Q14 — B.**

**Q15 — B.** It would also break Lesson 4.2's checks, which assert `context()['status'] ===
'invoiced'` and run in the regression sweep — but that is the second reason, not the first. The first
is Rule 4: use their words.

**Q16.** Because the status is about the **request**, not about the failure. In the GET, the URL
names something that is not there — 404. In the POST, the URL is correct and it is the *body* that
names something that is not there, so the request is well-formed but unprocessable — 422.

It rules out `httpStatus()` on the exception. The exception is raised inside a repository that has
never seen an HTTP request and could not know which of the two applied. That is the complete answer
to the "the domain knows better than the boundary" argument, and it does not depend on taste.

**Q17.** The alternative is guessing at a status, and a wrong 4xx tells a client something false with
confidence while a 500 says we do not know, which is true. It is uncomfortable — a 4xx reported as a
5xx is the mistake this module opened with — and it is the right default for a case that must never
occur.

The mechanism: an **exhaustiveness check** in `checks.php` that walks every leaf of the tree and
asserts each maps to something other than 500. A tenth exception added without a catalogue entry
fails the build.

**Q18.** So that the ask-path and the act-path agree. `Decision::orThrow()` throws the first refusal,
so a client that skips the check and simply submits gets the same status it would have got from
asking. Two paths, one answer — Lesson 4.3's drift argument extended to the wire.

**Q19.** Silently letting the extension win replaces the HTTP status with a string like `"invoiced"`,
and every client's error handling breaks differently — some crash on a non-integer, some compare it
to 409 and get false, some pass it to a switch that falls through. Silently dropping it loses
information the domain deliberately attached, and does so invisibly.

Throwing turns a data-modelling collision into a failing test at the point where somebody can still
choose what to do about it. It is a constructor, not a request handler; the cost of being strict is
paid once, in a suite.

**Q20.** Each of the three had to put something in the domain that is *about failure* without being
*about transport*:

- **4.1** — `WorkshopFailure` as a marker interface spanning both SPL branches. Without it, `map()`
  could not have a single `instanceof` check separating "safe to render" from "opaque 500", and the
  boundary would need a list of classes.
- **4.2** — `failureCode()` declared per class rather than derived from the class name. It is what
  the catalogue keys on, so the mapper never names a domain class, and a rename is not a breaking
  API change.
- **4.2, also** — `context()` restricted to scalars and lists of scalars. That is what lets the
  extension members be built with no serialisation logic in infrastructure; a `JobCardId` object
  would have forced the mapper to know a domain type.
- **4.3** — `Decision` collecting every refusal, which is what `fromDecision()` renders. The
  exception path could never have produced a multi-error body.

Full credit needs one decision per lesson and an explanation of what it enabled, not just a list.

---

## Scoring

| Score | Verdict |
|-------|---------|
| 18–20 | Module 4 complete. Take Gate 4 in `GATES.md`. |
| 15–17 | Pass. Re-read sections 4 and 6 before the gate. |
| 11–14 | Run examples 02 and 03, then retake. |
| 0–10 | Re-read the lesson. Section 3 is the part you will use most often. |
