<?php

namespace Melasistema\MelaCms\Install;

use Illuminate\Filesystem\Filesystem;

/**
 * Removes the welcome route a fresh Laravel app has for `/` in routes/web.php,
 * so the home page chosen in the admin is served there. Pages are served by a
 * fallback route, which a site route for the same address always wins over.
 *
 * Only Laravel's own route is removed. A `/` route the site wrote is its home
 * page on purpose, and stays.
 */
final readonly class WelcomeRoute
{
    public const string REMOVED = 'removed';

    public const string KEPT = 'kept';

    public const string ABSENT = 'absent';

    /**
     * The stock route, as a closure, an arrow function or Route::view().
     */
    private const string STOCK = '/^Route::(?:get\(\s*([\'"])\/\1\s*,\s*(?:function\s*\(\)\s*\{\s*return\s+view\(\s*([\'"])welcome\2\s*\)\s*;\s*\}|fn\s*\(\)\s*=>\s*view\(\s*([\'"])welcome\3\s*\))\s*\)|view\(\s*([\'"])\/\4\s*,\s*([\'"])welcome\5\s*\));[ \t]*\n?/m';

    /**
     * Any other route of the site for `/`.
     */
    private const string OWN = '/Route::\w+\(\s*([\'"])\/\1\s*[,)]/';

    private const string IMPORT = "/^use Illuminate\\\\Support\\\\Facades\\\\Route;[ \t]*\n/m";

    /**
     * Left where the route was when nothing else remains in the file, so it
     * still says how the site's pages are served.
     */
    private const string NOTE = <<<'PHP'
        // Every address no route here matches is served by MelaCMS: `/` shows the
        // home page chosen in the admin, any other address the page at that slug.
        // A route here for the same address replaces the page.

        PHP;

    public function __construct(private Project $project, private Filesystem $files) {}

    /**
     * Returns REMOVED, KEPT when the site has its own route for `/`, or ABSENT
     * when there is no route for `/` (or no routes/web.php) at all.
     */
    public function remove(): string
    {
        $path = $this->project->path('routes/web.php');

        if (! $this->files->exists($path)) {
            return self::ABSENT;
        }

        $source = $this->files->get($path);
        $routes = preg_replace(self::STOCK, '', $source, 1, $count);

        if ($count === 0) {
            return preg_match(self::OWN, $source) ? self::KEPT : self::ABSENT;
        }

        // Pint refuses an unused import.
        if (! str_contains(preg_replace(self::IMPORT, '', $routes), 'Route::')) {
            $routes = preg_replace(self::IMPORT, '', $routes).self::NOTE;
        }

        $this->files->put($path, $this->tidy($routes));

        return self::REMOVED;
    }

    /**
     * No run of blank lines and a single newline at the end, as Pint expects.
     */
    private function tidy(string $source): string
    {
        return rtrim(preg_replace("/\n{3,}/", "\n\n", $source))."\n";
    }
}
