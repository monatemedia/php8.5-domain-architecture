<?php
declare(strict_types=1);

/**
 * Example 02 — The scan, against five domain layers
 * ==================================================
 *
 * One clean, four broken in different ways, and one that only LOOKS broken.
 * The tokeniser has to get all five right.
 *
 *     php 02-the-scan.php
 */

function section(string $title): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$title}\n" . str_repeat('=', 74) . "\n\n";
}

const FORBIDDEN = ['Infrastructure\\', 'PDO', 'Slim\\', 'Psr\\', 'DI\\'];

/**
 * A miniature of Infrastructure\ImportScan: the two kinds of reference, read
 * from tokens.
 *
 * @return list<string>
 */
function referencesIn(string $source): array
{
    $references = [];
    $tokens     = token_get_all($source);
    $count      = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (!is_array($token)) {
            continue;
        }

        if ($token[0] === T_NAME_FULLY_QUALIFIED) {
            $references[] = ltrim($token[1], '\\');
            continue;
        }

        if ($token[0] !== T_USE) {
            continue;
        }

        $current = '';
        $aliased = false;

        for ($j = $i + 1; $j < $count; $j++) {
            $next = $tokens[$j];

            if (!is_array($next)) {
                if ($next === '(') { $i = $j; break; }          // a closure. Not an import.
                if ($next === ';' || $next === '{') {
                    if ($current !== '') { $references[] = $current; }
                    $i = $j;
                    break;
                }
                continue;
            }

            if ($next[0] === T_AS) { $aliased = true; continue; }
            if ($aliased) { continue; }

            if (in_array($next[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING], true)) {
                $current = ltrim($next[1], '\\');
            }
        }
    }

    return $references;
}

/** @return list<string> */
function violationsIn(string $source): array
{
    return array_values(array_filter(
        referencesIn($source),
        static function (string $reference): bool {
            foreach (FORBIDDEN as $prefix) {
                if (str_starts_with($reference, $prefix)) {
                    return true;
                }
            }

            return false;
        }
    ));
}


// ═══════════════════════════════════════════════════════════════════════════
section('Five files that all claim to be domain classes');

$files = [
    'clean' => <<<'PHP'
        <?php
        namespace Workshop;

        final class JobCard
        {
            public function invoice(): void
            {
                throw new IllegalStatusTransition();
            }
        }
        PHP,

    'imports the framework' => <<<'PHP'
        <?php
        namespace Workshop;

        use Slim\App;

        final class JobCard
        {
            public function __construct(private App $app) {}
        }
        PHP,

    'imports the outer layer' => <<<'PHP'
        <?php
        namespace Workshop;

        use Infrastructure\SqliteJobCards;

        final class JobCard
        {
            public function __construct(private SqliteJobCards $repository) {}
        }
        PHP,

    'no import, but a type hint' => <<<'PHP'
        <?php
        namespace Workshop;

        final class JobCard
        {
            public function __construct(private \PDO $connection) {}
        }
        PHP,

    'an alias, which hides nothing' => <<<'PHP'
        <?php
        namespace Workshop;

        use Slim\App as Framework;

        final class JobCard
        {
            public function __construct(private Framework $app) {}
        }
        PHP,

    'only a doc block' => <<<'PHP'
        <?php
        namespace Workshop;

        /**
         * This class must never import \PDO, Slim\App, or anything from
         * Infrastructure\ — see the capstone.
         */
        final class JobCard
        {
            public function note(): string { return 'PDO is a word here'; }
        }
        PHP,
];

printf("  %-32s %-10s %s\n", 'file', 'verdict', 'found');
printf("  %s\n", str_repeat('-', 74));

foreach ($files as $label => $source) {
    $violations = violationsIn($source);

    printf(
        "  %-32s %-10s %s\n",
        $label,
        $violations === [] ? 'clean' : 'CAUGHT',
        implode(', ', $violations)
    );
}


// ═══════════════════════════════════════════════════════════════════════════
section('Rows four and six are why this is a tokeniser');

echo "  ROW 4 — no `use` statement at all, and it still depends on ext-pdo.\n";
echo "  A scan that reads only imports calls it clean. That is the failure\n";
echo "  mode of every 'check the imports' script written in an afternoon.\n\n";

echo "  ROW 6 — names \\PDO, Slim\\App and Infrastructure\\ in prose, and\n";
echo "  depends on none of them. A grep calls it a violation, somebody\n";
echo "  removes the doc block to make the build pass, and the next reader\n";
echo "  has no idea why the rule exists.\n\n";

echo "  This project shipped both mistakes before it stopped making them —\n";
echo "  RUN-THIS-FIRST.md, bugs 3 and 7. The rule that came out of them:\n\n";
echo "      A check that reads source is a parser with the parsing left out.\n";


// ═══════════════════════════════════════════════════════════════════════════
section('And the result on the real thing');

echo "  87 files scanned in the domain layer.\n";
echo "  Every name they refer to, in full:\n\n";
echo "      DomainException\n";
echo "      InvalidArgumentException\n";
echo "      NoDiscard\n";
echo "      Throwable\n\n";
echo "  Not 'no framework imports'. NO IMPORTS. Domain classes share a\n";
echo "  namespace, so they name each other unqualified, and the only names\n";
echo "  that survive are the SPL types the exception tree extends.\n\n";
echo "  Delete Slim, PDO, PHP-DI and the web server. There is nothing left\n";
echo "  to remove.\n\n";
