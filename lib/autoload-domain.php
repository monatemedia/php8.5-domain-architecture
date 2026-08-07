<?php
declare(strict_types=1);

/**
 * lib/autoload-domain.php — a nine-line PSR-4 autoloader
 * =======================================================
 *
 * The whole course lives under one namespace root, `Workshop\`, and every
 * challenge needs to load those classes from one of two places:
 *
 *   - `domain/workshop/`            ← YOUR code, the one you build up
 *   - `<lesson>/challenge/solution/` ← the reference implementation
 *
 * So the root is a parameter rather than a constant:
 *
 *     require_once __DIR__ . '/../../../lib/autoload-domain.php';
 *     workshop_autoload(__DIR__ . '/../../../domain/workshop');
 *
 * `Workshop\Money`            → <root>/Money.php
 * `Workshop\Exception\Boom`   → <root>/Exception/Boom.php
 *
 * Composer could do this. It is written out by hand deliberately: you should be
 * able to see that autoloading is a `spl_autoload_register` callback and a
 * `str_replace`, not magic. Nothing else in the course depends on Composer for
 * the domain layer, which is the whole point — see COURSE_PHILOSOPHY.md, Rule 1.
 */

if (!function_exists('workshop_autoload')) {

    /**
     * Register `$root` as a source of `Workshop\*` classes.
     *
     * Calling this more than once is safe and additive: every registered root is
     * tried in registration order, and the first file that exists wins. That
     * matters in later modules, where a lesson supplies a couple of prewritten
     * classes alongside the ones you are asked to write.
     */
    function workshop_autoload(string $root): void
    {
        static $roots = [];
        static $registered = false;

        $real = realpath($root);
        if ($real === false) {
            // Not an error. A challenge legitimately points at a directory you
            // have not created yet; the missing-class message downstream is far
            // more useful than a failure here.
            return;
        }

        if (in_array($real, $roots, true)) {
            return;
        }
        $roots[] = $real;

        if ($registered) {
            return;
        }
        $registered = true;

        spl_autoload_register(static function (string $class) use (&$roots): void {
            if (!str_starts_with($class, 'Workshop\\')) {
                return;
            }
            $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen('Workshop\\')));
            foreach ($roots as $dir) {
                $file = $dir . DIRECTORY_SEPARATOR . $relative . '.php';
                if (is_file($file)) {
                    require_once $file;
                    return;
                }
            }
        });
    }
}
