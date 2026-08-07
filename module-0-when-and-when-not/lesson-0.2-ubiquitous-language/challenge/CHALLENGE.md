# Challenge — Lesson 0.2: The Transcript

> **No code.** Read a conversation, build a glossary, find the boundary.

---

## The brief

Below is a transcript of two conversations at the same business, on the same afternoon. Nobody in
either conversation is confused. Everybody is using their own words correctly.

Your job:

1. **Build the glossary** — every business term, with the definition the speaker is using.
2. **Find every word that is doing two jobs** — the same word meaning two different things, whether
   between the two speakers or within one of them.
3. **Draw the context boundary** — how many bounded contexts are in evidence, and what is the
   translation between them?
4. **Name the three modelling errors** you would most likely make if you built this system straight
   from the transcript without doing steps 1–3.

Write it up in your own file. `challenge/my-glossary.md` is as good a place as any. Nothing to run.

---

## Conversation 1 — with Priya, the service manager

> **Priya:** So a customer rings up, we book them in for a slot. That's the booking. Then on the
> day they turn up and we book the vehicle in, which is different — that's when the job card gets
> opened.
>
> **You:** Same customer both times?
>
> **Priya:** Usually. Not always. About a third of our work is fleet, so the account is Northgate
> Couriers or whoever, and the driver who actually brings it in is just whoever's on shift. We need
> both — the account gets the invoice, the driver signs the handover and gets rung when it's ready.
>
> **You:** And the job card?
>
> **Priya:** That's the whole visit. Everything we do to that vehicle that day. Inside it you've got
> the individual jobs — brake pads, service, MOT, whatever. Each job has its labour and its parts.
>
> **You:** So "job" is a line on the job card.
>
> **Priya:** Right. Though we do say "that's a two-hour job" meaning the whole thing sometimes.
> Depends.
>
> **You:** What happens if you find something extra?
>
> **Priya:** Then it's additional work and we have to get authorisation. Ring the account holder —
> not the driver, the driver can't authorise anything — and they say yes or no. Under fifty we don't
> bother, we just do it. That's a rule Marcus set, he changes it about once a year.
>
> **You:** And when it's finished?
>
> **Priya:** We close it. Then it goes to accounts and they invoice it.
>
> **You:** So closed means finished but not billed?
>
> **Priya:** Closed means the work's done and the vehicle's ready. Accounts will tell you closed
> means invoiced. Drives me mad. When they say "how many closed this month" they mean billed and
> when I say it I mean finished.
>
> **You:** What about the courtesy car?
>
> **Priya:** Courtesy vehicle, we call it. It goes out with the driver, comes back when they collect.
> One at a time obviously — you can't have the same car out on two jobs. And it's got its own job
> card sometimes, when it needs servicing itself, which confuses everybody.

---

## Conversation 2 — with Marcus, who runs the parts desk

> **Marcus:** I don't really deal in vehicles. I deal in fitments. Somebody comes to me with a
> registration, I look up the fitment — engine code, year, trim — and that tells me what parts go on
> it. Two completely different cars with different plates can be the same fitment as far as I'm
> concerned.
>
> **You:** So a vehicle to you is…
>
> **Marcus:** A key into the catalogue. That's it. I don't care about mileage, I don't care whose it
> is.
>
> **You:** And a part?
>
> **Marcus:** Depends what you mean. There's the part number — that's a thing in the catalogue, it
> exists whether we've got one or not. Then there's the stock, which is the ones on my shelf, each
> with a bin location. And then there's what Priya calls a part, which is one that's been fitted to
> a job and needs charging out.
>
> **You:** Three things.
>
> **Marcus:** Three things, and everybody calls all three "a part". When Priya says "have you got
> that part" she means stock. When she says "put the part on the job card" she means the charged
> one. When I say "that part's superseded" I mean the catalogue one.
>
> **You:** What about the fifty-unit authorisation threshold?
>
> **Marcus:** That's mine, yes. It's fifty at the moment. It was thirty. For the fleet accounts it's
> two hundred because they've signed something. I'll probably move it again.
>
> **You:** Where's it written down?
>
> **Marcus:** …in my head, mostly. And in the system somewhere, I think.

---

## What a good answer looks like

**On the glossary:** every term, and where two people define a term differently, *both* definitions,
attributed. A glossary with one definition per word has already lost the information you were
looking for.

**On the double-jobbing words:** there are at least six. Four are reasonably visible; two are the
kind you only find by reading the transcript twice. Getting four is a pass. If you found one, you
skimmed.

**On the boundary:** the answer is not "one system". It is also not "one context per person". Say
what the contexts are, what each one's model of the shared concepts looks like, and what identifier
carries the translation across.

**On the three errors:** name the specific class or field you would have got wrong, and what event
would eventually reveal it. "I'd have modelled it badly" is not an answer.

---

## Hints, if you want them

<details>
<summary>Hint 1 — where to look for double-jobbing words</summary>

Look for these moments in the transcript:

- Anywhere someone says "depends", "though", "sort of", or "we call it"
- Anywhere the two speakers use the same word in the same paragraph
- Anywhere someone corrects your word with theirs
- Anywhere someone says another department means something different

Priya hands you one outright and is annoyed about it. Marcus hands you one and enumerates it
himself. The other four you have to find.

</details>

<details>
<summary>Hint 2 — one thing that is not a double-jobbing word</summary>

The fifty threshold is not a vocabulary problem. It is a different animal entirely, and Lesson 0.1
plus `DOMAIN.md` both have a name for it.

Notice what Marcus says about it: it has changed once, it differs per account, it lives in his
head, and he expects to change it again. If you modelled that as an invariant you would be
hard-coding a number that is under active negotiation.

</details>

<details>
<summary>Hint 3 — the courtesy vehicle</summary>

Priya says the courtesy vehicle "sometimes has its own job card, when it needs servicing itself,
which confuses everybody."

That sentence is worth five minutes on its own. Is the courtesy vehicle a *vehicle*, or a
*resource that gets allocated*, or both? What happens to the "one at a time" rule when the courtesy
vehicle is itself the subject of a job card? Whatever you conclude, write down the question you
would ask Priya next — the question is worth more than the answer you guessed.

</details>

---

## Self-assessment

- [ ] The glossary has at least 15 entries
- [ ] At least two entries have two definitions, attributed to different people
- [ ] I found at least four words doing two jobs
- [ ] I identified more than one bounded context, and named the identifier that translates between them
- [ ] I distinguished the fifty threshold from the invariants around it
- [ ] For each of my three modelling errors, I named a class or field and an event that would reveal it
- [ ] I wrote down at least two questions I would go back and ask

That last box is the one that matters most in real work. A glossary produces questions. If yours
produced none, you were transcribing rather than modelling.

---

- [ ] **Lesson 0.2 complete.** I have built the glossary, found the double-jobbing words, drawn the
      context boundary, and written down what I would go back and ask.
