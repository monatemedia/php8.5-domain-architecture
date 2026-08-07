<?php
declare(strict_types=1);

/**
 * lib/autoload-domain.php — a small PSR-4 autoloader
 * ===================================================
 *
 * The course has two namespace roots, and keeping them apart is the point:
 *
 *     Workshop\        -> domain/workshop/        the domain layer
 *     Infrastructure\  -> infrastructure/         everything that is not
 *
 * Each maps to a directory that is a PARAMETER rather than a constant, because
 * the same checks run against two different trees — your code, or the reference
 * implementation beside the challenge:
 *
 *     require_once __DIR__ . '/../../../lib/autoload-domain.php';
 *     workshop_autoload($root . '/domain/workshop');
 *     infrastructure_autoload($root . '/infrastructure');
 *
 * `Workshop\Money`                     -> <root>/Money.php
 * `Workshop\Exception\Boom`            -> <root>/Exception/Boom.php
 * `Infrastructure\InMemoryJobCards`    -> <infra>/InMemoryJobCards.php
 *
 * Composer could do all of this, and does — see composer.json. It is written
 * out by hand as well so that you can see autoloading is a
 * `spl_autoload_register` callback and a `str_replace`, not magic. Nothing in
 * the domain layer depends on Composer, which is the whole point.
 *
 * ON THE SECOND ROOT, ADDED IN LESSON 3.1
 * ----------------------------------------
 * Until Module 3 there was nothing to put outside the domain, so there was one
 * root and one function. A repository IMPLEMENTATION is the first thing this
 * course writes that is unambiguously infrastructure, and it needs a home that
 * the capstone's import-scanning test can tell apart from the domain.
 *
 * `workshop_autoload()` behaves exactly as before. Every existing starter and
 * runner keeps working unchanged.
 */

if (!function_exists('psr4_autoload')) {

    /**
     * Register `$root` as a source of classes under `$prefix`.
     *
     * Calling this more than once for the same prefix is safe and ADDITIVE:
     * every registered root is tried in registration order and the first file
     * that exists wins. Later lessons rely on that — a reference runner
     * registers its own directory first so that its version of a class beats
     * the earlier one it replaced.
     *
     * @param string $prefix e.g. 'Workshop\\' — trailing separator required
     */
    function psr4_autoload(string $prefix, string $root): void
    {
        /** @var array<string, list<string>> $roots prefix => directories, in order */
        static $roots = [];
        static $registered = false;

        $real = realpath($root);

        if ($real === false) {
            // Not an error. A challenge legitimately points at a directory you
            // have not created yet, and the missing-class message downstream is
            // far more useful than a failure here.
            return;
        }

        $roots[$prefix] ??= [];

        if (in_array($real, $roots[$prefix], true)) {
            return;
        }

        $roots[$prefix][] = $real;

        if ($registered) {
            return;
        }
        $registered = true;

        spl_autoload_register(static function (string $class) use (&$roots): void {
            foreach ($roots as $prefix => $directories) {
                if (!str_starts_with($class, $prefix)) {
                    continue;
                }

                $relative = str_replace(
                    '\\',
                    DIRECTORY_SEPARATOR,
                    substr($class, strlen($prefix))
                );

                foreach ($directories as $directory) {
                    $file = $directory . DIRECTORY_SEPARATOR . $relative . '.php';

                    if (is_file($file)) {
                        require_once $file;
                        return;
                    }
                }
            }
        });
    }

    /** The domain layer. */
    function workshop_autoload(string $root): void
    {
        psr4_autoload('Workshop\\', $root);
    }

    /** Everything that is not the domain layer. Added in Lesson 3.1. */
    function infrastructure_autoload(string $root): void
    {
        psr4_autoload('Infrastructure\\', $root);
    }
}
