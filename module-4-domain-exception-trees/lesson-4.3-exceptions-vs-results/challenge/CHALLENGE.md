# Challenge — Lesson 4.3: Exceptions vs Result Types

> **You write:** `domain/workshop/Refusal.php` · `domain/workshop/Decision.php`
> **You edit:** `domain/workshop/JobCard.php` — two new methods, two rewritten
> **You run:** `php module-4-domain-exception-trees/lesson-4.3-exceptions-vs-results/challenge/starter.php`
> **Then run:** `php regression.php`
> **Done when both are green.**

Short, after 4.2. The difficulty is entirely in deciding *which* operations get a `Decision` — and
the answer is two of them, not all of them.

---

## Read `checks.php` first. Section 5 is the lesson.

---

## 1 — `domain/workshop/Refusal.php`

```php
final readonly class Refusal
{
    private function __construct(
        public string $code,
        public string $message,
        public array $context,
        private CarriesContext $failure,
    ) {}

    public static function from(CarriesContext $failure): self;

    public function raise(): never;
}
```

**It holds the exception.** That is the design, not an implementation detail. The alternative —
`Refusal::of('outstanding-authorisations', 'Job card...', [...])` — is simpler and puts the drift
straight back, because the code and message are then written twice with nothing keeping them in
step.

**The parameter is `CarriesContext`, not `WorkshopFailure`.** This needs `failureCode()` and
`context()`; a marker interface does not have them. A domain failure carrying no context cannot
become a `Refusal`, which is a constraint worth having.

**`raise(): never`, and it throws the original object.** Not a copy. Same argument as 4.1's
decorator: rebuilding it reports this file in every stack trace.

**Private constructor.** The checks assert it.

---

## 2 — `domain/workshop/Decision.php`

```php
final readonly class Decision
{
    private function __construct(public array $refusals) {}

    #[\NoDiscard('A Decision that is not read has decided nothing.')]
    public static function allowed(): self;

    #[\NoDiscard('A Decision that is not read has decided nothing.')]
    public static function refusedBecause(Refusal $first, Refusal ...$rest): self;

    public function isAllowed(): bool;
    public function isRefused(): bool;

    /** @return list<string> */
    public function codes(): array;

    public function orThrow(): void;
}
```

**Note the signature of `refusedBecause()`.** `Refusal $first, Refusal ...$rest` — not a bare
variadic. `Decision::refusedBecause()` with no arguments would be a refused decision holding no
reasons, which reports itself as allowed. That is the one state this class must not be able to
reach, and the way to prevent it is a required first parameter. The checks assert
`getNumberOfRequiredParameters() >= 1`.

**`#[\NoDiscard]` on both factories.** The characteristic bug of every result type in every language
is `$jobCard->checkCanComplete();` on its own line — a no-op shaped like a guard. Example 03 makes
PHP say so.

**`orThrow()` throws the first refusal and discards the rest.** That is not a limitation to
apologise for; it is the cost of the exception style expressed in one line of code.

---

## 3 — Two methods on `JobCard`

```php
#[\NoDiscard('A Decision that is not read has decided nothing.')]
public function checkCanComplete(): Decision;

#[\NoDiscard('A Decision that is not read has decided nothing.')]
public function checkCanInvoice(): Decision;
```

`checkCanComplete()` collects **both** possible refusals:

| Order | Condition | Refusal built from |
|-------|-----------|--------------------|
| 1 | `pendingAuthorisations !== []` | `OutstandingAuthorisations::on(...)` |
| 2 | cannot transition to `Completed` | `IllegalStatusTransition::from(...)` |

**The order matters and is asserted.** `orThrow()` throws the first, so this order decides what
`complete()` throws — and outstanding-work-first is what `complete()` threw before this lesson, so
Lesson 4.2's checks keep passing.

Sit with that for a moment: statement order inside a method used to be an implementation detail and
has quietly become an observable decision. It is a real cost of this pattern, and the kind that bites
during a tidy-up.

---

## 4 — Rewrite the two operations

```php
public function complete(): void
{
    $this->checkCanComplete()->orThrow();

    $this->transitionTo(JobCardStatus::Completed);
    $this->recordThat(new JobCardCompleted($this->id));
}
```

**One list of guards, two ways of reading it.** This is what makes the pattern affordable — without
it you are maintaining every rule twice and hoping.

`transitionTo()` re-checks a transition the Decision has already cleared. Leave it. This is the only
method that assigns `$this->status`, and a method that mutates state should enforce its own
invariant rather than trust its callers. The cost is one `match()` on the happy path.

Same shape for `invoice()`.

---

## 5 — What NOT to do

**Do not add a `checkCanX()` to every method.** `addLine()`, `removeLine()`, `startWork()`,
`cancel()` stay exception-only. Nobody asks whether they may add a line; they add it. The test is
README section 5: *does anybody ask before acting?*

Doubling the API is the failure mode of this lesson, not the goal.

**Do not delete `checkCanInvoice()` because it can only hold one refusal.** It exists because the
caller is a screen with two buttons on it, and that screen should not have to know that one is
answered by a `Decision` and the other by catching an exception.

**Do not make `Refusal` throwable.** Holding two of them would mean two live exceptions carrying
stack traces from where they were *built* rather than thrown.

**Do not build `Result<T, E>`.** PHP has no generics. `Decision` answers one question where the yes
case carries no value, which collapses the problem — thirty lines instead of three hundred.

---

## Acceptance criteria

- [ ] `Refusal` is `final readonly`, private constructor, `from(CarriesContext)`, `raise(): never`
- [ ] `raise()` throws the **same object** — asserted with `===`
- [ ] `Refusal` is not itself throwable
- [ ] `Decision` is `final readonly`; both factories carry `#[\NoDiscard]`
- [ ] `refusedBecause()` requires at least one `Refusal`
- [ ] `codes()` returns the failure codes in order
- [ ] Both check methods return `Decision` and carry `#[\NoDiscard]`
- [ ] Awaiting Authorisation with an outstanding item yields **two** refusals, in the right order
- [ ] **The drift test:** ask and act agree in all seven reachable states, for both operations
- [ ] Where both refuse, the thrown failure's code is the **first** refusal's code
- [ ] `php regression.php` is green

---

## Hints

<details>
<summary>Hint 1 — collecting refusals</summary>

```php
$refusals = [];

if ($this->pendingAuthorisations !== []) {
    $refusals[] = Refusal::from(
        OutstandingAuthorisations::on($this->id, $this->pendingAuthorisations)
    );
}

if (!$this->status->canTransitionTo(JobCardStatus::Completed)) {
    $refusals[] = Refusal::from(
        IllegalStatusTransition::from($this->id, $this->status, JobCardStatus::Completed)
    );
}

return $refusals === []
    ? Decision::allowed()
    : Decision::refusedBecause(...$refusals);
```

Note it calls `canTransitionTo()` rather than `transitionTo()`. The enum already answers the
question without performing the transition, which is Lesson 2.1 paying off in a way it did not
anticipate.

</details>

<details>
<summary>Hint 2 — spreading into a required first parameter</summary>

`Decision::refusedBecause(...$refusals)` works with the `Refusal $first, Refusal ...$rest`
signature — PHP unpacks the first element into `$first` and the rest into `$rest`. You do not need
to split the array yourself.

It will fatal at runtime if `$refusals` is empty, which is why the branch above checks for that
first.

</details>

<details>
<summary>Hint 3 — if the drift test fails</summary>

It will name the state and both answers. Two likely causes:

- **`complete()` still has its own `if` above the `orThrow()`.** Delete it. The whole point is one
  list.
- **The refusal order is reversed.** The drift test itself will still pass — both paths agree on
  *whether* — but the check asserting the thrown code equals `codes()[0]` will fail, and so will
  Lesson 4.2's `complete()` assertion in the regression sweep.

</details>

<details>
<summary>Hint 4 — `#[\NoDiscard]` warnings appearing in your test output</summary>

That is the attribute working. Something is calling a check method and throwing the result away.
Find it and use the value, or assign it — assignment counts as use.

If it is in your own scratch code, note that this is exactly the bug the attribute exists to catch,
and it caught it.

</details>

---

## When it passes

Compare with `solution/`. Worth looking at:

- `Refusal`'s doc block on why it holds the exception, and the cost that buys
- `Decision`'s note on refusal order becoming part of the contract
- `checkCanInvoice()`'s defence of existing at all
- The comment in `complete()` about why `transitionTo()`'s redundant re-check stays

---

## Then, without running anything

**Q1.** The drift test passes, and in the reference implementation it *cannot* fail — `complete()`
is `$this->checkCanComplete()->orThrow()`, so there is one list of guards. Write down why the test is
still worth having. (Your answer should mention something a developer could do next week.)

**Q2.** `Refusal` holds a `CarriesContext`, so building a refused `Decision` constructs one exception
per reason, backtrace included. Name the specific situation in this domain where that cost is
unacceptable, then give the fix — and note that the fix is not in this file.

**Q3.** `checkCanComplete()` returns refusals in a fixed order, and `orThrow()` throws the first.
Suppose a colleague reorders the two `if` blocks for readability. Trace exactly what breaks, how
loudly, and which test catches it.

**Q4.** Lesson 4.2 argued that `OutstandingAuthorisations` is a redundant guard kept for the
message, since the state machine already refuses that transition. Now that both refusals appear
together in a single `Decision`, is it still redundant? Argue both ways, then say which you would
ship.

**Q5.** `JobCards::contains()` returns `bool`. Should it return a `Decision`? Answer in two
sentences, and name the rule you applied.
