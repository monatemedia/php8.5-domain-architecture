<?php
declare(strict_types=1);

/**
 * Example 02 — The same three rungs, in both shapes PHP uses
 * ===========================================================
 *
 * Active Record (Eloquent, CodeIgniter, Rails) and Data Mapper (Doctrine).
 * The DECISION is identical in both. The mechanism is not, and the cost of
 * rung 5 differs by a factor of about ten.
 *
 * Part 1: rung 1 — stopping the setter.
 * Part 2: rung 3 — a value object over columns.
 * Part 3: rung 5, and why a Doctrine codebase usually should not pay for it.
 *
 *     php 02-two-shapes.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

function attempt(string $label, callable $fn): void
{
    try {
        $fn();
        printf("      %-44s allowed\n", $label);
    } catch (Throwable $e) {
        printf("      %-44s REFUSED (%s)\n", $label, (new ReflectionClass($e))->getShortName());
    }
}


// ═══════════════════════════════════════════════════════════════════════════
// PART 1 — Rung 1
// ═══════════════════════════════════════════════════════════════════════════
section('Part 1 — stopping the setter');

echo "  ACTIVE RECORD: there is no property to make private. The attributes\n";
echo "  live in an array and __set writes any of them. You have to take over\n";
echo "  the magic.\n\n";

final class ArJobCard
{
    private const array GUARDED = ['status'];

    public function __construct(private array $attributes = []) {}

    public function __get(string $n): mixed { return $this->attributes[$n] ?? null; }

    public function __set(string $n, mixed $v): void
    {
        if (in_array($n, self::GUARDED, true)) {
            throw new LogicException("{$n} is guarded — use complete().");
        }

        $this->attributes[$n] = $v;
    }

    public function complete(): void { $this->attributes['status'] = 'completed'; }

    public function status(): string { return (string) $this->attributes['status']; }
}

$ar = new ArJobCard(['status' => 'in_progress']);
attempt('$ar->status = "completed"', static function () use ($ar): void { $ar->status = 'completed'; });
attempt('$ar->complete()', static fn() => $ar->complete());
printf("      -> status is %s\n", $ar->status());

echo "\n  DATA MAPPER: the properties are already private, because the mapper\n";
echo "  populates them by reflection. There is nothing to stop — you simply\n";
echo "  do not add a setter.\n\n";

final class DmJobCard
{
    private string $status = 'in_progress';

    public function complete(): void { $this->status = 'completed'; }

    public function status(): string { return $this->status; }
}

$dm = new DmJobCard();
attempt('$dm->status = "completed"', static function () use ($dm): void {
    /** @phpstan-ignore-next-line — deliberate: the property is private. */
    $dm->status = 'completed';
});
attempt('$dm->complete()', static fn() => $dm->complete());
printf("      -> status is %s\n", $dm->status());

echo "\n  Note WHICH error each produced. The Active Record model threw a\n";
echo "  LogicException you wrote, at runtime. The Data Mapper entity threw an\n";
echo "  Error from the engine, because private means private.\n\n";
echo "  Same outcome, and one of them cannot be forgotten, mistyped or left\n";
echo "  out of the GUARDED list.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 2 — Rung 3
// ═══════════════════════════════════════════════════════════════════════════
section('Part 2 — a value object over the same two columns');

echo "  ACTIVE RECORD — an accessor, or a cast:\n\n";
echo "      public function total(): Money\n";
echo "      {\n";
echo "          return Money::of(\$this->attributes['total_cents'],\n";
echo "                           Currency::from(\$this->attributes['currency']));\n";
echo "      }\n\n";
echo "      // Eloquent:  protected \$casts = ['total' => MoneyCast::class];\n\n";

echo "  DATA MAPPER — an embeddable, or a custom type:\n\n";
echo "      #[ORM\\Embedded(class: Money::class)]\n";
echo "      private Money \$total;\n\n";

echo "  Both leave the SCHEMA alone: two scalar columns, exactly as they were.\n";
echo "  Both stop callers doing money arithmetic with +.\n\n";
echo "  The difference is one line in a docblock versus one method, and it is\n";
echo "  not worth choosing a framework over.\n";


// ═══════════════════════════════════════════════════════════════════════════
// PART 3 — Rung 5
// ═══════════════════════════════════════════════════════════════════════════
section('Part 3 — rung 5, where the two shapes stop being comparable');

$comparison = [
    ['a second model class',        'yes',  'no'],
    ['a mapper, kept in step',      'yes',  'no'],
    ['save() on the domain object', 'yes',  'no — the entity never had one'],
    ['static finders to hide',      'yes',  'no — repositories already exist'],
    ['magic attributes to fight',   'yes',  'no — properties are private'],
    ['ORM annotations on the class', 'no',  'yes'],
    ['an ORM Collection inside',    'no',   'usually'],
];

printf("  %-32s %-10s %s\n", 'rung 5 requires', 'Active R.', 'Data Mapper');
printf("  %s\n", str_repeat('-', 66));

foreach ($comparison as [$item, $ar, $dm]) {
    printf("  %-32s %-10s %s\n", $item, $ar, $dm);
}

echo "\n  Read the last two rows before concluding Doctrine wins outright.\n";
echo "  The cost there is real: ORM attributes on a domain class, and a\n";
echo "  Collection type from the ORM inside your aggregate. Both are\n";
echo "  infrastructural things in a domain file.\n\n";
echo "  But they are SMALL, and they buy you the top five rows for nothing.\n\n";
echo "  So the honest advice differs by stack:\n\n";
echo "      Eloquent   rungs 1-4. Rung 5 only with a signal from Module 0,\n";
echo "                 and expect the framework to fight you all the way.\n\n";
echo "      Doctrine   put the rules on the entity, keep the repository\n";
echo "                 interface in the domain, and do NOT build a second\n";
echo "                 model. The entity is already most of an aggregate.\n\n";
echo "  That is the same decision — Rule 6, ceremony must be paid for —\n";
echo "  reaching two different answers because the price is different.\n\n";
