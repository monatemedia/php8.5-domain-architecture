# Challenge — Lesson 1.3: VehicleId, OdometerReading, Vehicle

> **You write:** `domain/workshop/VehicleId.php`, `OdometerReading.php`, `Vehicle.php`
> **You run:** `php module-1-domain-modelling/lesson-1.3-entities-and-identity/challenge/starter.php`
> **Done when it prints:** `ACCEPTANCE: all checks passed`

This is the first challenge that **uses what you built last time**. `Vehicle` takes a
`RegistrationNumber`, which you wrote in Lesson 1.2. If that file is not in `domain/workshop/`, this
one cannot pass — which is the accumulation working as intended.

---

## Read `checks.php` first.

---

## 1 — `VehicleId`

```php
final readonly class VehicleId { /* ... */ }
```

| Member | Behaviour |
|--------|-----------|
| `VehicleId::generate(): self` | A fresh, unique identity. No arguments, no database. |
| `VehicleId::fromString(string $value): self` | Rebuild from storage. Throws `\InvalidArgumentException` if empty or whitespace-only. |
| `value(): string` | |
| `equals(VehicleId $other): bool` | |
| `__toString(): string` | |

Constructor not public.

**Two named factories, not one constructor.** `generate()` and `fromString()` mean opposite things:
one *mints* a new identity, the other *recognises* an existing one. A single constructor cannot say
which, so every caller has to know from context — and the day somebody calls the wrong one, a
vehicle gets a second identity and its history splits in two.

For `generate()`, `bin2hex(random_bytes(...))` with a prefix is fine. A real system would use
UUID v7 from a library; this course has no Composer dependencies before Module 3, and example 02
says so.

---

## 2 — `OdometerReading`

```php
final readonly class OdometerReading { /* ... */ }
```

| Member | Behaviour |
|--------|-----------|
| `OdometerReading::of(int $kilometres): self` | Throws `\InvalidArgumentException` if negative, or above **2,000,000** |
| `kilometres(): int` | |
| `isLessThan(OdometerReading $other): bool` | |
| `equals(OdometerReading $other): bool` | |
| `__toString(): string` | `number_format()` plus `' km'` — e.g. `84,500 km` |

Constructor not public. Zero is valid: a new vehicle has done no kilometres.

The 2,000,000 bound is a **sanity check, not a business rule** — its job is to catch a transposed
digit at the service desk. Saying which of the two a guard is turns out to matter, and Lesson 1.4
is about exactly that.

---

## 3 — `Vehicle`

```php
final class Vehicle { /* ... */ }        // NOT readonly
```

| Member | Behaviour |
|--------|-----------|
| `__construct(VehicleId $id, RegistrationNumber $registration, OdometerReading $odometer)` | Public. All three required. |
| `id(): VehicleId` | |
| `registration(): RegistrationNumber` | |
| `odometer(): OdometerReading` | |
| `recordOdometer(OdometerReading $reading): void` | **Invariant I4.** Throws `\DomainException` if the reading is lower than the current one. |
| `reRegister(RegistrationNumber $registration): void` | |
| `equals(Vehicle $other): bool` | **Identity only.** |

**`Vehicle` must not be `readonly`.** An entity changes over its lifetime; `readonly` forbids writes
even from inside the class after construction, so a readonly entity cannot express its own
lifecycle. The checks assert this explicitly, because it is the mistake people make immediately
after Lesson 1.2.

### Invariant I4, read literally

> An odometer reading may never decrease for a given vehicle.

**Never decrease** is not **must always increase.** Recording the *same* reading twice is allowed —
a vehicle in for two jobs on the same day has not moved, and a model that refused that would send
somebody to the service desk to invent a number.

The difference between `<` and `<=` has gone to production more than once. Read your invariants
literally.

### `equals()` compares identity and nothing else

Two vehicles with the same registration, colour and mileage are two vehicles. One vehicle whose
every attribute has changed is still that vehicle. An `equals()` that compares attributes reports
that a vehicle stopped being itself when somebody corrected a typo, and downstream that looks like
duplicate rows and a cache that never hits.

---

## Acceptance criteria

- [ ] All three files in `domain/workshop/`, namespace `Workshop`
- [ ] `VehicleId` and `OdometerReading` are `final readonly` with non-public constructors
- [ ] `Vehicle` is `final` and **not** `readonly`
- [ ] `Vehicle` has a public constructor
- [ ] `Vehicle::equals()` compares only identity
- [ ] `Vehicle` accepts only typed values — a raw `string` registration must not compile
- [ ] `recordOdometer()` leaves the vehicle unchanged when it refuses
- [ ] No public property is writable from outside; no public `set*` methods
- [ ] No file imports anything outside the `Workshop` namespace

---

## Hints

<details>
<summary>Hint 1 — generate() and uniqueness</summary>

```php
public static function generate(): self
{
    return new self('VEH-' . strtoupper(bin2hex(random_bytes(8))));
}
```

Sixteen hex characters from a cryptographically secure source. The checks generate a few hundred and
assert they are all distinct — a counter starting at 1 will fail that in a fresh process every time,
which is the point.

</details>

<details>
<summary>Hint 2 — the entity's guard</summary>

```php
public function recordOdometer(OdometerReading $reading): void
{
    if ($reading->isLessThan($this->odometer)) {
        throw new \DomainException(/* ... */);
    }
    $this->odometer = $reading;
}
```

Note how little work this is, and note *why*: `$reading` is already known to be non-negative and
plausible, because it is an `OdometerReading` and there is no other way to get one. The entity only
has to check the thing the value cannot know about — the previous reading.

That is the payoff for Lesson 1.2. Every guard a value object holds is a guard every entity using it
does not have to repeat.

</details>

<details>
<summary>Hint 3 — the "not readonly" check</summary>

If you wrote `final readonly class Vehicle`, PHP will stop you before the checks do: assigning
`$this->odometer` in `recordOdometer()` throws "Cannot modify readonly property". That error is the
language telling you the entity/value distinction is real.

</details>

---

## When it passes

Compare with `solution/`. Look at:

- Whether they kept a reading history, and whether you agree it belongs here
- The wording of the `DomainException` message — does it tell the service desk what to do?
- Whether `registration()` returns the value object or a string, and what that costs at the boundary

---

## Then, without running anything

**Q1.** `Vehicle::equals()` compares identity. Your repository will later need to answer *"do we
already have this vehicle?"* when a customer arrives with a registration. That question cannot use
`equals()`. What is it actually asking, and where does it belong?

**Q2.** You made `VehicleId::generate()` produce identity without a database. Name one thing that
becomes possible because of this that would not be if the database assigned identity. Then name one
real cost.

**Q3.** The 2,000,000 km bound is called a sanity check rather than a business rule. Suppose the
workshop takes on a haulage contract and a truck arrives on 2.4 million km. What breaks, who finds
out, and what should have been done differently? This is Lesson 1.4's opening question.
