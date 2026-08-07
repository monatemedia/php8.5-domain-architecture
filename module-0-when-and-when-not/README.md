# Module 0 — When, and When Not, to Do This
> Advanced Domain Architecture & Tactical DDD

| | |
|---|---|
| **Lessons** | 2 |
| **Challenges** | 2 — both written arguments, no code |
| **Time** | ~90 minutes, of which about 30 is reading |
| **Needs PHP** | 8.5+ to run the examples. Nothing else. |

---

## Why this module is first

Every other module in this course teaches you a pattern. This one teaches you to *not use* the
patterns, and it comes first because the order matters.

A course that opens with aggregates produces developers who reach for aggregates. Six months later
they have wrapped four layers of indirection around a settings page, their team has quietly decided
that "architecture" means "more files", and the next person to suggest a domain layer will be
overruled by people who have seen one go badly.

The judgement of *when not to* is the more valuable half of this subject, and it is the half almost
every DDD course omits — partly because it is harder to teach and mostly because it undercuts the
sale.

So: two lessons, no code to write, and a challenge that asks you to argue rather than to implement.
It will feel like the least substantial module in the course. It is the one you will use most.

---

## What you will be able to do afterwards

- Given a system brief, say whether tactical DDD, a transaction script, or plain Active Record is
  the right answer — and defend it in two sentences to someone who disagrees.
- Name what the ceremony costs, who pays it, and when the bill arrives.
- Spot the two failure modes that account for most unhappy DDD projects: the shared model, and the
  oversized aggregate. Both are visible in the language people use before they are visible in code.
- Explain why the vocabulary matters more than the patterns, and why a model that uses your words
  instead of the business's cannot be corrected.

---

## The lessons

### [0.1 — The Cost of DDD, and How to Price It](lesson-0.1-the-cost-of-ddd/) ⭐

What the ceremony actually costs, the signals that justify it, the signals that argue against, and
a decision procedure you can run in ten minutes with a whiteboard.

**Challenge:** four system briefs. Decide for each, and defend each answer in two sentences.

### [0.2 — Ubiquitous Language and Bounded Contexts](lesson-0.2-ubiquitous-language/)

Why the patterns are meaningless without the language. Bounded contexts: the same word meaning
different things in different parts of the business, and why one giant shared model is the most
common way a DDD project fails.

Kept deliberately short. This is the strategic minimum needed for the tactical work, not a full
treatment of strategic DDD — that is a book, not a lesson.

**Challenge:** a transcript of a real-sounding conversation. Build the glossary and find the
context boundary.

---

## How Module 0's challenges are judged

They are not, by a script. Both are written arguments, and a script cannot mark an argument.

Each `CHALLENGE.md` ends with a checkbox you tick yourself. `check.php` reads that box and nothing
else. Tick it honestly — the only person it reports to is you, and a Module 0 you skimmed will show
up as a Module 2 you cannot do.

If you want a harder version of both challenges: write your answers, put them away for two days,
then read them again before ticking. Almost everyone finds at least one answer they now disagree
with, and finding it is the exercise.

---

## Where this module shows up later

| Later lesson | How Module 0 is used |
|--------------|---------------------|
| 1.2 Value objects | "Should this be a value object?" is the cost question at the smallest possible scale. |
| 2.1 Aggregate boundaries | The oversized-aggregate failure is diagnosed here first, in words. |
| 3.2 The mapping problem | The mapping layer is the single largest cost item in Lesson 0.1's ledger. This is where you pay it. |
| 5.1 When the framework owns your models | The whole lesson is Rule 6 applied to code you are not allowed to rewrite. |

---

*Start with [Lesson 0.1](lesson-0.1-the-cost-of-ddd/README.md).*
