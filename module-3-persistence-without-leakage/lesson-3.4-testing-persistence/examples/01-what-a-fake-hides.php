<?php
declare(strict_types=1);

/**
 * Example 01 — What a fake hides
 * ===============================
 *
 * An in-memory repository satisfies several promises BY ACCIDENT, because an
 * array happens to behave that way. A database does not, and a test suite
 * written only against the fake goes green while production is wrong.
 *
 * Four drifts, run. The last one is the one this course cannot fix.
 *
 *     php 01-what-a-fake-hides.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}


// ═══════════════════════════════════════════════════════════════════════════
section('Drift 1 — Ordering');

$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE items (id TEXT PRIMARY KEY, label TEXT NOT NULL)');

$labels = ['Diagnostic', 'Front pads', 'Rear discs', 'Oil filter'];

// The array version. Order is a property of the array.
$inMemory = [];
foreach ($labels as $i => $label) {
    $inMemory['LN-' . $i] = $label;
}

// The table version, inserted in the same order.
$insert = $pdo->prepare('INSERT INTO items (id, label) VALUES (:id, :label)');
foreach ($labels as $i => $label) {
    $insert->execute(['id' => 'LN-' . $i, 'label' => $label]);
}

echo "  in-memory, no ordering asked for:\n    " . implode(', ', array_values($inMemory)) . "\n";

$rows = $pdo->query('SELECT label FROM items')->fetchAll(PDO::FETCH_COLUMN);
echo "  sqlite,    no ORDER BY:\n    " . implode(', ', $rows) . "\n";

echo "\n  They agree — today, on this schema, with this planner. That agreement\n";
echo "  is not a promise. Add an index, update a row in place, or change\n";
echo "  engine, and the second line can reorder without anything failing.\n\n";
echo "  JobCard::lines() PROMISES insertion order. An array gives it away free;\n";
echo "  a table needs a position column and an ORDER BY, and the only reason\n";
echo "  anybody writes one is a contract test that asserts ordering.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Drift 2 — Uniqueness');

echo "  In-memory: a duplicate key silently REPLACES unless you guard it.\n\n";

$naive = [];
$naive['JC-1001'] = 'first';
$naive['JC-1001'] = 'second';
printf("    after two writes to the same key: %s (one entry, %d total)\n", $naive['JC-1001'], count($naive));

echo "\n  SQLite: the constraint refuses.\n\n";
try {
    $insert->execute(['id' => 'LN-0', 'label' => 'duplicate']);
    echo "    [!!] accepted\n";
} catch (PDOException $e) {
    echo "    [refused] " . explode("\n", $e->getMessage())[0] . "\n";
}

echo "\n  A fake without an explicit guard is MORE PERMISSIVE than the real\n";
echo "  thing — so a test proving 'adding twice is refused' passes against\n";
echo "  SQLite and silently does nothing against the fake. That is why\n";
echo "  InMemoryJobCards::add() has a guard it does not strictly need.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Drift 3 — Types');

$pdo->exec('CREATE TABLE amounts (id TEXT PRIMARY KEY, minor INTEGER NOT NULL)');
$pdo->prepare('INSERT INTO amounts (id, minor) VALUES (:id, :minor)')
    ->execute(['id' => 'A', 'minor' => 10450]);

$row = $pdo->query('SELECT minor FROM amounts')->fetch(PDO::FETCH_ASSOC);

printf("  stored an int, read back a %s: %s\n", get_debug_type($row['minor']), var_export($row['minor'], true));

echo "\n  Depending on driver and settings, integers can come back as strings.\n";
echo "  In-memory, an int is an int. Money::of() takes an int, so without a\n";
echo "  cast the mapper would hit a TypeError only on the real repository —\n";
echo "  which is why JobCardMapper casts every column on the way in.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('Drift 4 — The one this course cannot fix');

echo "  In-memory holds a REFERENCE. Change an aggregate after add() and the\n";
echo "  repository already agrees:\n\n";

$store = ['JC-1001' => (object) ['status' => 'in_progress']];
$loaded = $store['JC-1001'];
$loaded->status = 'completed';
printf("    in-memory, after mutating what add() was given: %s\n", $store['JC-1001']->status);

echo "\n  SQLite wrote rows at add() time and has no idea anything changed:\n\n";
$pdo->exec("CREATE TABLE cards (id TEXT PRIMARY KEY, status TEXT NOT NULL)");
$pdo->exec("INSERT INTO cards VALUES ('JC-1001', 'in_progress')");
// ...the object changes in memory. Nothing tells the database.
$stored = $pdo->query("SELECT status FROM cards WHERE id = 'JC-1001'")->fetchColumn();
printf("    sqlite, after the same mutation:               %s\n", $stored);

echo "\n  THE TWO IMPLEMENTATIONS GENUINELY DIVERGE, and no assertion written\n";
echo "  against JobCards can hide it.\n\n";
echo "  That is not a bug in either class. It is the interface being\n";
echo "  collection-oriented — add() and remove(), no save() — which Lesson 3.1\n";
echo "  chose deliberately and warned about in the same breath. There is no\n";
echo "  moment at which the SQLite implementation is told to write.\n\n";
echo "  The honest options are a unit of work that tracks loaded aggregates\n";
echo "  and diffs them, or an explicit save() that puts one word of storage\n";
echo "  vocabulary back into the caller. Most PHP codebases take the second.\n\n";
echo "  The contract test is written NOT TO DEPEND on this, and says so. What\n";
echo "  it must never do is pretend the divergence is not there.\n\n";
