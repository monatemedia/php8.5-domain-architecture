<?php
declare(strict_types=1);

namespace Infrastructure;

/**
 * Every type a set of PHP files refers to by name.
 *
 * REFERENCE IMPLEMENTATION, and the course's thesis in executable form.
 *
 * WHAT IT IS FOR
 * ---------------
 * One assertion, made against a directory rather than a class:
 *
 *     the domain layer names nothing infrastructural
 *
 * Not "should not". Does not — checked, on every run, by something that reads
 * the files rather than trusting a convention. Every other check in this course
 * asserts a behaviour; this one asserts a SHAPE, and it is the only one that
 * keeps being true as the codebase grows past what any one person has read.
 *
 * WHY A SCAN AND NOT A CONVENTION
 * --------------------------------
 * "The domain must not import infrastructure" is a rule with no enforcement,
 * and rules with no enforcement decay in a predictable way: not in a big
 * violation somebody would notice, but in one `use Infrastructure\Clock;` added
 * at 5pm because the alternative was an interface and a container binding.
 *
 * Nobody reviews that line. It is one line. Six months later the domain does
 * not compile without the framework and nobody can say which change did it.
 *
 * This class turns the rule into a build failure, which is the only form of
 * architectural rule that survives contact with a deadline.
 *
 * IT READS TOKENS, NOT REGULAR EXPRESSIONS
 * -----------------------------------------
 * `token_get_all()` rather than a grep, and this project has three separate
 * scars from getting that wrong — recorded in `RUN-THIS-FIRST.md` as bugs 3, 7
 * and the rule that came out of them:
 *
 *     A check that reads source is a parser with the parsing left out.
 *
 * A grep for `Infrastructure` cannot tell a dependency from a doc block that
 * explains the dependency rule. A grep for `PartStock` cannot tell it from
 * `PartStockRow`. Tokens can tell the difference between all of those without
 * being told to, because the tokeniser has already done the work.
 *
 * WHAT COUNTS AS A REFERENCE
 * ---------------------------
 *   - a `use` statement — `use Workshop\JobCards;`
 *   - a fully-qualified name anywhere in the file — `\PDO`, `\Slim\App`
 *
 * Both matter. A file with no `use` statements can still type-hint `\PDO
 * $connection`, and a scan that only reads imports would call it clean.
 *
 * WHAT IT DELIBERATELY DOES NOT CATCH
 * ------------------------------------
 * A class named as a string: `$container->get('Slim\App')`,
 * `new ('Slim\\' . $x)`. Nothing short of running the code finds those, and a
 * domain class building class names from strings has a larger problem than this
 * scan.
 *
 * Worth knowing where the edge is. A check whose limits you cannot state is a
 * check you are trusting rather than using.
 */
final readonly class ImportScan
{
    /**
     * Anything whose presence in the domain means the layer has leaked.
     *
     * The list is short and it is meant to be: these are the things this course
     * actually depends on, plus the two ORMs a reader is most likely to add.
     * A longer list is not a stronger check — the useful entries are
     * `Infrastructure\` and `PDO`, because those are the ones somebody adds by
     * accident.
     *
     * @var list<string>
     */
    public const array INFRASTRUCTURAL = [
        'Infrastructure\\',   // the course's own outer layer
        'PDO',                // ext-pdo: PDO, PDOStatement, PDOException
        'Slim\\',             // the framework
        'Psr\\',              // PSR-7 and PSR-15 are HTTP, and HTTP is transport
        'DI\\',               // PHP-DI
        'PHPUnit\\',          // a domain class importing a test framework
        'Doctrine\\',
        'Illuminate\\',
    ];

    /** @param array<string, list<string>> $referencesByFile */
    private function __construct(private array $referencesByFile) {}

    /**
     * Scan every PHP file under these directories.
     *
     * SKIPS any directory segment named `infrastructure` or `given`. In this
     * course a reference solution keeps its own infrastructure beside it, and
     * scanning that would report the outer layer for being the outer layer.
     * A real project has one `domain/` and needs neither exclusion.
     */
    public static function of(string ...$directories): self
    {
        $referencesByFile = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());

                if (str_contains($path, '/infrastructure/') || str_contains($path, '/given/')) {
                    continue;
                }

                $referencesByFile[$path] = self::referencesIn((string) file_get_contents($path));
            }
        }

        ksort($referencesByFile);

        return new self($referencesByFile);
    }

    public function fileCount(): int
    {
        return count($this->referencesByFile);
    }

    /** @return list<string> every file scanned, forward-slashed */
    public function files(): array
    {
        return array_keys($this->referencesByFile);
    }

    /**
     * Every distinct type named anywhere in the scanned files.
     *
     * @return list<string>
     */
    public function references(): array
    {
        $all = array_merge(...array_values($this->referencesByFile)) ?: [];

        $unique = array_values(array_unique($all));
        sort($unique);

        return $unique;
    }

    /**
     * file -> the infrastructural names it refers to.
     *
     * Empty is the passing state. When it is not empty it names the file and
     * the import, which is the whole difference between a useful architectural
     * test and one that says "the domain is impure".
     *
     * @param list<string>|null $forbidden defaults to INFRASTRUCTURAL
     *
     * @return array<string, list<string>>
     */
    public function violations(?array $forbidden = null): array
    {
        $forbidden ??= self::INFRASTRUCTURAL;
        $violations = [];

        foreach ($this->referencesByFile as $file => $references) {
            $offending = array_values(array_filter(
                $references,
                static function (string $reference) use ($forbidden): bool {
                    foreach ($forbidden as $prefix) {
                        if (str_starts_with($reference, $prefix)) {
                            return true;
                        }
                    }

                    return false;
                }
            ));

            if ($offending !== []) {
                $violations[$file] = $offending;
            }
        }

        return $violations;
    }

    public function isClean(): bool
    {
        return $this->violations() === [];
    }

    /** A one-line summary, for a failing assertion to print. */
    public function report(): string
    {
        $violations = $this->violations();

        if ($violations === []) {
            return sprintf('%d files scanned, no infrastructural references.', $this->fileCount());
        }

        $lines = [];

        foreach ($violations as $file => $offending) {
            $lines[] = sprintf('%s -> %s', basename($file), implode(', ', $offending));
        }

        return implode('; ', $lines);
    }

    // ── The tokeniser ───────────────────────────────────────────────────────

    /**
     * @return list<string> every type this source names, without leading slashes
     */
    private static function referencesIn(string $source): array
    {
        $references = [];
        $tokens     = token_get_all($source);
        $count      = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                continue;
            }

            // `\PDO`, `\Slim\App`, `#[\NoDiscard]` — anywhere at all.
            if ($token[0] === T_NAME_FULLY_QUALIFIED) {
                $references[] = ltrim($token[1], '\\');
                continue;
            }

            if ($token[0] !== T_USE) {
                continue;
            }

            $i = self::readUseStatement($tokens, $i, $count, $references);
        }

        return $references;
    }

    /**
     * Read one `use` statement from position $i, appending what it imports.
     *
     * Handles the three shapes that matter and skips the one that is not an
     * import at all:
     *
     *     use Workshop\JobCards;              a name
     *     use Workshop\Money as Cash;         an alias — the ORIGINAL is the
     *                                         reference; the alias is a local
     *                                         nickname and means nothing here
     *     use RecordsDomainEvents;            a trait, inside a class body
     *     function () use ($x) { ... }        a closure. NOT an import.
     *
     * @param list<array{0:int,1:string}|string> $tokens
     * @param list<string>                       $references
     *
     * @return int the index to continue from
     */
    private static function readUseStatement(array $tokens, int $i, int $count, array &$references): int
    {
        $current = '';
        $aliased = false;

        for ($j = $i + 1; $j < $count; $j++) {
            $token = $tokens[$j];

            if (!is_array($token)) {
                // A closure's `use (...)`. Not an import; leave it alone.
                if ($token === '(') {
                    return $j;
                }

                if ($token === ',') {
                    if ($current !== '') {
                        $references[] = $current;
                    }

                    $current = '';
                    $aliased = false;
                    continue;
                }

                if ($token === ';' || $token === '{') {
                    if ($current !== '') {
                        $references[] = $current;
                    }

                    return $j;
                }

                continue;
            }

            // Everything after `as` is a local nickname. Stop recording.
            if ($token[0] === T_AS) {
                $aliased = true;
                continue;
            }

            if ($aliased) {
                continue;
            }

            if (in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING], true)) {
                $current = ltrim($token[1], '\\');
            }
        }

        return $count;
    }
}
