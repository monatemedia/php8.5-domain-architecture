# Challenge — Lesson 1.2: Money, Currency, RegistrationNumber

> **You write:** `domain/workshop/Currency.php`, `Money.php`, `RegistrationNumber.php`
> **You run:** `php module-1-domain-modelling/lesson-1.2-value-objects/challenge/starter.php`
> **Done when it prints:** `ACCEPTANCE: all checks passed`

---

## Read `checks.php` first

It is the specification. This file explains why.

---

## The brief

Three value objects, all in the `Workshop` namespace, all in `domain/workshop/`. They implement
invariants **I1** and **I2** from `DOMAIN.md`.

Build them in this order. `Money` needs `Currency`; `RegistrationNumber` needs neither and is the
easier warm-up if you would rather start there.

---

## 1 — `Currency` (a backed enum)

```php
enum Currency: string
{
    case EUR = 'EUR';
    case USD = 'USD';
    case GBP = 'GBP';
    case JPY = 'JPY';
    case KWD = 'KWD';
}
```

Exactly those five cases, with exactly those values.

| Method | Behaviour |
|--------|-----------|
| `minorUnits(): int` | How many decimal places this currency has: **EUR/USD/GBP → 2, JPY → 0, KWD → 3** |
| `format(int $amountMinor): string` | Renders minor units for humans — see below |

`format()` must produce:

| Call | Result |
|------|--------|
| `Currency::EUR->format(4500)` | `EUR 45.00` |
| `Currency::EUR->format(0)` | `EUR 0.00` |
| `Currency::EUR->format(7)` | `EUR 0.07` |
| `Currency::JPY->format(4500)` | `JPY 4500` |
| `Currency::KWD->format(4500)` | `KWD 4.500` |

No thousands separators. Format is `CODE` + a space + the amount, with exactly `minorUnits()`
decimal places, and no decimal point at all when `minorUnits()` is zero.

**Why an enum and not a string.** JPY has no minor units and KWD has three. A codebase that
hard-codes `/ 100` is correct until its first Japanese customer, and then it is wrong by a factor of
a hundred in a way that looks like a data problem. Putting the exponent on the currency puts it
where the knowledge belongs.

---

## 2 — `Money`

```php
final readonly class Money { /* ... */ }
```

Integer minor units. Never a float, anywhere, for any reason.

| Member | Behaviour |
|--------|-----------|
| `Money::of(int $amountMinor, Currency $currency): self` | Throws `\InvalidArgumentException` if negative |
| `Money::zero(Currency $currency): self` | Zero in that currency |
| `amountMinor(): int` | The raw minor units |
| `currency(): Currency` | |
| `plus(Money $other): self` | Throws `\DomainException` on a currency mismatch |
| `minus(Money $other): self` | Throws `\DomainException` on mismatch; `\DomainException` if the result would be negative |
| `times(int $multiplier): self` | Throws `\InvalidArgumentException` if the multiplier is negative |
| `equals(Money $other): bool` | Same amount **and** same currency |
| `isGreaterThan(Money $other): bool` | Throws `\DomainException` on a currency mismatch |
| `isZero(): bool` | |
| `__toString(): string` | Delegates to `Currency::format()` |

**The constructor must not be public.** `Money::of()` and `Money::zero()` are the only ways in. A
public constructor makes the guards optional, including from inside a test or a mapper.

### On the two exception types

| Failure | Type | Why |
|---------|------|-----|
| Negative amount | `\InvalidArgumentException` | Malformed argument. No state of anything makes it acceptable. |
| Negative multiplier | `\InvalidArgumentException` | Same. |
| Currency mismatch | `\DomainException` | Both arguments are perfectly valid Money. The **relationship** is what the business refuses. |
| `minus()` going below zero | `\DomainException` | Both arguments valid; the result is not a value this business has. |

This is a judgement call and the README says so. Some codebases make all four
`InvalidArgumentException`. What matters is consistency, because Module 4 maps these onto HTTP
status codes and an inconsistent tree produces inconsistent responses.

---

## 3 — `RegistrationNumber`

```php
final readonly class RegistrationNumber { /* ... */ }
```

| Member | Behaviour |
|--------|-----------|
| `RegistrationNumber::fromString(string $raw): self` | Normalises, then validates |
| `value(): string` | The **normalised** form |
| `equals(RegistrationNumber $other): bool` | By normalised value |
| `__toString(): string` | The normalised form |

**Normalisation, in this order:**

1. Trim surrounding whitespace
2. Remove **all** spaces and hyphens, anywhere in the string
3. Uppercase

**Validation, after normalising.** All failures throw `\InvalidArgumentException`.

- 2 to 8 characters
- Only `A`–`Z` and `0`–`9`
- At least one letter **and** at least one digit

So these are all the same registration:

```
'AB12CDE'   'ab12cde'   'AB-12-CDE'   '  ab 12 cde  '
```

And these are all refused:

```
''          too short
'A'         too short
'ABCDEFGHI' too long (9)
'ABCDEFG'   no digit
'12345'     no letter
'AB!12'     illegal character
'AB_12'     illegal character
```

> **Note the deliberate simplification.** Real registration formats are national, and a real system
> would need a per-country strategy. This one rule is enough to make the *modelling* point —
> normalise once, compare everywhere — without turning the challenge into a research exercise. If
> you build this for real, the format rule is the part to replace.

---

## Acceptance criteria

- [ ] All three files are in `domain/workshop/`, namespace `Workshop`
- [ ] `Money` and `RegistrationNumber` are `final readonly class`
- [ ] Neither has a public constructor
- [ ] No `float` appears anywhere in `Money`
- [ ] Every operation returns a new instance; nothing mutates
- [ ] `equals()` is explicit on both, not left to `==`
- [ ] `Currency` handles 0, 2 and 3 minor units correctly
- [ ] No file imports anything outside the `Workshop` namespace

---

## Hints

<details>
<summary>Hint 1 — Currency::format()</summary>

You need the amount split into major and minor parts, with the split depending on `minorUnits()`.

```php
$divisor = 10 ** $this->minorUnits();   // 1, 100 or 1000
```

`intdiv()` and `%` give you the two halves without a float ever appearing.
`str_pad()` with `STR_PAD_LEFT` gives you the zero padding.

Watch the zero case: with `minorUnits() === 0` the divisor is 1, and there should be no decimal
point at all — not `JPY 4500.` and not `JPY 4500.0`.

</details>

<details>
<summary>Hint 2 — the private constructor</summary>

```php
final readonly class Money
{
    private function __construct(
        private int      $amountMinor,
        private Currency $currency,
    ) {}

    public static function of(int $amountMinor, Currency $currency): self
    {
        if ($amountMinor < 0) {
            throw new \InvalidArgumentException(/* ... */);
        }
        return new self($amountMinor, $currency);
    }
}
```

A private constructor is callable from any code *inside the class*, including from static methods
and from other instances of the same class. That is what makes `plus()` able to build its result.

</details>

<details>
<summary>Hint 3 — the currency guard, written once</summary>

Three methods need it. Write it once:

```php
private function assertSameCurrency(self $other): void
{
    if ($this->currency !== $other->currency) {
        throw new \DomainException(/* ... */);
    }
}
```

Enum cases are singletons, so `!==` is the correct comparison for two enum instances — no `equals()`
needed, and no `->value` comparison either.

</details>

<details>
<summary>Hint 4 — normalising a registration</summary>

```php
$normalised = strtoupper(str_replace([' ', '-'], '', trim($raw)));
```

Then one `preg_match` for the character set and length, and two more for "has a letter" and "has a
digit". Three small checks read better than one clever pattern, and they let you throw a message
that says which rule failed — which matters more than you would think when the error reaches a user.

</details>

---

## When it passes

Compare with `solution/`. Specific things to look at:

- How they wrote `format()`, particularly the zero-minor-unit branch
- Whether their exception messages contain the offending value (yours should)
- Whether they used `10 ** $n` or a `match` expression, and which you find clearer
- Whether `minus()` checks the currency before or after the negative-result check, and whether the
  order is observable

Log anything you would have done differently in `PROGRESS.md`.

---

## Then, without running anything

**Q1.** `Money::of(4500, Currency::EUR)` and `Money::of(4500, Currency::JPY)` both hold `4500`. One
is 45 euros and one is 4,500 yen. Is `Money` therefore an unsafe abstraction? Argue both sides in
four sentences.

**Q2.** You have made `Money` refuse negative amounts. The workshop issues credit notes. Where does
a credit note live, given that its amount is conceptually negative? Name two options and say which
you prefer.

**Q3.** Look at your `RegistrationNumber`. It refuses `'12345'` because there is no letter. Find a
real country whose plates would fail your rule. What does that tell you about validation rules
written from a specification rather than from data?
