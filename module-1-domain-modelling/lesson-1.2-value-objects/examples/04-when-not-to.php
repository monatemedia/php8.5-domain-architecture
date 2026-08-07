<?php
declare(strict_types=1);

/**
 * Example 04 — When NOT to make a value object
 * =============================================
 *
 * Module 1 is where people who have just read about DDD do the most damage.
 * This example is the counterweight, and it is here on purpose: a lesson that
 * only shows the pattern working produces developers who apply it everywhere.
 *
 * Six candidates, scored by one question: CAN YOU NAME A RULE THIS TYPE
 * ENFORCES THAT THE PRIMITIVE DOES NOT?
 *
 *     php 04-when-not-to.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
section('Six candidates');

/** @var list<array{name:string, rules:list<string>, verdict:string, note:string}> */
$candidates = [
    [
        'name'    => 'Money',
        'rules'   => [
            'never negative',
            'never mixes currencies',
            'exact arithmetic (integer minor units)',
            'knows its own decimal precision',
        ],
        'verdict' => 'YES',
        'note'    => 'Four rules, all with money attached. The easiest yes there is.',
    ],
    [
        'name'    => 'RegistrationNumber',
        'rules'   => [
            'normalised to one spelling',
            'format enforced at construction',
            'equality by normalised form',
        ],
        'verdict' => 'YES',
        'note'    => 'Without it, the normalisation is duplicated at every comparison.',
    ],
    [
        'name'    => 'EmailAddress',
        'rules'   => [
            'format enforced once',
            'lowercased for comparison',
        ],
        'verdict' => 'USUALLY',
        'note'    => 'Compared constantly, and the lowercase step is forgotten constantly.',
    ],
    [
        'name'    => 'VehicleColour',
        'rules'   => [],
        'verdict' => 'NO',
        'note'    => 'Free text from a dropdown. You have wrapped a string in a file.',
    ],
    [
        'name'    => 'FaultDescription',
        'rules'   => ['not empty'],
        'verdict' => 'NO',
        'note'    => '"Not empty" does not justify a type. Guard it where it is used.',
    ],
    [
        'name'    => 'PageNumber',
        'rules'   => ['greater than zero'],
        'verdict' => 'NO',
        'note'    => 'An int that is positive. A named argument says it better and costs nothing.',
    ],
];

foreach ($candidates as $c) {
    printf("  %-22s %-8s %d rule(s)\n", $c['name'], $c['verdict'], count($c['rules']));
    foreach ($c['rules'] as $rule) {
        echo "      - {$rule}\n";
    }
    if ($c['rules'] === []) {
        echo "      (none)\n";
    }
    echo "      {$c['note']}\n\n";
}

$yes = count(array_filter($candidates, static fn(array $c): bool => $c['verdict'] === 'YES'));
printf("  Unqualified yes: %d of %d.\n", $yes, count($candidates));

echo "\n  If your own list comes out at six of six, you are not applying the\n";
echo "  question — you are applying the pattern.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('What the bad ones actually cost');

/** The value object nobody needed. */
final readonly class VehicleColour
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Colour cannot be empty.');
        }
        return new self(trim($value));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

$colour = VehicleColour::fromString('Metallic Blue');
echo "  It works: {$colour}\n\n";

$costs = [
    'A file'                    => 'domain/workshop/VehicleColour.php, forever',
    'A name to learn'           => 'every new developer asks what it is for',
    'A mapping'                 => 'to and from the database column, both ways',
    'A conversion per boundary' => 'HTTP in, HTTP out, CSV import, report export',
    'A test file'               => 'testing that a string is a string',
];

foreach ($costs as $cost => $detail) {
    printf("  %-28s %s\n", $cost, $detail);
}

echo "\n  Bought, in exchange: 'it is not empty'.\n\n";
echo "  That is a bad trade, and it is a bad trade that looks like good\n";
echo "  practice — which is what makes it worth an example rather than a\n";
echo "  footnote. Nobody reviewing this pull request will object.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('The middle option people forget');

echo "  It is not value-object-or-nothing. There is a cheap middle:\n\n";

echo "    1. A named argument at the call site\n";
echo "         bookIn(registration: \$reg, odometer: \$km)\n";
echo "       Costs nothing, fixes argument-order bugs, no new type.\n\n";

echo "    2. Normalise once, at ONE boundary\n";
echo "         \$registration = normaliseRegistration(\$request['reg']);\n";
echo "       One function, called where input arrives. Much better than eleven\n";
echo "       duplicated strtoupper() calls; much worse than a type, because\n";
echo "       nothing forces anyone to call it.\n\n";

echo "    3. A first-class enum, where the set is closed\n";
echo "         enum ServiceLevel: string {\n";
echo "             case Standard = 'standard';\n";
echo "             case Express  = 'express';\n";
echo "         }\n";
echo "       If the valid values are a fixed list, an enum is cheaper than a\n";
echo "       value object and enforces more.\n\n";

echo "  Option 3 is the one most under-used. Reach for an enum before you reach\n";
echo "  for a value object: it is less code, and the compiler does the work.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('The test, in one line');

echo "  Can you name a rule this type enforces that the primitive does not?\n\n";
echo "    Yes, and it has money or a regulator attached      -> build it\n";
echo "    Yes, and it is duplicated in several places        -> build it\n";
echo "    Yes, but it is only 'not empty'                    -> do not\n";
echo "    No, but it reads better                            -> usually do not\n\n";
echo "  That last line is the contentious one. Expressiveness is a real\n";
echo "  benefit and this course is claiming it is a small one — smaller than\n";
echo "  the file, the mapping, the conversions and the name. Disagree if you\n";
echo "  like, but disagree explicitly, and write down where the line is.\n\n";
