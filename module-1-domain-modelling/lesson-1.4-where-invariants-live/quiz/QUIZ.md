# Quiz — Lesson 1.4: Where Invariants Live
> Closed book. **20 questions. Threshold: 15.**
> The last question in Module 1, and the one most worth taking seriously.

---

## Section A — Multiple choice

**Q1.** Of the four candidate homes for a rule, only one has an empty "fails for" column. Which?

- A) The API boundary.
- B) A service.
- C) A setter.
- D) The constructor, plus the named operations that change state.

---

**Q2.** A setter cannot protect:

- A) Direct property assignment.
- B) The moment the object came into existence.
- C) Hydration from the database.
- D) All of the above.

---

**Q3.** "Tax is 20%" is:

- A) An invariant, belonging in the constructor.
- B) A policy, passed in.
- C) A sanity check.
- D) A domain event.

---

**Q4.** The test that identifies an invariant is:

- A) Does it appear in the specification?
- B) If you loaded the object straight from the database and the rule were violated, would that be
  a data-corruption incident?
- C) Is it enforced in more than one place?
- D) Does the business have a name for it?

---

**Q5.** The test that identifies a policy is:

- A) Does it involve money?
- B) Could two customers reasonably have different answers, or could it change after a meeting?
- C) Is it in a config file already?
- D) Does it have a number in it?

---

**Q6.** A sanity check with no override path:

- A) Is the safest option.
- B) Becomes a business rule by accident, and produces plausible invented data.
- C) Should be moved into the constructor.
- D) Is fine as long as it is documented.

---

**Q7.** Why is "validate everywhere" a design failure?

- A) It is slow.
- B) Copies drift, nobody knows which is authoritative, and the real rule is hidden by the noise.
- C) It duplicates code, which is always wrong.
- D) It makes testing harder.

---

**Q8.** A boundary check that duplicates a model rule is acceptable when:

- A) The boundary check is stricter, for extra safety.
- B) It exists for the error message, and deleting it would make nothing newly possible.
- C) The model check might be removed later.
- D) Never — duplication is always a failure.

---

**Q9.** "Validate first, mutate last" matters because:

- A) It is faster.
- B) An exception thrown after a partial mutation leaves a half-changed object that nothing rolls
  back.
- C) It reads better.
- D) PHP requires it for `readonly` properties.

---

**Q10.** `LabourLine::total()` is derived rather than stored. The main benefit is:

- A) It uses less memory.
- B) The invariant "the total matches the line" is unrepresentable rather than merely enforced.
- C) It is faster to compute than to load.
- D) It avoids a database column.

---

## Section B — True / False

| # | Statement | Answer |
|---|-----------|--------|
| 11 | A rule enforced in the constructor holds for code that has not been written yet. | |
| 12 | `LabourUnits::ofTenths(3)` should be accepted, because it is just a small line. | |
| 13 | Some rules have no correct home in Module 1, and saying so is better than forcing them onto an object. | |
| 14 | A policy hard-coded as a class constant is a bug with a commencement date. | |
| 15 | The number of checks should go up when you relocate scattered validation properly. | |

---

## Section C — Short answer

**Q16.** The five-tenth minimum is implemented as an invariant of `LabourUnits`. Argue that it is a
policy, then say what you would actually do.

*Your answer:*

---

**Q17.** `LabourUnits::ofTenths()` refuses values below five, even though it exists to rebuild
objects from storage. Explain why that is right, and name the risk it creates.

*Your answer:*

---

**Q18.** `LabourLine::total()` is derived. A rate changes next year. What happens to every
historical line's total? Is that what the business wants? If not, what changes — and what does that
change turn the total back into?

*Your answer:*

---

**Q19.** Take one rule from a system you know. Say which of the three kinds it is, where it
currently lives, where it should live, and what specifically would go wrong if it stayed put.

*Your answer:*

---

**Q20.** Module 1 is finished. `domain/workshop/` holds nine classes and none of them can enforce
invariant I10 — "a courtesy vehicle is on at most one open job card at any time". Explain precisely
why not, and what kind of thing would have to exist for it to be enforceable.

*Your answer:*

---
---

<details>
<summary><strong>Answer key</strong></summary>

**Q1 — D.** The constructor, plus named operations for state that changes. There is no way to obtain
the object without going through construction, and no way to change the guarded state without going
through the operation.

**Q2 — D.** All of them. The most interesting is B: an object that has never been through the setter
is perfectly constructible, and it is wrong from birth.

**Q3 — B.** A policy. It is set by a government, with a commencement date, and it will change.

**Q4 — B.** The data-corruption test. A is the trap — a rule appearing in a specification tells you
somebody wrote it down, not that it can never be otherwise.

**Q5 — B.** Could two customers reasonably differ, or could a meeting change it?

**Q6 — B.** It becomes a business rule by accident. And it is worse than a hard-coded policy, because
a policy produces a visible complaint whereas this produces plausible data: the adviser types
999,999 and everyone downstream believes it.

**Q7 — B.** All three, and the third is the one that compounds — a reader who cannot tell which check
is the rule adds a fifth. (C is too strong: duplication for presentation is fine, see Q8.)

**Q8 — B.** For the message, and only if deleting it makes nothing newly possible. A *stricter*
boundary check is not presentation — it is a second rule in disguise, and a console command calling
the model will get behaviour the API would have refused.

**Q9 — B.** No rollback for an object. The exception propagates, the caller handles it, and a
half-mutated object stays in memory and gets saved.

**Q10 — B.** Unrepresentable beats enforced. There is no state in which the total disagrees with the
parts, so no guard is needed and none can be forgotten.

**Q11 — TRUE.** That is the whole argument for the constructor.

**Q12 — FALSE.** Five tenths is the minimum a line can bill, so a stored 3 is corrupt data. Accepting
it means the model trusts the database more than it trusts itself — and Lesson 3.2 is largely about
why that is the wrong way round.

**Q13 — TRUE.** I10 is exactly this. A rule forced onto an object that cannot see enough to enforce
it produces a check that looks like protection and is not, which is worse than no check because
somebody will rely on it.

**Q14 — TRUE.** Nobody finds it until the day it changes, and then the change is a deployment rather
than a setting.

**Q15 — FALSE.** It goes down. Example 03 goes from nine checks in four places to three in three, and
the count falls because the duplicates were never adding safety.

**Q16** *The case for policy:* it is a commercial decision, it differs between workshops, a fleet
contract might waive it, and somebody chose the number in a meeting. By this lesson's own test —
could two customers reasonably have different answers — the answer is plainly yes.

*What I would actually do:* ask. The design change is one parameter,
`fromMinutes(int $minutes, int $minimumTenths)`, so the cost of getting it wrong is small and the
cost of asking is a two-minute conversation. What decides it is not which design is prettier but
which sentence the service manager agrees with: *"we always bill at least half an hour"* or *"we
usually bill at least half an hour"*. The word *usually* is the tell, and it is a word you can only
get by asking.

*What I would not do:* leave it as an invariant because a document said so. That is exactly how
policies get welded into domain models, and the reference implementation says so out loud rather
than tidying it away.

**Q17** It is right because a value below the minimum is not a small line — it is data that the
model's own rules say cannot exist. Silently accepting it would mean reconstitution is a way around
the invariants, and then holding a `LabourUnits` would no longer guarantee anything, which is the
entire value of the type.

*The risk it creates:* a legitimate old row, written before the minimum existed or under a rule that
has since changed, now cannot be loaded at all. That is a migration problem that will appear as a
fatal error in production on a page nobody expected to break. The honest handling is to migrate the
data or to version the rule — not to weaken the guard, which trades a loud failure for a silent one.

**Q18** Every historical total changes, retroactively, because the total is computed from the rate
the line currently holds. If the rate object is shared or re-read from configuration, last year's
invoices re-price themselves.

Almost certainly not what the business wants: an invoice is a statement of what was charged, and it
must not change afterwards. What changes is that the rate must be **captured on the line at the
moment it is created** — which the reference implementation already does, because `Money` is
immutable and the line holds its own copy. So the derived total is safe here *because of Lesson
1.2*, which is worth noticing.

Where it stops being safe is when the total must survive a change to how the total is *calculated* —
a new tax treatment, a different rounding rule. Then the calculation itself is historical, and the
answer is to store the computed total alongside the parts. That turns a derived value back into a
stored one, with all the drift risk that brings, and accepting that risk knowingly is Lesson 2.1's
subject.

**Q19** No key. Two things to check in your own answer: did you name the *specific event* that would
reveal the problem, and did you resist reclassifying a policy as an invariant because it happens to
be stable right now? Stable is not the same as necessary.

**Q20** No object in `domain/workshop/` can see more than itself. `CourtesyVehicle` knows whether
*it* is allocated, so it refuses a second allocation on the same instance — but load the same vehicle
twice from storage, allocate each copy to a different job card, and save both, and every individual
object obeyed its own rule while the system ended up wrong. I10 is a statement about *the whole set
of open job cards at one instant*, and nothing in Module 1 holds that set.

What would have to exist: something that can see all of them, and a decision about *when* the rule
must hold. Either a transactional boundary wide enough to cover both aggregates at once — which
makes the aggregate too large, and Lesson 2.1 is about why that fails — or an acceptance that the
rule holds eventually rather than instantaneously, with a compensating action when it is violated.

The second is usually right, and it is not a technical decision. Somebody in the workshop has to say
whether "two people booked the same courtesy car for ten seconds and then one got a phone call" is
acceptable. That conversation is Lesson 2.2.

</details>

---

**Score:** ____ / 20   ·   **Threshold: 15**

Then take **Gate 1** in `GATES.md`, with every file shut. That is Module 1 finished.
