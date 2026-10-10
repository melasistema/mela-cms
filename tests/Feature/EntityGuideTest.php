<?php

namespace Melasistema\MelaCms\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Models\Page;
use Melasistema\MelaCms\Tests\Fixtures\User;
use Melasistema\MelaCms\Tests\TestCase;

/**
 * Builds the entity of docs/entity.md in the temporary project, from the
 * code blocks of the guide itself, and uses it as a visitor and as an editor.
 * A guide that no longer matches the core fails here.
 *
 * A class and not a Pest file, because the code of the guide has to be in
 * place before the panel and the routes are registered.
 */
class EntityGuideTest extends TestCase
{
    private const string GUIDE = __DIR__.'/../../docs/entity.md';

    private const string GUIDELINES = __DIR__.'/../../stubs/agent-guidelines.md';

    private const string SERVICE = 'App\\Models\\Service';

    private const string RESOURCE = 'App\\Filament\\Resources\\Services\\ServiceResource';

    private const string CREATE_PAGE = 'App\\Filament\\Resources\\Services\\Pages\\CreateService';

    private const string LIST_PAGE = 'App\\Filament\\Resources\\Services\\Pages\\ListServices';

    /** @var callable(string): void */
    private $autoloader;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        View::addLocation($this->projectFile('resources/views'));

        $this->withoutVite();
        // Also runs the migration of the guide, as `php artisan migrate` does in a site.
        $this->install(['--locales' => 'en,it']);
    }

    protected function tearDown(): void
    {
        spl_autoload_unregister($this->autoloader);

        parent::tearDown();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $files = new Filesystem;

        foreach (self::guideFiles() as $path => $file) {
            if ($file['add']) {
                continue;
            }

            $files->ensureDirectoryExists(dirname($this->projectFile($path)));
            $files->put($this->projectFile($path), $file['code']."\n");
        }

        // The routes of the guide are lines to add to the routes file of the site.
        $files->ensureDirectoryExists($this->projectFile('routes'));
        $files->put(
            $this->projectFile('routes/web.php'),
            "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n".self::guideFiles()['routes/web.php']['code']."\n",
        );

        // In a site Composer loads app/ and the panel discovers the resource there.
        spl_autoload_register($this->autoloader = function (string $class): void {
            $file = $this->projectFile('app/'.str_replace('\\', '/', substr($class, 4)).'.php');

            if (str_starts_with($class, 'App\\') && is_file($file)) {
                require_once $file;
            }
        });

        $app['config']->set('mela-cms.resources', [
            ...(require __DIR__.'/../../config/mela-cms.php')['resources'],
            'services' => self::RESOURCE,
        ]);
        $app['config']->set('mela-cms.sitemap', [Page::class, self::SERVICE, 'services']);
    }

    protected function defineWebRoutes($router): void
    {
        require $this->projectFile('routes/web.php');
    }

    public function test_the_entity_of_the_guide_is_shown_on_the_site_in_each_language(): void
    {
        $this->service();
        $this->service(['title' => ['en' => 'Coming soon'], 'slug' => ['en' => 'coming-soon'], 'published_at' => null]);

        $this->get('/services')
            ->assertOk()
            ->assertSee('<title>Services | Laravel</title>', false)
            ->assertSee('Web design')
            ->assertSee('href="http://localhost/services/web-design"', false)
            ->assertDontSee('Coming soon');

        $this->app->forgetScopedInstances();

        $this->get('/services/web-design')
            ->assertOk()
            ->assertSee('<title>Web design | Laravel</title>', false)
            ->assertSee('<meta name="description" content="Sites that load fast.">', false)
            ->assertSee('<link rel="alternate" hreflang="it" href="http://localhost/it/services/design-web">', false)
            ->assertSee('<a href="http://localhost/it/services/design-web" hreflang="it">IT</a>', false)
            ->assertSee('<p>How we work.</p>', false);

        $this->app->forgetScopedInstances();

        $this->get('/it/services/design-web')
            ->assertOk()
            ->assertSee('<title>Design web | Laravel</title>', false)
            ->assertSee('<a href="http://localhost/services/web-design" hreflang="en">EN</a>', false);

        $this->get('/it/services/web-design')->assertNotFound();
        $this->get('/services/coming-soon')->assertNotFound();
    }

    public function test_the_entity_of_the_guide_is_in_the_sitemap(): void
    {
        $this->service();
        $this->service(['slug' => ['en' => 'coming-soon'], 'published_at' => null]);
        $this->service(['slug' => ['en' => 'hidden'], 'seo' => ['noindex' => true]]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>http://localhost/services/web-design</loc>', false)
            ->assertSee('<loc>http://localhost/it/services/design-web</loc>', false)
            ->assertSee('<loc>http://localhost/it/services</loc>', false)
            ->assertDontSee('coming-soon')
            ->assertDontSee('hidden');
    }

    public function test_an_editor_works_on_the_entity_of_the_guide_in_the_panel(): void
    {
        $editor = User::create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => bcrypt('password')]);
        $editor->assignRole(Role::Editor->value);

        $this->actingAs($editor);

        Livewire::test(self::CREATE_PAGE)
            ->fillForm([
                'title.en' => 'Web design',
                'title.it' => 'Design web',
                'slug.en' => 'web-design',
                'slug.it' => 'design-web',
                'excerpt.en' => 'Sites that load fast.',
                'seo.title.en' => 'Web design in Rome',
                'seo.noindex' => true,
                'published_at' => now()->subDay(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $service = self::SERVICE::query()->sole();

        $this->assertSame('design-web', $service->getTranslation('slug', 'it'));
        $this->assertSame('Web design in Rome', $service->seoTitle());
        $this->assertTrue($service->seoNoindex());
        $this->assertTrue($service->isPublished());

        // The same address in the same language is refused for a second record.
        Livewire::test(self::CREATE_PAGE)
            ->fillForm(['title.en' => 'Again', 'slug.en' => 'web-design'])
            ->call('create')
            ->assertHasFormErrors(['slug.en']);

        Livewire::test(self::LIST_PAGE)
            ->assertCanSeeTableRecords([$service])
            ->searchTable('Design web')
            ->assertCanSeeTableRecords([$service])
            ->searchTable('nothing like this')
            ->assertCanNotSeeTableRecords([$service]);

        $this->get(self::RESOURCE::getUrl('index'))->assertOk();
    }

    public function test_the_php_of_the_guide_passes_pint(): void
    {
        $directory = $this->projectFile('pint');
        $files = new Filesystem;

        foreach (self::guideFiles() as $path => $file) {
            if ($file['language'] === 'php' && ! $file['add']) {
                $files->ensureDirectoryExists(dirname("{$directory}/{$path}"));
                $files->put("{$directory}/{$path}", $file['code']."\n");
            }
        }

        exec(escapeshellarg(__DIR__.'/../../vendor/bin/pint').' --test --preset laravel '.escapeshellarg($directory).' 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    public function test_the_guides_name_only_what_the_core_has(): void
    {
        foreach ([self::GUIDE, self::GUIDELINES] as $guide) {
            preg_match_all('/Melasistema\\\\MelaCms((?:\\\\[A-Z]\w*)+)/', file_get_contents($guide), $matches);

            $this->assertNotEmpty($matches[1]);

            foreach (array_unique($matches[1]) as $name) {
                $path = __DIR__.'/../../src'.str_replace('\\', '/', $name);

                $this->assertTrue(
                    is_file($path.'.php') || is_dir($path),
                    basename($guide)." names Melasistema\\MelaCms{$name}, which the core does not have.",
                );
            }
        }
    }

    public function test_the_agent_guidelines_point_to_the_guide(): void
    {
        $this->assertStringContainsString('vendor/melasistema/mela-cms/docs/entity.md', file_get_contents(self::GUIDELINES));

        // The guide is part of what Composer downloads into a site.
        $this->assertStringNotContainsString('/docs', file_get_contents(__DIR__.'/../../.gitattributes'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function service(array $attributes = []): object
    {
        return self::SERVICE::create([
            'title' => ['en' => 'Web design', 'it' => 'Design web'],
            'slug' => ['en' => 'web-design', 'it' => 'design-web'],
            'excerpt' => ['en' => 'Sites that load fast.'],
            'body' => ['en' => '<p>How we work.</p>'],
            'published_at' => now()->subDay(),
            ...$attributes,
        ]);
    }

    /**
     * The files of the guide: a line with the path in bold, then a code
     * block. "add" marks lines for a file the site already has.
     *
     * @return array<string, array{language: string, add: bool, code: string}>
     */
    private static function guideFiles(): array
    {
        preg_match_all(
            '/^\*\*`([^`]+)`\*\*(, add[^\n]*)?\n\n```(\w+)\n(.*?)\n```$/ms',
            file_get_contents(self::GUIDE),
            $matches,
            PREG_SET_ORDER,
        );

        $files = [];

        foreach ($matches as [, $path, $add, $language, $code]) {
            $files[$path] = ['language' => $language, 'add' => $add !== '', 'code' => $code];
        }

        return $files;
    }
}
