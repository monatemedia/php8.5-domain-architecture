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

*Gates for Modules 2 onwards are written as those modules are built.*
