<?php

use Melasistema\MelaCms\Install\WelcomeRoute;

// routes/web.php as shipped by `laravel new` (Laravel 13).
const STOCK_WEB_ROUTES = <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

PHP;

beforeEach(function () {
    mkdir($this->projectFile('routes'));
});

function writeRoutes(string $source): void
{
    file_put_contents(test()->projectFile('routes/web.php'), $source);
}

function routes(): string
{
    return file_get_contents(test()->projectFile('routes/web.php'));
}

function routesPassPint(): bool
{
    exec(escapeshellarg(__DIR__.'/../../vendor/bin/pint').' --test --preset laravel '.escapeshellarg(test()->projectFile('routes/web.php')).' 2>&1', $output, $exitCode);

    return $exitCode === 0;
}

it('removes the stock welcome route and leaves a file Pint accepts', function () {
    writeRoutes(STOCK_WEB_ROUTES);

    expect(app(WelcomeRoute::class)->remove())->toBe(WelcomeRoute::REMOVED)
        ->and(routes())->not->toContain('welcome')
        ->not->toContain('use Illuminate\Support\Facades\Route;')
        ->toContain('home page chosen in the admin')
        ->and(routesPassPint())->toBeTrue();
});

it('keeps the other routes of the site and their import', function () {
    writeRoutes(str_replace("});\n", "});\n\nRoute::get('/services', fn () => 'services');\n", STOCK_WEB_ROUTES));

    expect(app(WelcomeRoute::class)->remove())->toBe(WelcomeRoute::REMOVED)
        ->and(routes())->toBe("<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/services', fn () => 'services');\n")
        ->and(routesPassPint())->toBeTrue();
});

it('removes the welcome route written in another form', function (string $route) {
    writeRoutes("<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n{$route}\n");

    expect(app(WelcomeRoute::class)->remove())->toBe(WelcomeRoute::REMOVED)
        ->and(routes())->not->toContain('welcome');
})->with([
    'an arrow function' => "Route::get('/', fn () => view('welcome'));",
    'Route::view()' => "Route::view('/', 'welcome');",
]);

it('keeps a route for / the site wrote', function () {
    $source = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/', fn () => view('home'));\n";
    writeRoutes($source);

    expect(app(WelcomeRoute::class)->remove())->toBe(WelcomeRoute::KEPT)
        ->and(routes())->toBe($source);
});

it('does nothing when there is no route for /', function () {
    writeRoutes(STOCK_WEB_ROUTES);
    app(WelcomeRoute::class)->remove();
    $source = routes();

    expect(app(WelcomeRoute::class)->remove())->toBe(WelcomeRoute::ABSENT)
        ->and(routes())->toBe($source);
});

it('does nothing without routes/web.php', function () {
    expect(app(WelcomeRoute::class)->remove())->toBe(WelcomeRoute::ABSENT)
        ->and($this->projectFile('routes/web.php'))->not->toBeFile();
});

it('is a step of mela:install', function () {
    writeRoutes(STOCK_WEB_ROUTES);

    $this->install();

    expect(routes())->not->toContain('welcome');
});
