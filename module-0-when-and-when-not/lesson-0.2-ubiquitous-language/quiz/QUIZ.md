# Quiz — Lesson 0.2: Ubiquitous Language and Bounded Contexts
> Closed book. Write your answers before opening the key at the bottom.
> **18 questions. Threshold: 14.**

---

## Section A — Multiple choice

**Q1.** "Ubiquitous language" requires:

- A) Business-friendly names on public methods.
- B) The same words in the code, tests, tickets, conversations and column names, with no translation
  anywhere.
- C) A glossary maintained by the product owner.
- D) Avoiding technical terms in the domain layer.

---

**Q2.** The workshop says "job card". You think "work order" is clearer. What do you do?

- A) Use "work order" — clarity wins.
- B) Use "job card" — their word is the one they will use when reporting a bug.
- C) Use "work order" internally and "job card" in the UI.
- D) Ask them to adopt "work order".

---

**Q3.** A bounded context is best described as:

- A) A module or package boundary.
- B) A boundary inside which one set of words has one agreed meaning.
- C) A microservice.
- D) The set of tables owned by one team.

---

**Q4.** Three departments each have a `Vehicle` class with different fields. This is:

- A) Duplication that should be resolved with a shared base class.
- B) Duplication that should be resolved with a shared package.
- C) Not duplication — three concepts that share a spelling.
- D) A sign the domain has not been analysed properly.

---

**Q5.** In the shared-model failure, which step causes the irreversible damage?

- A) The step where the three models are unified.
- B) The step where fields become nullable because some contexts do not need them.
- C) The step where a fourth model is added alongside.
- D) The step where three teams must agree to any change.

---

**Q6.** The reliable early tell of a shared-model failure is:

- A) A class with more than twenty methods.
- B) A class with many nullable fields, where which are populated depends on where the object came from.
- C) A class in a `Shared\` namespace.
- D) Circular dependencies between packages.

---

**Q7.** During a conversation, a domain expert says *"well, it depends"*. This most often means:

- A) They are unsure of the rule.
- B) The rule has an exception you should hard-code.
- C) You have found one word covering two concepts.
- D) The rule is a policy rather than an invariant.

---

**Q8.** A domain expert says *"that's just how the system does it"*. This most often means:

- A) The rule is important and undocumented.
- B) The word came from software, not from the business, and may have no business meaning.
- C) They disagree with the rule.
- D) The rule is a workaround for a bug.

---

**Q9.** An anticorruption layer is:

- A) A validation layer at the API boundary.
- B) A class at the edge whose only job is to translate their model into yours.
- C) A set of tests that guard against regressions.
- D) A permissions system.

---

**Q10.** Which is the *worst* source of domain vocabulary?

- A) A recorded conversation with someone who does the work.
- B) A requirements document.
- C) The existing database column names.
- D) A glossary the business has corrected.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 11 | If the business changes a word, the code should be renamed to match. | |
| 12 | Two contexts having a class with the same name in different namespaces is a design smell. | |
| 13 | One glossary per company is the goal; per-context glossaries are a compromise. | |
| 14 | A word your code uses that the business has never said is always a mistake. | |

---

## Section C — Short answer

**Q15.** In two sentences: why is "the code can be read back to the business" a *functional*
property rather than an aesthetic one?

*Your answer:*

---

**Q16.** `Accounts\Vehicle` in Example 02 has no odometer. Give the practical benefit of that
absence, in terms of something that cannot happen.

*Your answer:*

---

**Q17.** A colleague proposes a `Shared\Vehicle` package to remove duplication between three
contexts. Give the strongest argument *in favour* of their proposal before you argue against it.
Then say what you would propose instead.

*Your answer:*

---

**Q18.** Your codebase says `Customer`. The business says "account holder" and, separately,
"driver". Describe the specific sequence by which this costs you money — not "it's confusing", an
actual chain of events ending in work you have to do.

*Your answer:*

---
---

<details>
<summary><strong>Answer key</strong> — open only after you have written every answer</summary>

**Q1 — B.** The demand is stricter than "friendly names". Any place where a translation survives —
a column name, a ticket title, an internal abbreviation — is a place where the two vocabularies can
drift apart without anyone noticing.

**Q2 — B.** Their word is the one that appears in bug reports, in meetings, and in the sentence
somebody says when the system is wrong. Consistency beats elegance. C is the worst option: it
guarantees a permanent translation layer, and it puts the boundary somewhere nobody can see it.

**Q3 — B.** A boundary of *meaning*, not of deployment or ownership. It frequently coincides with a
module or a service, but that is a consequence, not the definition — and treating it as a
deployment concern is how teams end up with microservices sharing one god model.

**Q4 — C.** They share a spelling, not a concept. Each department's model is complete and correct
for what that department does, and unifying them means a class carrying every field any of them
needs.

**Q5 — B.** Nullability. Once a field can be null because some context does not populate it, no
invariant can be enforced on it — the guard has to accept the null case or the other context
breaks. Everything after that step is consequence. The unification itself (A) breaks nothing on the
day it happens, which is precisely why it passes review.

**Q6 — B.** Many nullable fields, populated differently depending on origin. That is several models
in one class, and it is visible long before anything fails.

**Q7 — C.** "It depends" is the sound of one word covering two concepts. Ask what it depends on;
the answer is the distinction you were missing. (D is a reasonable second answer — sometimes it
does signal a policy — but the more common and more valuable reading is the vocabulary one.)

**Q8 — B.** It signals a word that came from software rather than from the business. These are the
safe ones to change, and frequently the ones worth deleting: a concept nobody in the business has a
name for is often a concept the business does not have.

**Q9 — B.** A translator at the edge. The concrete test: after you have one, the upstream system's
field names should appear in exactly one file.

**Q10 — B.** A requirements document has already been translated once, by someone who is usually
not available to ask. Database column names (C) are also poor, but at least they are honest about
being the system's words rather than the business's. A is best; D is nearly as good.

**Q11 — TRUE.** A model whose vocabulary is six months behind the business has grown a translation
layer, and translation layers are where distinctions get lost. The rename is real work and it is
worth doing.

**Q12 — FALSE.** It is the correct outcome. Three contexts with three `Vehicle` classes in three
namespaces is a boundary drawn properly. The smell would be *one* `Vehicle` serving all three.

**Q13 — FALSE.** Exactly backwards. Per-context glossaries are the goal, plus explicit translations
between them. One company-wide glossary either forces a false consensus or, more often, quietly
documents whichever department wrote it first.

**Q14 — FALSE.** Some concepts are genuinely technical — a `Clock`, a `Mapper`, a
`ConnectionPool` — and inventing business names for them would be worse. The red flag is a word
with no business meaning attached to something that clearly does have one:
`JobCardManager`, `VehicleHelper`, `OrderInfo`. The test is not "did they say it?" but "is this a
container for logic that belongs on something with a real name?"

**Q15** Because being correctable is a function of being readable. If the domain expert can read a
method signature and say "no, the driver can't authorise that", the error is caught in a meeting at
a cost of five minutes. If they cannot, the same error is caught by a customer, at a cost that
includes the incident, the fix, the data repair and the conversation about why nobody spotted it.
Readability determines who finds your modelling errors and when, which is about as functional as a
property gets.

**Q16** An odometer reading cannot leak into accounting logic, because there is nothing there to
read. Concretely: no future developer can write an accounts feature that branches on mileage,
because the attempt does not compile — it is a call to an undefined method, not a runtime check
someone might disable. A model with no way to express a thing cannot do that thing by accident,
which is a stronger guarantee than any amount of validation.

**Q17** *The steelman:* the three classes will drift. A field added to one for a reason that
applies to all three will be added to one, and six months later there are three subtly different
notions of what a valid registration looks like. Shared code is shared knowledge, and the proposal
is aimed at a real failure mode — this is the same instinct that makes DRY good advice, and it is
correct far more often than it is wrong.

*What to propose instead:* separate the two things being conflated. Registration-number *format* is
genuinely shared and belongs in a small shared kernel — a value object with no dependencies, frozen
by agreement. The *Vehicle* concept is not shared, because each context needs different things and
the differences are what each context is for. So: share the value object, keep three models,
translate at the boundary by registration. That gives the colleague the anti-drift guarantee they
actually wanted without the god object they would have got.

**Q18** The chain: the service manager reports that "an account holder shouldn't be able to have
two courtesy vehicles out at once". You search for `AccountHolder`, find nothing, and file the
report as unclear. Two weeks later you discover `Customer` and realise it is the same thing, so you
implement the rule on `Customer`. It works, until a fleet job where the driver — a different person
— also has a courtesy vehicle out under the same account, and the rule fires when it should not.
Investigating, you find the business has always distinguished account holder from driver, and your
single `Customer` collapsed both. Now every billing rule and every handover rule sit on one class
and reference one identifier, and separating them is a data migration with backfill, not a rename.
The cost is the migration, the incident, and the two weeks at the start, all of which were bought by
one word.

</details>

---

**Score:** ____ / 18   ·   **Threshold: 14**

Then take **Gate 0** in `GATES.md`, with every file shut. Only after that, start Module 1.
