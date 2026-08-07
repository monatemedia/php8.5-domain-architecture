# Challenge — Lesson 0.1: Four Briefs, Four Decisions

> **No code.** This challenge is a written argument, and that is the point.

---

## The brief

Below are four systems. For each one, decide between:

- **Active Record** — the ORM model is the domain object
- **Transaction script** — one procedure per use case, over fairly dumb data
- **Tactical DDD** — rules in objects that refuse to break them
- **A mix** — and if so, say exactly which part gets which, and where the line is

Then **defend your answer in two sentences**. Not three. The constraint is deliberate: if you
cannot state the reason in two sentences, you have not located it yet.

Write your answers in a file of your own — `challenge/my-answers.md` is ignored by git, or use a
notebook. There is nothing to run.

---

## Brief 1 — Conference talk submissions

A site where speakers submit talk proposals for a conference.

A proposal has a title, abstract, duration and track. Speakers may edit until the deadline, after
which proposals lock. Reviewers score each proposal 1–5 and leave comments. Reviewers cannot see
each other's scores until they have submitted their own. Once review closes, the organisers pick
the accepted talks by hand and the system emails everyone.

There are 400 proposals a year, 12 reviewers, and one conference. The site is rebuilt every couple
of years by whoever volunteers.

---

## Brief 2 — Prescription dispensing

A pharmacy system that records dispensing of prescription medicines.

A prescription has a patient, a prescriber, one or more items, and a validity period. An item
cannot be dispensed after the prescription expires. Controlled drugs have a shorter validity than
ordinary ones and require a second pharmacist's signature. Partial dispensing is allowed: three of
six months' supply now, the rest later, but the total across all dispensings may never exceed what
was prescribed. Every dispensing event is retained for inspection and cannot be edited, only
superseded by a correction record that references it.

The pharmacy is inspected. Getting this wrong is a regulatory matter.

---

## Brief 3 — Internal link shortener

A tool that turns long internal URLs into short ones.

Paste a URL, get back `go/something`. The short code may be auto-generated or chosen. Codes are
unique. You can see how many times each was clicked. Anyone in the company can create one; only the
creator or an admin can delete one.

Roughly 30 new links a week. Two engineers maintain it in their spare time.

---

## Brief 4 — Freight consolidation

A system that combines customer shipments into containers.

Each shipment has a weight, a volume, an origin, a destination and a ready date. A container has a
weight limit, a volume limit and a sailing date. A shipment can only go in a container whose
sailing date is on or after its ready date, and whose route serves its destination. Some goods
cannot travel with others — a compatibility matrix maintained by the compliance team. Once a
container is sealed, its contents are fixed and its manifest is filed with customs. A shipment
removed from a sealed container requires the container to be reopened, which is a separate,
audited operation.

Customers are billed by chargeable weight, which is the greater of actual weight and volumetric
weight, where the volumetric divisor differs by trade lane and is renegotiated annually.

---

## What a good answer looks like

Not "tactical DDD, because there are complex business rules". That sentence fits all four briefs
and distinguishes nothing.

A good answer:

- **Names the specific rule** that carries the decision — "a shipment removed from a sealed
  container requires an audited reopen" rather than "there are rules"
- **Says what would go wrong** with the option you rejected, in terms of an actual event
- **Scopes it** — most of these are not one answer for the whole system
- **Is willing to say Active Record** where Active Record is right. Two of these four are not
  close, and an answer that recommends a domain layer for all four has misread the lesson

---

## Then, the harder half

Answer these three afterwards. They are worth more than the four briefs.

**Q1.** For the brief where you chose tactical DDD most confidently, name the **one thing** that
would change your mind. If nothing would, you are not making a decision, you are stating a
preference.

**Q2.** For the brief where you chose Active Record most confidently, describe the specific event —
a feature request, a bug report, a new regulation — that would make you regret it. Then say roughly
how long you would expect to wait for it.

**Q3.** Brief 3 is a link shortener maintained by two engineers in their spare time. Suppose your
CTO has just returned from a conference and mandates a domain layer for all new services. Write the
two sentences you would say in that meeting. This is a real skill and it is not a technical one.

---

## Self-assessment

Before ticking the box, check your answers against these. There is no key — a key would let you
recognise a good answer without producing one.

- [ ] Every answer names a specific rule, not a category of rules
- [ ] At least one answer is Active Record, unqualified, with no apology attached
- [ ] At least one answer is a mix, with the line drawn explicitly
- [ ] No answer is longer than two sentences
- [ ] Q1 names something that would genuinely change your mind
- [ ] You could say any of these out loud to a colleague who disagreed

**Optional, and recommended:** put the answers away for two days and read them again before
ticking. Most people find one they now disagree with. Finding it is the exercise.

---

## Sanity check on the briefs themselves

Two of these four are clear-cut and two are arguable. If you found all four clear-cut, re-read the
two you found easiest — the difficulty is there, and not seeing it is the thing to notice.

If you want to check your instinct against the scorer, run
`examples/03-the-decision-procedure.php` and add these four to the `$systems` array. Do it *after*
you have written your answers, not before, and treat a disagreement as a question rather than a
correction.

---

- [ ] **Lesson 0.1 complete.** I have written and reviewed my four decisions and the three
      follow-up questions.
