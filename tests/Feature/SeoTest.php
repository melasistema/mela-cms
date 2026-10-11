<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Filament\Pages\ManageSettings;
use Melasistema\MelaCms\Filament\Resources\Pages\Pages\CreatePage;
use Melasistema\MelaCms\Languages;
use Melasistema\MelaCms\Models\Media;
use Melasistema\MelaCms\Models\Page;
use Melasistema\MelaCms\Seo;
use Melasistema\MelaCms\Settings;
use Melasistema\MelaCms\Tests\Fixtures\Post;
use Melasistema\MelaCms\Tests\Fixtures\User;

beforeEach(function () {
    Storage::fake('public');

    $this->install(['--locales' => 'en,it']);

    app(Settings::class)->merge('site', [
        'name' => ['en' => 'Studio Mela', 'it' => 'Studio Mela IT'],
        'description' => ['en' => 'We build websites.'],
    ]);
});

function seoUser(Role $role): User
{
    $user = User::create(['name' => 'Test', 'email' => uniqid().'@example.com', 'password' => bcrypt('password')]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function seoPage(array $attributes = []): Page
{
    return Page::create([
        'title' => ['en' => 'About us', 'it' => 'Chi siamo'],
        'slug' => ['en' => 'about', 'it' => 'chi-siamo'],
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);
}

function seoImage(string $name = 'photo.jpg'): Media
{
    return Media::fromFile(UploadedFile::fake()->image($name, 1200, 630)->store('media', 'public'), $name);
}

it('prints the title and the address of a page, with the site as fallback', function () {
    seoPage();

    $this->get('/about?utm_source=x')
        ->assertOk()
        ->assertSee('<title>About us | Studio Mela</title>', false)
        ->assertSee('<meta name="description" content="We build websites.">', false)
        ->assertSee('<link rel="canonical" href="http://localhost/about">', false)
        ->assertSee('<meta property="og:title" content="About us">', false)
        ->assertSee('<meta property="og:site_name" content="Studio Mela">', false)
        ->assertSee('<meta property="og:url" content="http://localhost/about">', false)
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertDontSee('robots', false)
        ->assertDontSee('og:image', false);
});

it('prints what an editor wrote for search engines, in the language of the request', function () {
    $cover = seoImage('cover.jpg');
    $share = seoImage('share.jpg');
    $share->update(['alt' => ['en' => 'Our team']]);

    seoPage(['cover_id' => $cover->id, 'seo' => [
        'title' => ['en' => 'The studio', 'it' => 'Lo studio'],
        'description' => ['en' => "Who we are &\n what we do."],
        'image' => $share->id,
    ]]);

    $this->get('/about')
        ->assertSee('<title>The studio | Studio Mela</title>', false)
        ->assertSee('<meta name="description" content="Who we are &amp; what we do.">', false)
        ->assertSee('<meta property="og:image" content="'.$share->url().'">', false)
        ->assertSee('<meta property="og:image:width" content="1200">', false)
        ->assertSee('<meta property="og:image:alt" content="Our team">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);

    $this->get('/it/chi-siamo')
        ->assertSee('<title>Lo studio | Studio Mela IT</title>', false)
        ->assertSee('<meta name="description" content="Who we are &amp; what we do.">', false)
        ->assertSee('<meta property="og:locale" content="it">', false);
});

it('shares the cover of a page, then the share image of the site', function () {
    $cover = seoImage('cover.jpg');
    $site = seoImage('site.jpg');

    seoPage(['cover_id' => $cover->id]);
    seoPage(['slug' => ['en' => 'contact']]);

    app(Settings::class)->merge('site', ['share_image' => $site->id]);

    $this->get('/about')->assertSee('og:image" content="'.$cover->url().'"', false);
    $this->get('/contact')->assertSee('og:image" content="'.$site->url().'"', false);
});

it('calls the home page like the site', function () {
    $home = seoPage(['title' => ['en' => 'Home'], 'slug' => ['en' => 'home']]);

    app(Settings::class)->merge('site', ['home_page' => $home->id]);

    $this->get('/')
        ->assertSee('<title>Studio Mela</title>', false)
        ->assertSee('<link rel="canonical" href="http://localhost">', false);

    $home->update(['seo' => ['title' => ['en' => 'Websites that work']]]);

    $this->get('/')->assertSee('<title>Websites that work | Studio Mela</title>', false);
});

it('writes titles in the format of the settings page', function () {
    seoPage();

    $this->actingAs(seoUser(Role::Admin));

    Livewire::test(ManageSettings::class)
        ->fillForm(['seo.title_format' => ':site · :title'])
        ->call('save')
        ->assertHasNoFormErrors();

    auth()->logout();

    $this->get('/about')->assertSee('<title>Studio Mela · About us</title>', false);
});

it('keeps a page, a draft or the whole site out of search engines', function () {
    seoPage();
    seoPage(['slug' => ['en' => 'hidden'], 'seo' => ['noindex' => true]]);
    seoPage(['slug' => ['en' => 'draft'], 'published_at' => null]);

    $robots = '<meta name="robots" content="noindex, nofollow">';

    $this->get('/about')->assertDontSee($robots, false);
    $this->get('/hidden')->assertSee($robots, false);
    $this->actingAs(seoUser(Role::Editor))->get('/draft')->assertOk()->assertSee($robots, false);

    app(Settings::class)->merge('site', ['seo' => ['noindex' => true]]);

    $this->get('/about')->assertSee($robots, false);
    $this->get('/sitemap.xml')->assertNotFound();
});

it('saves the search engine fields of a page', function () {
    $this->actingAs(seoUser(Role::Editor));

    $image = seoImage();

    Livewire::test(CreatePage::class)
        ->fillForm([
            'title.en' => 'About us',
            'seo.title.it' => 'Lo studio',
            'seo.description.en' => 'Who we are.',
            'seo.image' => $image->id,
            'seo.noindex' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $page = Page::sole();

    expect($page->seo['title'])->toMatchArray(['it' => 'Lo studio'])
        ->and($page->seoTitle())->toBe('About us')
        ->and($page->seoDescription())->toBe('Who we are.')
        ->and((int) $page->seoImageId())->toBe($image->id)
        ->and($page->seoNoindex())->toBeTrue();
});

it('lets a route of the site set its own tags', function () {
    Route::middleware('web')->get('/blog', function () {
        app(Seo::class)->title('Blog')->description('<p>Everything we wrote.</p>')->type('article');

        return Blade::render('@seo');
    });
    Route::middleware('web')->get('/plain', fn () => Blade::render('@seo'));

    $this->get('/it/blog')
        ->assertSee('<title>Blog | Studio Mela IT</title>', false)
        ->assertSee('<meta name="description" content="Everything we wrote.">', false)
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<link rel="canonical" href="http://localhost/it/blog">', false);

    // A new request starts without the tags of the one before.
    app()->forgetScopedInstances();

    $this->get('/plain')->assertSee('<title>Studio Mela</title>', false);
});

it('reads the tags of a site entity from its title', function () {
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->json('title');
        $table->json('slug');
        $table->timestamp('published_at')->nullable();
        $table->timestamps();
    });

    $post = Post::create(['title' => ['en' => 'News'], 'slug' => ['en' => 'news']]);

    $seo = app(Seo::class)->for($post);

    expect($seo->getTitle())->toBe('News | Studio Mela')
        ->and($seo->isIndexable())->toBeFalse();
});

it('lists the published pages in the sitemap, in every language', function () {
    $home = seoPage(['title' => ['en' => 'Home'], 'slug' => ['en' => 'home']]);
    seoPage();
    seoPage(['slug' => ['en' => 'hidden'], 'seo' => ['noindex' => true]]);
    seoPage(['slug' => ['en' => 'draft'], 'published_at' => null]);

    app(Settings::class)->merge('site', ['home_page' => $home->id]);

    config(['mela-cms.sitemap' => [Page::class, 'blog']]);

    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $xml = simplexml_load_string($response->getContent());
    $locations = array_map(strval(...), $xml->xpath('//*[local-name()="loc"]'));

    expect($locations)->toBe([
        'http://localhost',
        'http://localhost/it',
        'http://localhost/about',
        'http://localhost/it/chi-siamo',
        'http://localhost/blog',
        'http://localhost/it/blog',
    ]);

    $response
        ->assertSee('<xhtml:link rel="alternate" hreflang="it" href="http://localhost/it/chi-siamo"/>', false)
        ->assertSee('<lastmod>', false);
});

it('leaves the alternates out of the sitemap of a site in one language', function () {
    app(Languages::class)->update(['en'], 'en', 'en');

    seoPage();

    $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>http://localhost/about</loc>', false)->assertDontSee('xhtml:link', false);
});

it('removes the sitemap when the site lists nothing', function () {
    config(['mela-cms.sitemap' => []]);

    $this->get('/sitemap.xml')->assertNotFound();
});
