# Lesson 0.2 — Ubiquitous Language and Bounded Contexts
> **Module 0: When, and When Not, to Do This** · Advanced Domain Architecture

| | |
|---|---|
| **Time** | ~20 min reading · ~15 min running the examples. Challenge on top. |
| **Comes after** | Lesson 0.1 — The Cost of DDD |
| **Covers** | The translation tax · Building a glossary · Bounded contexts · The shared-model failure · Context maps |
| **Needs PHP** | 8.5+ |

---

## 📁 Lesson folder

```
lesson-0.2-ubiquitous-language/
├── README.md                            ← you are here
├── examples/
│   ├── 01-the-translation-tax.php       ← the same method, two vocabularies
│   ├── 02-one-word-three-contexts.php   ← "vehicle" to three departments
│   └── 03-the-shared-model-failure.php  ← how a god object accumulates, and breaks
├── challenge/
│   └── CHALLENGE.md                     ← a transcript. Find the glossary and the boundary.
└── quiz/
    └── QUIZ.md
```

---

## 1 — Why this comes before any pattern

Everything in Modules 1 to 5 is a technique for putting a business rule somewhere it cannot be
broken. None of it helps if you have put the *wrong rule* somewhere it cannot be broken, and the
most common way that happens is that you and the business were using the same word to mean
different things and neither of you noticed.

> **The pattern is the answer. The language is the question.**
> Getting a beautifully modelled aggregate around a concept the business does not actually have is
> the most expensive way to be wrong in this entire subject.

This lesson is deliberately short. Strategic DDD — context mapping, distillation, core versus
supporting subdomains — is a book, and most of it does not change what you type. What you need
before Module 1 is the minimum: how to get the words right, and how to notice when one word is
doing two jobs.

---

## 2 — The translation tax

Every time your code uses a different word from the business, you have created a translation layer
that exists only in someone's head. It is paid on every conversation, in both directions, and
translations lose things.

```php
// The workshop says: "book in a vehicle"
$service->createJobCardRecord($vehicleId, $customerId);     // ❌ nobody says this

// The workshop says: "the account holder authorised the extra work"
$jobCard->setStatus(3);                                     // ❌ meaningless to everyone
$jobCard->update(['auth_flag' => true]);                    // ❌ worse — invented vocabulary

$jobCard->authoriseAdditionalWork($quote);                  // ✅ the actual phrase
```

The cost is not aesthetic. Consider the sequence:

1. The workshop manager reports: *"an account holder shouldn't be able to have two courtesy
   vehicles out at once."*
2. You search the codebase for `AccountHolder`. Nothing. You conclude the report is confused, or
   about a different system.
3. Two weeks later you find `Customer` and realise it is the same thing.
4. Then you discover the business distinguishes the **account holder** (who pays) from the
   **driver** (who brings the vehicle in) — and they are frequently not the same person, because
   half the work is on company fleets.
5. Your single `Customer` class silently collapsed that distinction. Every billing rule and every
   vehicle-handover rule now live on the same class, and separating them is a data migration rather
   than a rename.

**The translation layer did not merely slow the conversation down. It hid a modelling error for
months, and the error was in the direction of "we have one concept where the business has two",
which is the expensive direction.**

---

## 3 — What "ubiquitous" actually demands

Not "use business-friendly names". Something stricter:

> **The same words, in the code, in the tests, in the tickets, in the meeting, in the database
> column names, with no translation anywhere.**

Three practical consequences that people resist:

**You adopt their word even when yours is better.** If the workshop says "job card" and you think
"work order" is clearer, you lose. Their word is the one they will use when reporting a bug, and
consistency beats elegance. The exception is when the business itself is inconsistent — see the
next section.

**You change the code when they change the word.** If "additional work" becomes "supplementary
work" after a process review, that is a rename across the codebase, and it is worth doing. A model
whose vocabulary is six months behind the business is drifting back towards a translation layer.

**A word the business does not have is a red flag.** `JobCardManager`, `VehicleHelper`,
`JobCardData`, `OrderInfo`, `PaymentProcessorService` — nobody in the workshop has ever said any of
these. Sometimes the concept is genuinely technical and that is fine. Frequently it means you have
invented a container for logic that belongs on something with a real name.

---

## 4 — Building the glossary

The practical technique, and it takes an afternoon.

1. **Record or transcribe a real conversation** with someone who does the work. Not a requirements
   document — those are already translated. A conversation.
2. **Underline every noun and verb** that seems to carry weight.
3. **For each one, ask: is this one thing or two?** This is where the value is. "Vehicle" is
   usually two or three things.
4. **For each one, ask: does the business ever use two words for it?** If so, find out whether the
   two words mean the same thing. Usually they do not, and the difference is a rule you had not
   heard about.
5. **Write it down, and show it to them.** A glossary they have corrected is worth ten you wrote
   alone.

Two tells worth listening for specifically:

> **"Well, it depends"** — you have found a concept that is two concepts. Ask what it depends on;
> the answer is the distinction.

> **"That's just how the system does it"** — the opposite. You have found a word that came from
> software, not from the business, and it may have no business meaning at all. Those are the safe
> ones to change.

`DOMAIN.md` contains the glossary for this course's workshop. Read it as a worked output of this
process — including the entry that says the business says *account holder* and never *customer*.

---

## 5 — Bounded contexts

Here is the thing that makes a single glossary impossible, and it is not a complication to be
engineered away — it is how businesses actually work.

**The same word genuinely means different things in different parts of a business.**

| To the workshop, a **vehicle** is… | To accounts, a **vehicle** is… | To the parts desk, a **vehicle** is… |
|---|---|---|
| a thing with a service history | a registration string on an invoice | a compatibility key |
| an odometer reading | nothing else | engine code, model year, trim |
| a set of open faults | | a list of superseded part numbers |
| a bay it is currently sitting in | | |

Nobody is wrong. Each department's definition is complete and correct *for what they do*.

A **bounded context** is a boundary inside which one set of words has one agreed meaning. Outside
it, the same spelling is a different word.

```
┌─────────────────────┐   ┌────────────────────┐   ┌─────────────────────┐
│  Workshop           │   │  Accounts          │   │  Parts              │
│                     │   │                    │   │                     │
│  Vehicle            │   │  Vehicle           │   │  Vehicle            │
│   - serviceHistory  │   │   - registration   │   │   - engineCode      │
│   - odometer        │   │                    │   │   - modelYear       │
│   - openFaults      │   │  (that is all it   │   │   - trim            │
│   - currentBay      │   │   needs)           │   │   - fitmentList     │
└─────────────────────┘   └────────────────────┘   └─────────────────────┘
          │                        │                         │
          └──────── translated at the edges by ──────────────┘
                    a registration number
```

Three models. Three classes with the same name in three namespaces. Translated at the boundary,
usually by an identifier both sides agree on.

**This feels wrong the first time and it is correct.** The instinct that says "we should have one
Vehicle class, this is duplication" is the instinct that produces the failure in the next section.

---

## 6 — The shared-model failure

The most common way a DDD project goes wrong, ahead of oversized aggregates and well ahead of
anything technical.

It proceeds like this:

1. Someone notices that Workshop, Accounts and Parts all have a `Vehicle`.
2. Duplication is bad, so they are unified into one `Vehicle` in a shared package.
3. The shared class now needs every field any context needs. It has eleven.
4. Accounts needs a vehicle with only a registration — the other nine fields are nullable now.
5. Because the fields are nullable, no invariant can be enforced on any of them. `$vehicle->odometer`
   might be `null` and every consumer must handle it.
6. Parts adds `fitmentList`. The Workshop test suite breaks, for reasons no Workshop developer
   understands.
7. Any change to `Vehicle` now requires agreement from three teams, so changes stop happening.
8. Someone adds `VehicleForAccounts` alongside it, and now there are four models with no boundary
   between them at all.

**The tell, arriving early:** a class with many nullable fields, where which ones are populated
depends on where the object came from. That is three models in a trenchcoat, and step 5 is where
you lost the ability to enforce anything — which means every pattern in Modules 1 to 4 has been
disabled before you started.

`examples/03-the-shared-model-failure.php` runs this whole sequence and prints the field count and
the nullable count at each step.

---

## 7 — Context maps, briefly

When two contexts must talk, the relationship between them has a shape. You do not need the full
taxonomy, but you should recognise these three, because the choice is usually made by accident:

| Shape | What it means | When it is right |
|-------|--------------|------------------|
| **Shared kernel** | Two contexts share a small, deliberately frozen set of concepts | Rare. Only when both teams genuinely own it jointly and the set really is small |
| **Anticorruption layer** | You translate their model into yours at the boundary, and their vocabulary never enters your code | Almost always, when consuming something you do not control |
| **Conformist** | You adopt their model wholesale rather than translating | When the upstream model is good enough and translating is not worth the cost |

The one to remember is the **anticorruption layer**, because it is the one you will need and it has
a concrete meaning: a class at the edge whose only job is to turn *their* words into *yours*. When
you integrate a vendor API, the vendor's field names should not appear anywhere but in that class.

Lesson 3.1 builds one — the repository interface is, among other things, an anticorruption layer
between your domain and the database's vocabulary.

---

## 8 — Common mistakes

| Mistake | Why it is wrong | Instead |
|---------|----------------|---------|
| Unifying two contexts' models to remove "duplication" | It is not duplication; the two things merely share a spelling | Two models, translated at the edge |
| Using a requirements document as the source of vocabulary | It has already been translated once, by someone who is not available to ask | Talk to someone who does the work |
| Adopting a technical word because the business word is vague | Vague usually means it is two concepts. The vagueness is information | Ask what it depends on |
| Renaming the code but not the database columns | The translation layer survives, and it will be found by the next person during an incident | Rename both, or accept that the mapper is where translation lives and document it |
| Treating the glossary as a deliverable | It is a conversation artefact and it goes stale in weeks | Revisit it when the words change |
| One glossary for the whole company | See section 5 | One per context, plus the translations between them |

---

## 9 — Where else this shows up

The same word doing two jobs is not specific to DDD:

- **"User"** in almost every system means both *account* and *person*, and the day someone needs
  two accounts is the day you find out.
- **"Order"** means the customer's request and the fulfilment instruction, which have different
  lifecycles and different owners.
- **"Booking"** in travel means the reservation, the payment, and the ticket, and all three change
  independently.

If you learn to hear "well, it depends" as *this is two concepts*, you will get more out of that
one habit than out of any pattern in this course.

---

## ✅ Lesson checklist

- [ ] Read this README fully — especially sections 5 and 6
- [ ] Run and study `examples/01-the-translation-tax.php`
- [ ] Run and study `examples/02-one-word-three-contexts.php`
- [ ] Run and study `examples/03-the-shared-model-failure.php`
- [ ] Re-read the glossary in `DOMAIN.md` as an output of section 4's process
- [ ] Complete `challenge/CHALLENGE.md`
- [ ] Take `quiz/QUIZ.md` closed-book
- [ ] Take **Gate 0** in `GATES.md` with every file shut

---

*Next: **Module 1 — Domain Modelling**. You start writing `domain/workshop/` there, and it does not
stop growing until the capstone.*
