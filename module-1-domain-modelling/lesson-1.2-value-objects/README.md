# Lesson 1.2 — Value Objects ⭐
> **Module 1: Domain Modelling** · Advanced Domain Architecture

| | |
|---|---|
| **Time** | ~30 min reading · ~30 min running the examples. Challenge on top. |
| **Comes after** | Lesson 1.1 — Rich vs Anemic Models |
| **Covers** | Primitive obsession · The three properties · Money and integer minor units · Normalisation · Withers and `clone with` · When NOT to |
| **You write** | `Currency.php`, `Money.php`, `RegistrationNumber.php` |
| **Needs PHP** | 8.5+ |

---

## 📁 Lesson folder

```
lesson-1.2-value-objects/
├── README.md
├── examples/
│   ├── 01-primitive-obsession.php       ← what floats and strings actually cost
│   ├── 02-the-three-properties.php      ← each removed in turn, and the bug it lets in
│   ├── 03-withers-and-clone-with.php    ← PHP 8.5 clone($obj, [...]) and #[\NoDiscard]
│   └── 04-when-not-to.php               ← Rule 6, at the smallest scale
├── challenge/
│   ├── CHALLENGE.md
│   ├── checks.php                       ← the spec
│   ├── starter.php
│   └── solution/
└── quiz/QUIZ.md
```

---

## 1 — Primitive obsession

Two concepts are missing from every codebase that stores money in a `float` and a registration in a
`string`:

```php
function invoice(float $amount, string $currency, string $registration): void
```

Everything wrong with that signature:

- `$amount` can be negative. There is no negative price, so the type is lying.
- `$amount` is a float, so `0.1 + 0.2 !== 0.3` and your totals drift by cents.
- `$amount` and `$currency` can be separated. Nothing keeps 4500 with EUR.
- `$registration` can be `''`, `'  ab 12 cde '`, `'AB12CDE'` or `'ab12cde'` — four spellings of one
  registration, and `===` says all four are different.
- The parameters can be passed in the wrong order and the type system will not notice.

Every one of those is a real bug that has shipped in real systems. **Primitive obsession** is the
name for using `int`, `float` and `string` to represent concepts that have rules.

The alternative:

```php
function invoice(Money $amount, RegistrationNumber $registration): void
```

Three parameters became two, because `Money` was always one concept. Neither can be constructed
wrong. Neither can be passed in the wrong order.

---

## 2 — The three properties

A value object has exactly three defining properties. Miss one and it is not a value object — it is
a class that resembles one.

### Immutability

Once constructed, it never changes. Operations return new instances.

```php
$price   = Money::of(4500, Currency::EUR);
$doubled = $price->times(2);        // a NEW Money; $price is still 4500
```

**Without it:** an object handed to two collaborators can be changed by one behind the other's back,
and any invariant checked at construction can be violated afterwards.

The concrete version of that: you put a `Money` in an array as a running total, hand the same
instance to a discount calculator, and it mutates it. Every earlier total in the array changes too,
because they are the same object. This bug is very hard to find and has a distinctive symptom —
historical records that change when new ones are added.

### Structural equality

Two value objects with equal contents *are the same value*.

```php
Money::of(4500, Currency::EUR)->equals(Money::of(4500, Currency::EUR));   // true
```

**Without it:** `$a === $b` compares object identity, so two 45.00 EUR values are "different".
Every comparison, every `in_array`, every array key silently misbehaves.

> **PHP detail worth knowing.** `==` on objects compares class and property values, so it
> accidentally does the right thing for simple value objects. `===` compares identity. Relying on
> `==` is still a mistake: it does not let you decide what equality *means*. `RegistrationNumber`
> needs equality by *normalised* value, and only an explicit `equals()` gives you that decision.

### Self-validation

It cannot be constructed in an invalid state.

```php
Money::of(-100, Currency::EUR);          // throws. There is no negative money.
RegistrationNumber::fromString('!!');    // throws. That is not a registration.
```

**Without it:** an invalid instance can exist, so every consumer must re-check, and the day one of
them forgets is the day a negative price reaches an invoice.

> A value object that can be constructed in an invalid state is not a value object. It is a
> struct with a nice name, and it has bought you nothing except a file.

---

## 3 — Money: never a float

The single most common serious bug in this territory.

```php
$total = 0.0;
for ($i = 0; $i < 10; $i++) {
    $total += 0.1;
}
var_dump($total === 1.0);        // false
```

Floats are binary fractions. `0.1` is not representable, so it is stored as something very slightly
different, and the error accumulates. On ten additions you are out by a rounding error. On a
million-line ledger you are out by an amount somebody has to reconcile by hand.

**Store integer minor units.** 4500 cents, not 45.00.

```php
Money::of(4500, Currency::EUR);          // 45.00 EUR, exactly, forever
```

`int` arithmetic in PHP is exact up to `PHP_INT_MAX` — around 9.2 quintillion, which is 92
quadrillion cents. You will not overflow it with an invoice.

### Not every currency has two decimal places

The reason `Currency` is an enum with behaviour rather than a string:

| Currency | Minor units | 4500 minor units is |
|----------|:-----------:|---------------------|
| EUR, USD, GBP | 2 | 45.00 |
| JPY | 0 | ¥4,500 |
| BHD, KWD | 3 | 4.500 |

A codebase that hard-codes `/ 100` is correct until the first Japanese customer. Putting the
exponent on the currency puts it where it belongs — the currency knows how it is subdivided, and
nothing else has to.

### Currency mixing

```php
Money::of(4500, Currency::EUR)->plus(Money::of(3000, Currency::USD));   // throws
```

There is no sensible answer. Any conversion needs a rate, a date and a rounding policy, all of
which are inputs from outside the domain — so `Money` refuses, and conversion becomes an explicit
operation somebody has to think about.

> **A judgement call worth arguing with.** This course throws `\DomainException` for a currency
> mismatch and `\InvalidArgumentException` for a negative amount. The reasoning: a negative amount
> is a malformed *argument* — no state of anything makes it acceptable. A currency mismatch has two
> perfectly valid arguments, and it is the *relationship* between them that the business refuses.
>
> Reasonable people disagree, and some codebases make both `InvalidArgumentException`. What matters
> far more than which you pick is that you pick one and it is consistent, because Module 4 maps
> these onto HTTP status codes and an inconsistent tree produces inconsistent responses.

---

## 4 — Normalisation, and equality that means something

`RegistrationNumber` is the more interesting of the two, because it has a rule that would otherwise
be duplicated in every comparison in the system.

```
'AB12CDE'    ─┐
'ab12cde'     ├──  all four are the same registration
'AB-12-CDE'   │
'  ab 12 cde '─┘
```

The value object normalises once, at construction, and stores only the normalised form. Then
equality is trivial and correct everywhere, forever — including as an array key, in a `switch`, and
in a database index.

**The general principle:** *normalise at the boundary of the concept, not at every comparison.*
Every place that lowercases an email before comparing it is a place that could forget to.

Compare that with the alternative you have certainly seen:

```php
if (strtoupper(str_replace([' ', '-'], '', $a)) === strtoupper(str_replace([' ', '-'], '', $b)))
```

That expression is correct. It is also duplicated eleven times, and one of the eleven forgot the
hyphen.

---

## 5 — Withers, and PHP 8.5 `clone with`

Immutability means "change" produces a new instance. The convention is a `withX()` method.

```php
// PHP 8.4 and below — every property listed, every time
public function withAmount(int $amountMinor): self
{
    return new self($amountMinor, $this->currency);
    // add a third property and every wither must be updated
}
```

PHP 8.5 adds **clone with**. Despite the name it is written as a *call* — `clone($object, [...])` —
not as a `with` keyword. There is no `with` keyword in PHP, and material claiming otherwise is
describing a proposal.

```php
#[\NoDiscard('Returns a new Money — the original is unchanged')]
public function withAmount(int $amountMinor): self
{
    return clone($this, ['amountMinor' => $amountMinor]);
    // currency carried over automatically; new properties need no change here
}
```

**Two rules that catch people:**

1. A `readonly` property can only be replaced by clone-with **from inside the declaring class**.
   From outside it throws "Cannot modify readonly property". This is why every wither in this
   course is a method on the class rather than a helper beside it.
2. `set` hooks (PHP 8.4) run on the cloned values, so validation and normalisation still apply.

### `#[\NoDiscard]` — PHP 8.5

The characteristic immutability bug:

```php
$price->withAmount(9999);        // does nothing. $price is unchanged.
```

Correct code that achieves nothing, and it reads exactly like the mutable version somebody was used
to writing. `#[\NoDiscard]` makes PHP emit a warning when the return value is thrown away.

Use it on every wither. It is the cheapest bug prevention in the language.

---

## 6 — When *not* to make a value object

Rule 6 does not stop applying because you are in the module about the patterns. This is where
people who have just read about DDD do the most damage.

**Each value object costs:** a file, a name someone has to learn, a mapping to and from storage, and
a conversion at every boundary where a primitive arrives.

**It earns that when it has a rule.** Not "it is a domain concept" — a *rule*:

| Candidate | Verdict | Why |
|-----------|---------|-----|
| `Money` | Yes | Non-negative, currency-safe, exact arithmetic. Three rules, all with money attached. |
| `RegistrationNumber` | Yes | Normalisation and format, otherwise duplicated at every comparison. |
| `EmailAddress` | Usually | Format plus normalisation, and it is compared constantly. |
| `Colour` (a free-text string) | **No** | No rule. You have wrapped a string in a file. |
| `Description` (any text) | **No** | "Not empty" is not enough to justify a type. |
| `PageNumber` | **No** | It is an `int` that is positive. A named argument says it better. |

**The test:** *can you name a rule this type enforces that the primitive does not?* If the honest
answer is "it is more expressive", that is a real but small benefit, and it should lose to the cost
more often than most people let it.

There is a milder middle option people forget: a typed alias by convention, or just a well-named
parameter. `bookIn(string $registration)` with normalisation at one boundary is not as good as a
value object, and it is much better than eleven duplicated `strtoupper` calls.

---

## 7 — Common mistakes

| Mistake | Why it is wrong | Instead |
|---------|----------------|---------|
| `float` for money | Accumulating rounding error, silently | Integer minor units |
| Public setters on a value object | It is not immutable, so nothing above holds | `readonly`, withers |
| Relying on `==` for equality | You never get to decide what equality means | An explicit `equals()` |
| Normalising at the comparison | Duplicated, and one copy will differ | Normalise at construction |
| A wither that mutates `$this` | Callers holding the original see it change | Return a new instance |
| Discarding a wither's result | A no-op that reads like a mutation | `#[\NoDiscard]` |
| Value objects for everything | Section 6 | Only where there is a rule |
| Hard-coding `/ 100` | Wrong for JPY, KWD and others | Ask the currency |

---

## 8 — Where else this shows up

`Money` is the canonical example, but the shape recurs constantly:

- **Quantity with a unit** — 5 kilograms and 5 pounds are not the same number, and adding them
  should be as hard as adding EUR and USD
- **Percentages and rates** — 0.15 or 15? A `TaxRate` never has to be asked
- **Identifiers** — Lesson 1.3's `VehicleId`, and every place a `string $id` is passed to the wrong
  parameter
- **Time intervals and durations** — Lesson 1.4's `LabourUnits`, where "1.5 hours" bills as
  fifteen tenths

If you have ever written a comment saying *"amount is in cents"*, you have written a value object in
prose. Write it in a type instead.

---

## ✅ Lesson checklist

- [ ] Read this README fully — especially sections 3 and 6
- [ ] Run and study `examples/01-primitive-obsession.php`
- [ ] Run and study `examples/02-the-three-properties.php`
- [ ] Run and study `examples/03-withers-and-clone-with.php`
- [ ] Run and study `examples/04-when-not-to.php`
- [ ] Read `challenge/checks.php` before writing anything
- [ ] Complete the challenge — three classes in `domain/workshop/`
- [ ] Compare against `challenge/solution/`, and log disagreements in `PROGRESS.md`
- [ ] Take `quiz/QUIZ.md` closed-book — question 18 is the one most people get wrong

---

*Next lesson: **1.3 — Entities and Identity** — the other half of the model, and the distinction
that decides which one you are building.*
