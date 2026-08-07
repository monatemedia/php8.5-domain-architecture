# Gates
> Advanced Domain Architecture & Tactical DDD — end-of-module recall

---

## How to take a gate

**Every other file shut.** No README, no examples, no notes, no editor. A gate you can pass with
the material open tells you nothing you did not already know.

Each gate has two parts:

- **Part 1** covers the module you have just finished.
- **Part 2** reaches back to something you finished a while ago.

Part 2 is the part that does the work. A concept recalled once, and then again three modules later,
is held far longer than one recalled twice in the same afternoon. It will feel harder than Part 1.
That is the mechanism working, not you failing.

**Answer out loud or in writing, in full sentences, *then* read the key.** Recognising a correct
answer is not the same as producing one, and only one of the two transfers to a codebase.

Log every gap in the *Notes & Sticking Points* table in `PROGRESS.md`. Those are your re-read
targets.

---

# Gate 0 — After Module 0

## Part 1 — Module 0

**G0.1** A colleague proposes tactical DDD for a new internal tool. Name **four** signals that
would justify it and **three** that would argue against.

**G0.2** What, specifically, does tactical DDD cost? Give at least three costs, and say who pays
each one and when.

**G0.3** The workshop manager says "account holder". Your code says `Customer`. Describe a
concrete way this costs you money later — not "it is confusing", an actual sequence of events.

**G0.4** Explain a bounded context to someone who has never heard the term, using one example
where the *same word* means two genuinely different things.

## Part 2 — Carried forward from *PHP 8.5 OOP Mastery*

**G0.5** What is a service locator, why is it worse than constructor injection, and what does that
have to do with anything in this course?

**G0.6** State the Liskov Substitution Principle in your own words, then say why it matters to a
repository interface with two implementations.

---

<details>
<summary><strong>Gate 0 — answer key</strong> (open only after you have answered)</summary>

**G0.1** *Justifying:* an invariant spans more than one entity; the lifecycle has illegal
transitions rather than just states; the rules are argued about by people who do not write code;
being wrong costs money, breaks a law, or must be explained to a regulator. Weaker but real: the
codebase will outlive its current framework; multiple teams must agree on the same rules.

*Arguing against:* the database schema is the model and everyone is content with that; the work is
reporting, content management, or configuration; the only rule anyone can articulate is "this field
is required". Also: a team that has not done it before and a deadline that does not permit learning
— an unfamiliar pattern applied under time pressure produces the ceremony without the benefit.

**G0.2** Indirection, paid on every read of the code by every developer, forever. More files, so
navigation is slower and "where does this happen?" is a harder question. A mapping layer between
the model and storage, which you must write, test and maintain. Slower onboarding — a new developer
productive in a day on Active Record takes a week here. And a real risk of *architecture theatre*:
the layers exist, the discipline does not, and you have paid every cost for none of the benefit.

**G0.3** A concrete chain: the workshop manager reports that "an account holder can't have two open
job cards". You search for `AccountHolder`, find nothing, and conclude the report is confused.
Later you discover `Customer`, but by then you have also learned that the business distinguishes
*account holder* (who pays) from *driver* (who brings the vehicle in) — a distinction your single
`Customer` class silently collapsed. Every rule about billing and every rule about vehicle handover
now live on the same class, and separating them is a migration rather than a rename. The
translation layer did not merely slow conversation down; it hid a modelling error for months.

**G0.4** A bounded context is a boundary inside which one set of words has one agreed meaning. The
same word outside that boundary is a different word that happens to be spelled the same.
Example: *vehicle*. To the workshop it is a thing with a service history, an odometer and a set of
open faults. To accounts it is a line on an invoice — a registration string and nothing else. To
the parts desk it is a compatibility key: engine code, model year, trim. One shared `Vehicle` class
serving all three accumulates every field any of them needs, and a change made for one breaks the
others. Three models, three contexts, translated at the edges, is the working answer.

**G0.5** A service locator is a global registry that classes reach into for their dependencies:
`$container->get(Mailer::class)` inside a business class. It is worse than constructor injection
because the dependency is invisible in the signature — you cannot tell what a class needs without
reading its whole body, you cannot substitute a fake without configuring a global, and the class is
now coupled to the container itself. Its relevance here: the domain layer must depend on nothing.
A domain object reaching for a container has imported the entire infrastructure layer through a
side door, and Rule 1 is broken as thoroughly as if it had imported PDO directly.

**G0.6** Subtypes must be usable anywhere the supertype is, without the caller needing to know
which one it has. It matters to a repository interface because you will have at least two
implementations — an in-memory one for domain tests and a real one for production — and the entire
value of that arrangement depends on them being genuinely interchangeable. If the in-memory one
returns objects in insertion order and the SQL one returns them in an arbitrary order, your tests
pass and production is wrong. Lesson 3.4's contract test — one suite run against both
implementations — is LSP cashed in as something you can execute.

</details>

---

# Gate 1 — After Module 1

## Part 1 — Module 1

**G1.1** Define an anemic domain model. Then name the thing that makes it *tempting*, because
"people are careless" is not the reason it is so common.

**G1.2** Give three properties every value object must have. For each, name a bug that appears when
it is missing.

**G1.3** Two `Vehicle` objects have identical make, model, colour and odometer. Are they the same
vehicle? Two `Money` objects have identical amount and currency. Are they the same money? Explain
why the answers differ.

**G1.4** A rule says "a job card cannot be invoiced before it is complete". Name three places that
rule could be enforced, and say which one owns it and why.

## Part 2 — Carried forward from Module 0

**G1.5** You have just spent a module building value objects. A colleague asks whether they should
do the same in their CRUD admin panel. What do you actually ask them before answering?

**G1.6** How does a value object help with ubiquitous language, as opposed to merely with type
safety?

---

<details>
<summary><strong>Gate 1 — answer key</strong> (open only after you have answered)</summary>

**G1.1** An anemic model is a class of public getters and setters holding data, with all the
behaviour living in a separate "service" that operates on it. Structurally it is a procedural
program: records plus functions, with objects used only as containers.

What makes it *tempting* is that it is what every ORM tutorial, every scaffolding generator and
every framework's default model looks like — the shape is handed to you for free, and it works
perfectly well right up until two services need to enforce the same rule. It is also genuinely the
right answer for simple CRUD, which is why it is so hard to argue against in the abstract. The
correct objection is never "anemic models are bad"; it is "this particular model has eleven rules
in four services and nobody can tell you what a valid job card is".

**G1.2** *Immutability* — without it, an object handed to two collaborators can be changed by one
behind the other's back, and any invariant checked at construction can be violated afterwards.
*Structural equality* — without it, `$a == $b` compares identity, so two `Money` values of 500 EUR
are "different", and every comparison, `in_array`, and array key silently misbehaves.
*Self-validation at construction* — without it, an invalid instance can exist, so every consumer
must re-check, and the day one of them forgets is the day a negative price reaches an invoice.

**G1.3** The `Vehicle`s are two different vehicles: an entity's identity is a continuous thread
independent of its attributes, so two objects with identical attributes are still two entities, and
one vehicle whose every attribute changes is still that vehicle. The `Money`s are the same money:
a value object *has* no identity beyond its attributes — it is defined entirely by them, which is
exactly why swapping one for an equal one is always safe. The practical test: ask whether it makes
sense to say "this one, specifically". It does for a vehicle. It does not for five hundred euros.

**G1.4** Candidates: the API boundary (validate the request before it reaches the model); a
service (`InvoicingService::invoice()` checks the status first); the `JobCard` itself
(`invoice()` throws unless complete).

The model owns it. It is an always-true invariant of a job card, not a property of one request or
one service. Enforced at the boundary, it holds only for requests that come through that boundary
— not for a console command, a queue worker, a data migration, or the second controller somebody
adds next year. Enforced in a service, it holds only for callers who remember to use that service.
Enforced on `JobCard`, it holds everywhere, including in code that has not been written yet.

The boundary may still validate, but for a different reason: to produce a good error message
early. That is presentation, and it is a duplicate of the rule rather than the home of it. The
distinction matters — a duplicate that disagrees with the model is a bug, a duplicate that agrees
is a courtesy.

**G1.5** Ask what breaks today. Specifically: has a bad value ever reached the database, and did
anyone care? Is there a rule about that field that lives in more than one place? Does anyone in the
business argue about what a valid one is? If the answers are no, no and no, a value object buys
them a file and costs them a mapping, and the correct advice is to leave it alone. Rule 6: ceremony
must be paid for. A course you have just finished is the single worst reason to introduce a
pattern.

**G1.6** Type safety is about what the compiler will refuse. Ubiquitous language is about what the
code *says*. `RegistrationNumber` does both, but the second is the larger win: a signature reading
`bookIn(RegistrationNumber $reg, OdometerReading $odo)` can be read aloud to the workshop manager,
and they can tell you it is wrong — that you also need to know who is bringing it in. A signature
reading `bookIn(string $a, int $b)` cannot be read aloud to anyone, so it is never corrected. The
value object gives a business concept a name and a home, which means the business can find it.

</details>

---

# Gate 2 — After Module 2

## Part 1 — Module 2

**G2.1** Define an aggregate. Then say what the definition *excludes*, because the two most common
wrong answers are both plausible.

**G2.2** Invariant I10 — a courtesy vehicle is on at most one open job card at any time. Your
`CourtesyVehicle` refuses a second allocation. Explain precisely why that is not I10, and what
`CourtesyFleet` added that fixed it.

**G2.3** Why does an aggregate *record* events rather than dispatch them? Give the obvious reason
and the one that is worse.

**G2.4** Name the four tests for a genuine domain service, and say which one most false positives
fail.

## Part 2 — Carried forward from Modules 0 and 1

**G2.5** `CourtesyFleet` locks the whole fleet on every allocation, including cars nobody touched.
Justify that using Rule 6, and say what would make the justification stop working.

**G2.6** `JobCard::total()` is derived, and `LabourLine::total()` is derived, and Lesson 2.1 §6 says
a real billing system would probably store the job card total. Reconcile those.

---

<details>
<summary><strong>Gate 2 — answer key</strong> (open only after you have answered)</summary>

**G2.1** The set of objects that must be correct **together, at the same instant, inside one
transaction**.

*What it excludes:* it is **not** "everything reachable from the root" — reachability is a property
of your object graph, not of the business, and taking it as the definition gives you an aggregate
the size of your model. It is also **not** "things that belong together conceptually" — a vehicle
and its job cards belong together in conversation and are two aggregates. The question is only ever:
*if these two changed a second apart, would the business be harmed?*

**G2.2** `CourtesyVehicle` protects **one object in memory**. Load the same record twice — two
requests, two repository calls, entirely ordinary — allocate each copy to a different job card, and
save both. Every object obeyed its own rule and one car is promised to two customers.

What the fleet added is **ownership, not a check**. It holds every vehicle, so there is exactly one
instance of each, and Lesson 1.1's guard finally has something to run against. The check was never
wrong; it had nothing to compare with. Saying "the fleet enforces I10" is slightly inaccurate — the
vehicle still enforces it, and the fleet makes enforcement possible.

**G2.3** *The obvious reason:* an aggregate holding a dispatcher has a dependency on infrastructure.
It cannot be constructed without one, so every test, mapper and fixture needs one, and Rule 1 is
broken.

*The worse one:* handlers run **inside** the operation, before the transaction commits. If anything
later rolls back, the job card is not invoiced — and the customer has already been emailed a bill
and accounting has already been posted. The database rolled back; the outside world did not, and you
cannot un-send an email. Recording means handlers only ever run for things that actually happened.

**G2.4** (1) The business has a name for the operation. (2) It belongs to no single object. (3) It
is stateless. (4) It works only on domain objects.

**Test 4 catches most false positives.** Nearly everything people call a domain service turns out to
load something, and the moment it does it is an application service — orchestration rather than
domain logic, and it belongs outside `domain/`. Test 2 catches the rest: the operation usually
belongs on an object that already exists.

**G2.5** *The justification:* twelve courtesy vehicles and eight allocations a day. Contention
requires two people clicking within the same few milliseconds, which at that volume happens roughly
never, and the whole aggregate loads in under a millisecond. In exchange, I10 is instantaneous and
provable — no reconciliation job, no compensation, no window, and nobody ever rings a customer back.
Rule 6 cuts both ways: the ceremony of an eventually-consistent design must also be paid for, and
here it buys nothing.

*What stops it working:* volume. Somewhere between two hundred and a few thousand vehicles, and it
depends more on allocations per hour at peak than on fleet size. The signal is contention — two
allocations waiting on each other during the 9am rush.

The important part: that number is a fact about the business you have to go and ask for, and a
design that outgrows it is not a past mistake. Treating it as one is how teams learn never to choose
the simple thing.

**G2.6** They are not in tension, because *what makes deriving safe* is different from *whether
deriving is safe in general*.

Both totals are safe to derive **today**, for one reason: `Money` is immutable and every line holds
its own copy of the rate it was created with. Recomputing in five years gives the same answer,
because the inputs are frozen. The safety comes from Lesson 1.2, not from deriving.

What a real billing system worries about is the **calculation** becoming historical — a new tax
treatment, a different rounding rule. Then a derived total silently re-prices last year's invoices,
and nothing fails. At that point the computed total must be stored alongside the parts, and the
drift risk accepted knowingly.

Note what the course already does about it: `JobCardInvoiced` carries the total. The amount that was
**billed** is captured at the moment of billing, so the accounting record is a fact about a moment
even though the aggregate's own total is a fact about now. That is the compromise, and it is worth
noticing that it was made in Lesson 2.3 rather than argued about in 2.1.

</details>

---

# Gate 3 — After Module 3

## Part 1 — Module 3

**G3.1** The repository interface goes in the domain. The query service interface does not. Give the
test that decides it, and apply it to both.

**G3.2** Name the five things about a rich `JobCard` that block rebuilding one from a database row,
and say which earlier lesson each came from.

**G3.3** `JobCard::reconstitute()` records no events, and needs no flag to achieve that. Why?

**G3.4** A contract test must not assert that `get()` returns the same instance, but must assert that
lines come back in order. State the rule that produces both answers.

## Part 2 — Carried forward from Modules 1 and 2

**G3.5** `JobCardSummary` is anemic — public properties, no rules. Lesson 1.1 spent a lesson arguing
against that shape. Reconcile them.

**G3.6** Lesson 2.1 said the total is derived and never stored. Lesson 3.2's mapper stores no total
either. Name the one circumstance in which both would be wrong, and say what the course already does
about a version of it.

---

<details>
<summary><strong>Gate 3 — answer key</strong> (open only after you have answered)</summary>

**G3.1** *The test:* delete the framework and the database — does this still mean something to
somebody who runs the business?

`JobCards::awaitingAuthorisation()` survives. The service manager asks it every morning and would ask
it with a paper diary. It is a domain concept, so the interface lives in `domain/workshop/` and every
implementation lives outside it.

`JobCardSummaryQuery::openByValueDescending()` does not. Its columns are whatever the screen shows
this month and its sort order is somebody's preference; it changes when the screen changes. An
interface that changes when a screen changes is not a domain interface, so the whole thing —
read model, query, any interface over it — is infrastructure and the domain does not know it exists.

**G3.2** Private constructor (1.2's value-object discipline, applied to an aggregate). `open()`
records `JobCardOpened` (2.3). `LabourLine::create()` mints a `LineId` (2.1). Status transitions are
guarded by I9 (2.1). `readonly` lines and private state (1.1).

Each was argued for and each is still right. Together they mean the public API cannot express *"this
object already exists"* — which is the mapping problem, and it has a name: reconstitution.

**G3.3** Because `open()` records, and the **constructor** does not. `reconstitute()` builds through
the constructor, so there is nothing to suppress.

That was decided in Lesson 2.3, with the reason stated at the time — *"opening is a business event;
loading is not"* — and 3.2 is where it pays off. Had the event been recorded in the constructor, the
only way out would be a flag; and a flag on an aggregate saying "do not announce things right now" is
the sort of thing that gets left on.

**G3.4** **Assert what the interface promises; never how a class keeps the promise.**

A caller may rely on `get()` returning an **equal** job card — same identity, status, total, and
lines in the same order. A caller may not rely on the same PHP object, because nothing says so and a
database physically cannot: rows come back and a new aggregate is built.

Ordering is the reverse case. `lines()` returns a list in insertion order, which *is* part of the
promise — and an array satisfies it by accident while a table needs a `position` column. That is
exactly why it must be asserted: only one implementation gets it for free, and the failure arrives
later, from an index.

**G3.5** They are not in tension. Lesson 1.1 §7 named this case explicitly: a DTO whose only job is
to be rendered has no invariants to protect, and putting behaviour on it is the opposite error.

The distinction is whether anything is ever *decided* with the object. A `JobCard` is changed, saved
and reasoned about, so its rules must be unavoidable. A `JobCardSummary` is built from a row, shown,
and discarded — there is nothing to protect and nothing that could go wrong.

The line is watchable rather than theoretical: the moment a read model grows a method that *decides*
something — `canBeInvoiced()` is the classic — it has stopped being a read model, and the rule now
lives in two places, one of which nobody regards as authoritative.

**G3.6** Both would be wrong when **the calculation itself becomes historical** — a new tax
treatment, a different rounding rule. A derived total then silently re-prices last year's invoices,
and nothing fails. An invoice is a statement of what was charged, so that is a correctness failure
rather than a performance one.

What the course already does about a version of it: **`JobCardInvoiced` carries the total**, captured
at the moment of billing. So the accounting record is a fact about a moment even though the
aggregate's own total is a fact about now — and that compromise was made in Lesson 2.3, not argued
about in 2.1.

The remaining gap is a job card that is invoiced and then re-read for display before any calculation
change; that is fine today and would need a stored `invoiced_total` the day it is not. Note the
difference between storing a total *because reads are slow* — a cache, usually wrong — and storing
one *because it is a different fact*.

</details>

---

---

# Gate 4 — After Module 4

## Part 1 — Module 4

**G4.1** Name the **three** categories of failure, give the test that separates each from the
others, and say what the correct handling is for each. Then say which one people forget, and what
that specific mistake costs.

**G4.2** PHP's SPL exception hierarchy makes one thing impossible that this module needed. Say what,
say why it is impossible rather than merely awkward, and give the mechanism the course used instead.

**G4.3** You are reviewing a pull request that adds a fourth level to the exception tree:
`WorkshopException → InvoicingException → InvoiceException → InvoiceAlreadyIssued`. Give the test
you would apply, apply it, and say what you would ask for.

**G4.4** `IllegalStatusTransition` puts `$status->value` in `context()` and `$status->label()` in the
message. Explain the rule that produces that split, and give a concrete failure that follows from
getting it wrong.

**G4.5** `checkCanComplete()` and `complete()` are the same rules read two ways. Name the failure
mode this pattern is famous for, say why it hurts the careful caller more than the careless one, and
describe the mechanism — not the discipline — that prevents it.

**G4.6** A colleague wants `public function httpStatus(): int` on `CarriesContext`. Give the
strongest version of their argument, then the single fact that defeats it. (The fact is about a
request, not about a class.)

## Part 2 — Reaching back

**G4.7** *(Module 1)* Lesson 3.1 chose `\DomainException` for `JobCardNotFound` when there was no
tree to fit into. Module 4 re-parented that class twice and nothing broke. Say what property of the
original decision made that cheap, and describe what Module 4 would have looked like if the original
had been `\RuntimeException`.

**G4.8** *(Module 2)* `JobCard` now names seven exception classes it did not name before. Argue that
this is acceptable coupling, then give the threshold past which it stops being acceptable — and say
which Module 2 warning it would be telling you about.

**G4.9** *(Module 3)* Lesson 4.3 measures the cost of asking `checkCanComplete()` for ten thousand
job cards. Give the answer, and say why the fix belongs to Module 3 rather than to Module 4.

---

<details>
<summary><strong>Gate 4 — answer key.</strong> Answer first.</summary>

**G4.1** **(a) Expected** — the business has an answer; somebody who runs it would recognise the
failure and know what to do. Handling: a 4xx with the reason. **(b) Exceptional** — nobody has an
answer; the request was valid and the machinery fell over. Handling: a 500, a log with a stack
trace, and nothing useful to the client. **(c) Programmer error** — it should be impossible and the
fix is a code change. Handling: let it be loud.

The tests: *would Marcus recognise this as something that happens in his workshop?* Then, for the
ambiguous cases, *would retrying the identical request ever succeed?* (yes → b) and *is there a
different valid request that would work?* (yes → a; no, and no retry helps → c).

**Category (c) is the forgotten one**, and forgetting it produces a `catch (\Throwable)` at the
boundary that swallows real bugs. The cost is specific: a `TypeError` with a file and a line number
becomes "users occasionally see an error."

**G4.2** There is **no single class** you can name in a `catch` clause meaning "my domain refused
something."

The domain throws from both halves of SPL — `\InvalidArgumentException` for a bad argument,
`\DomainException` for a broken rule — and those are **siblings**. Their only common ancestor is
`\LogicException`, which also covers every `\BadFunctionCallException` in `vendor/`.

It is impossible rather than awkward because PHP gives each class exactly one inheritance slot and
SPL has already spent it. You cannot fix it by choosing a better base class.

The mechanism: an **interface**. `WorkshopFailure extends \Throwable`, implemented by both roots,
spanning a hierarchy neither can leave.

**G4.3** The test: **name the `catch` clause that would use each node, and the field it holds. If you
can do neither, you are drawing a diagram.**

Applied: `InvoicingException` and `InvoiceException` both fail. Nobody writes a clause for a node
with one child, because catching the child is strictly more precise, and with one child there is
nothing to hoist. Two nodes with one child each is a rename with extra steps, twice.

What to ask for: collapse both, so `InvoiceAlreadyIssued extends WorkshopException` — or, if invoices
have their own identity that several failures will carry, one node holding it. Add the second level
when the second class arrives; that is a two-minute change then and costs nothing now. Rule 6,
applied to a hierarchy.

**G4.4** **Machine values in the context; human labels in the message.** They serve different
audiences, and a class serving both must keep them apart.

The concrete failure: a client greys out buttons by reading `allowed`. If the context carried
labels, then translating the application — or a designer changing "Awaiting Authorisation" to
"Needs approval" — would change the values a client switches on. It would not fail loudly; the client
would compute a status that does not exist and render a dead button.

`in_progress` is not English. It is an identifier that happens to be spelled with Latin letters, and
that is why it survives translation.

**G4.5** **Drift.** The check and the operation encode the same rules separately, until somebody adds
a guard to one and not the other.

It hurts the careful caller more because that caller has an `if` where the careless one has a
`catch`. Its error handling is *thinner precisely because it checked first*, so the escaping
exception travels further and becomes a 500 for a rule the system understands perfectly. **Checking
made it worse**, which is what makes drift the failure mode of the pattern rather than a bug in it.

The mechanism: `complete()` is written as `$this->checkCanComplete()->orThrow();`. One list of
guards, two ways of reading it. A developer adding a rule has nowhere else to put it. Not a
convention, not a review rule — there is only one list.

**G4.6** Their argument: the domain already decides that a missing job card and an illegal transition
are different kinds of failure — that *is* the tree — and the boundary's table encodes the same
distinction a second time. Two places encoding one decision drift, and when they drift the domain is
right and the table is stale. It is one integer.

The fact that defeats it: **`JobCardNotFound` is a 404 from `GET /job-cards/{id}` and a 422 from
`POST /invoices` with an unknown id in the body.** Same exception, same failure code, two correct
answers — because the status is about the **request**, and the exception is raised inside a
repository that has never seen one.

Their real worry, drift, is answered by a test rather than by moving the table: walk every leaf of
the tree and assert each maps to something other than 500.

**G4.7** The original type was chosen **on the merits, for a stated reason** — 3.1's doc block argued
that a missing job card is a business-level mistake rather than a crash, and that the same exception
is right whether the repository is SQLite, an array or a filing cabinet.

Because of that, re-parenting was additive: `JobCardNotFound → JobCardException → WorkshopException →
\DomainException` still satisfies every `throws(\DomainException::class, ...)` and every
`is_subclass_of(..., DomainException::class)` written in Modules 1 to 3. `instanceof` does not care
about depth.

Had it been a `\RuntimeException` — a defensible-sounding choice, "because repositories are
infrastructure" — Module 4 would have been a **migration**: either the tree grows a second trunk
under `\RuntimeException` for one class, or forty assertions across three modules get rewritten.
Re-parenting is cheap when the first parent was right.

**G4.8** Acceptable because all seven are in `Workshop\` — no new `use` statements — and each
describes a rule this aggregate itself enforces. An aggregate naming the failures of its own
invariants is cohesion, not coupling.

The threshold is roughly where the list stops fitting on a screen; call it twenty. Past that it is
not telling you the exception design is wrong. It is telling you **the aggregate is too big** —
Lesson 2.1's warning that big aggregates are the second most common DDD failure, arriving from a
direction it did not anticipate. The size of an aggregate's exception vocabulary measures the number
of rules it enforces.

**G4.9** Roughly fifteen thousand exceptions constructed and discarded, backtraces included, to
produce one integer — because `Refusal` holds the failure it was built from, and building a refused
`Decision` builds one exception per reason.

The fix is a **read model** (Lesson 3.3): ask the database how many rows are in a blocked state and
never load an aggregate. Orders of magnitude faster, and it was the correct design before the
exceptions were an issue.

It belongs to Module 3 because the problem is not the cost of a `Refusal` — it is a **bulk question
being answered by loading aggregates one at a time**. Removing the held exception would buy
milliseconds and reintroduce drift, trading a correctness property for a performance one.

The general rule: when a domain pattern is too slow in bulk, check whether the bulk operation should
have been touching the domain.

</details>

---

*Gates for Modules 5 and 6 are written as those modules are built.*
