<?php
declare(strict_types=1);

/**
 * Example 01 — What floats and strings actually cost
 * ===================================================
 *
 * Five bugs, all of which have shipped in real systems, none of which is
 * exotic. Each one is possible only because a concept with rules was stored in
 * a type without them.
 *
 *     php 01-primitive-obsession.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 70) . "\n  {$title}\n" . str_repeat('=', 70) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
section('Bug 1 — Float money does not add up');

$total = 0.0;
for ($i = 0; $i < 10; $i++) {
    $total += 0.1;
}

printf("  Ten times 0.10 = %.20f\n", $total);
printf("  Is that exactly 1.0?  %s\n", $total === 1.0 ? 'yes' : 'NO');
printf("  Off by:               %.20f\n", abs($total - 1.0));

echo "\n  Now the same total in integer minor units:\n";
$totalMinor = 0;
for ($i = 0; $i < 10; $i++) {
    $totalMinor += 10;
}
printf("  Ten times 10 cents = %d cents = %s\n", $totalMinor, number_format($totalMinor / 100, 2));
printf("  Is that exactly 100 cents?  %s\n", $totalMinor === 100 ? 'yes' : 'NO');

echo "\n  On ten additions this is a rounding error. On a ledger with a million\n";
echo "  lines it is an amount somebody reconciles by hand every month, and\n";
echo "  nobody can tell them where it came from.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Bug 2 — A negative price');

/** The signature promises nothing. */
function invoiceFloat(float $amount, string $currency): string
{
    return sprintf('%s %.2f', $currency, $amount);
}

echo "  invoiceFloat(-4500.00, 'EUR') = " . invoiceFloat(-4500.00, 'EUR') . "\n";

echo "\n  There is no negative price in the workshop's business. The type says\n";
echo "  there is. Somewhere downstream, a credit note and a charge are now the\n";
echo "  same code path, distinguished by a sign nobody validated.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Bug 3 — Amount and currency drift apart');

$amount   = 4500.00;
$currency = 'EUR';

// Six months later, in a different file, converting for a report:
$amount = $amount * 1.08;          // "convert to USD"
// ...and the currency variable is thirty lines away, so it is not updated.

printf("  Amount: %.2f   Currency: %s\n", $amount, $currency);
echo "\n  4,860.00 EUR. It is neither: not the original amount, and not the\n";
echo "  currency it was converted into. Two variables that must agree can\n";
echo "  disagree, and this one will not throw, log or fail a test.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Bug 4 — Four spellings of one registration');

$spellings = ['AB12CDE', 'ab12cde', 'AB-12-CDE', '  ab 12 cde '];

echo "  Comparing every spelling with the first, using ===\n\n";
foreach ($spellings as $spelling) {
    printf(
        "    %-16s === %-10s  %s\n",
        "'" . $spelling . "'",
        "'AB12CDE'",
        $spelling === 'AB12CDE' ? 'true' : 'FALSE'
    );
}

echo "\n  As array keys — the shape this bug usually takes:\n\n";
$byRegistration = [];
foreach ($spellings as $spelling) {
    $byRegistration[$spelling] = 'job card for ' . $spelling;
}
printf("    %d distinct keys for one vehicle.\n", count($byRegistration));

echo "\n  A lookup by 'AB12CDE' finds one of the four, and which one depends on\n";
echo "  how the caller happened to type it.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Bug 5 — Arguments in the wrong order');

function bookIn(string $registration, string $accountReference): string
{
    return "Booked in {$registration} for {$accountReference}";
}

echo "  Correct:  " . bookIn('AB12CDE', 'ACC-4471') . "\n";
echo "  Swapped:  " . bookIn('ACC-4471', 'AB12CDE') . "\n";

echo "\n  Both compile. Both run. Both pass strict_types, because both really\n";
echo "  are strings. The type system cannot help, because you did not give it\n";
echo "  anything to work with.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('The same five, with value objects');

$fixes = [
    'Float money does not add up'      => 'Money stores integer minor units. int arithmetic is exact.',
    'A negative price'                 => 'Money::of() throws on a negative amount. There is no such value.',
    'Amount and currency drift apart'  => 'They are one object. There is nothing to keep in step.',
    'Four spellings of one plate'      => 'RegistrationNumber normalises at construction. One spelling.',
    'Arguments in the wrong order'     => 'bookIn(RegistrationNumber, AccountReference) will not compile swapped.',
];

foreach ($fixes as $bug => $fix) {
    printf("  %-34s %s\n", $bug, $fix);
}

echo "\n  Note what the last one costs: nothing at runtime. The check happens\n";
echo "  once, at the type boundary, and then never again — which is the whole\n";
echo "  argument for the type system as an enforcement mechanism rather than\n";
echo "  as documentation.\n\n";
