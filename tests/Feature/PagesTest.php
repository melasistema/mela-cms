<?php

use Filament\Actions\Testing\TestAction;
use Filament\Panel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Melasistema\MelaCms\Enums\PublicationStatus;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Filament\AdminPanelProvider;
use Melasistema\MelaCms\Filament\Forms\RichText;
use Melasistema\MelaCms\Filament\Pages\ManageSettings;
use Melasistema\MelaCms\Filament\Resources\Pages\PageResource;
use Melasistema\MelaCms\Filament\Resources\Pages\Pages\CreatePage;
use Melasistema\MelaCms\Filament\Resources\Pages\Pages\EditPage;
use Melasistema\MelaCms\Filament\Resources\Pages\Pages\ListPages;
use Melasistema\MelaCms\Languages;
use Melasistema\MelaCms\Models\Media;
use Melasistema\MelaCms\Models\Page;
use Melasistema\MelaCms\RichContent;
use Melasistema\MelaCms\Settings;
use Melasistema\MelaCms\Tests\Fixtures\Post;
use Melasistema\MelaCms\Tests\Fixtures\SitePageResource;
use Melasistema\MelaCms\Tests\Fixtures\User;

beforeEach(function () {
    Storage::fake('public');

    $this->install(['--locales' => 'en,it']);
});

function pageUser(?Role $role): User
{
    $user = User::create([
        'name' => 'Test',
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
    ]);

    if ($role) {
        $user->assignRole($role->value);
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function page(array $attributes = []): Page
{
    return Page::create([
        'title' => ['en' => 'About us', 'it' => 'Chi siamo'],
        'slug' => ['en' => 'about', 'it' => 'chi-siamo'],
        'body' => ['en' => '<p>Our story</p>', 'it' => '<p>La nostra storia</p>'],
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);
}

/**
 * A view of the site in resources/views/pages.
 */
function siteView(string $name, string $content): void
{
    $path = test()->projectFile('resources/views');

    @mkdir($path.'/pages', recursive: true);
    file_put_contents("{$path}/pages/{$name}.blade.php", $content);

    View::addLocation($path);
}

it('shows a published page at its address', function () {
    page();

    $this->get('/about')
        ->assertOk()
        ->assertSee('<h1>About us</h1>', false)
        ->assertSee('<p>Our story</p>', false);
});

it('gives each language its own address', function () {
    page();

    $this->get('/it/chi-siamo')->assertOk()->assertSee('Chi siamo')->assertSee('La nostra storia');
    $this->get('/it/about')->assertNotFound();
    $this->get('/chi-siamo')->assertNotFound();
});

it('uses the address of the default language where a language has none', function () {
    page(['title' => ['en' => 'Contact'], 'slug' => ['en' => 'contact'], 'body' => null]);

    $this->get('/it/contact')->assertOk()->assertSee('Contact');
});

it('keeps the address of a page when the default language changes', function () {
    page();
    $page = page([
        'title' => ['en' => 'Contact'],
        'slug' => ['en' => 'contact'],
        'body' => ['en' => '<p>Write to us</p>'],
    ]);

    app(Languages::class)->update(['en', 'it'], 'it', 'en');

    $this->get('/contact')->assertOk();
    $this->get('/en/contact')->assertOk()->assertSee('Contact')->assertSee('Write to us');
    $this->get('/chi-siamo')->assertOk();
    $this->get('/about')->assertNotFound();

    expect($page->fresh()->paths())->toBe(['it' => 'contact', 'en' => 'contact'])
        ->and($page->fresh()->url('it'))->toBe('http://localhost/contact');
});

it('looks for an address in the site languages in their order', function () {
    app(Languages::class)->update(['en', 'it', 'de'], 'en', 'en');

    $german = page(['title' => ['de' => 'Kontakt'], 'slug' => ['de' => 'kontakt']]);
    $italian = page(['title' => ['it' => 'Storia', 'de' => 'Geschichte'], 'slug' => ['it' => 'storia', 'de' => 'geschichte']]);

    expect(Page::whereSlug('kontakt', 'en')->exists())->toBeTrue()
        ->and(Page::whereSlug('kontakt', 'it')->exists())->toBeTrue()
        ->and(Page::whereSlug('storia', 'en')->exists())->toBeTrue()
        ->and(Page::whereSlug('geschichte', 'en')->exists())->toBeFalse()
        ->and($german->slugs())->toBe(['en' => 'kontakt', 'it' => 'kontakt', 'de' => 'kontakt'])
        ->and($italian->slugs())->toBe(['en' => 'storia', 'it' => 'storia', 'de' => 'geschichte']);
});

it('serves an address with more than one level', function () {
    page(['slug' => ['en' => 'services/web-design']]);

    $this->get('/services/web-design')->assertOk();
    $this->get('/services')->assertNotFound();
});

it('hides a draft and a scheduled page from visitors', function () {
    $draft = page(['slug' => ['en' => 'draft'], 'published_at' => null]);
    $scheduled = page(['slug' => ['en' => 'soon'], 'published_at' => now()->addDay()]);

    expect($draft->status())->toBe(PublicationStatus::Draft)
        ->and($scheduled->status())->toBe(PublicationStatus::Scheduled)
        ->and(page()->status())->toBe(PublicationStatus::Published)
        ->and(Page::published()->count())->toBe(1);

    $this->get('/draft')->assertNotFound();
    $this->get('/soon')->assertNotFound();
});

it('shows a draft to someone who may edit it', function () {
    page(['published_at' => null]);

    $this->actingAs(pageUser(null))->get('/about')->assertNotFound();
    $this->actingAs(pageUser(Role::Editor))->get('/about')->assertOk();
});

it('serves the home page at the root of each language', function () {
    $home = page(['title' => ['en' => 'Welcome', 'it' => 'Benvenuti'], 'slug' => ['en' => 'home']]);

    $this->get('/')->assertNotFound();

    app(Settings::class)->merge('site', ['home_page' => (string) $home->id]);

    $this->get('/')->assertOk()->assertSee('Welcome');
    $this->get('/it')->assertOk()->assertSee('Benvenuti');
    $this->get('/home')->assertRedirect('http://localhost')->assertStatus(301);
    $this->get('/it/home')->assertRedirect('http://localhost/it');

    expect($home->url('en'))->toBe('http://localhost')
        ->and($home->url('it'))->toBe('http://localhost/it');
});

it('lets a route of the site win over a page', function () {
    page();

    Route::middleware('web')->get('/about', fn () => 'the route of the site');

    $this->get('/about')->assertSee('the route of the site');
});

it('leaves the addresses alone when the site serves its pages itself', function () {
    page();

    config(['mela-cms.public_pages' => false]);

    $this->get('/about')->assertNotFound();
});

it('points the language switcher at the address of each language', function () {
    $page = page();

    siteView('default', '@hreflang|{!! json_encode(app(\Melasistema\MelaCms\Languages::class)->alternates()) !!}');

    $this->get('/it/chi-siamo')
        ->assertSee('hreflang="en" href="http://localhost/about"', false)
        ->assertSee('hreflang="it" href="http://localhost/it/chi-siamo"', false)
        ->assertSee('hreflang="x-default" href="http://localhost/about"', false)
        ->assertSee('"it":"http:\/\/localhost\/it\/chi-siamo"', false);

    expect($page->paths())->toBe(['en' => 'about', 'it' => 'chi-siamo'])
        ->and($page->url('it'))->toBe('http://localhost/it/chi-siamo');
});

it('shows a page with the view of its template', function () {
    siteView('default', 'site default: {{ $page->title }}');
    siteView('landing-page', 'landing: {{ $page->title }}');

    page();
    page(['slug' => ['en' => 'offer'], 'template' => 'landing-page']);
    page(['slug' => ['en' => 'old'], 'template' => 'removed']);

    expect(Page::templates())->toBe(['default' => 'Default', 'landing-page' => 'Landing Page']);

    $this->get('/about')->assertSee('site default: About us');
    $this->get('/offer')->assertSee('landing: About us');
    $this->get('/old')->assertSee('site default: About us');
});

it('prints rich text without what could run in the browser', function () {
    $media = Media::fromFile(UploadedFile::fake()->image('photo.jpg', 100, 100)->store('media', 'public'));

    $html = (string) RichContent::html('<p onclick="x()">Hello</p><script>alert(1)</script><img data-id="'.$media->path.'" src="http://old.test/gone.jpg">');

    expect($html)->toContain('<p>Hello</p>')
        ->not->toContain('script')
        ->not->toContain('onclick')
        ->toContain(Storage::disk('public')->url($media->path))
        ->not->toContain('old.test')
        ->and((string) RichContent::html(null))->toBe('')
        ->and(Blade::render('@richtext($body)', ['body' => '<p>Hi</p>']))->toBe('<p>Hi</p>');
});

it('reads the fields a site adds to a page', function () {
    $page = page(['fields' => ['hero' => ['link' => '/book', 'button' => ['en' => 'Book now', 'it' => 'Prenota']]]]);

    expect($page->field('hero.link'))->toBe('/book')
        ->and($page->field('hero.missing', 'none'))->toBe('none')
        ->and($page->translated('hero.button'))->toBe('Book now');

    app()->setLocale('it');

    expect($page->translated('hero.button'))->toBe('Prenota');
});

it('shows the pages screen to every role', function (?Role $role, int $status) {
    $this->actingAs(pageUser($role))->get('/admin/pages')->assertStatus($status);
})->with([
    [Role::SuperAdmin, 200],
    [Role::Admin, 200],
    [Role::Editor, 200],
    [null, 403],
]);

it('lists the pages with their status and finds one in any language', function () {
    $this->actingAs(pageUser(Role::Editor));

    $about = page();
    $draft = page(['title' => ['en' => 'Prices', 'it' => 'Prezzi'], 'slug' => ['en' => 'prices'], 'published_at' => null]);

    Livewire::test(ListPages::class)
        ->assertCanSeeTableRecords([$about, $draft])
        ->assertSee('/about')
        ->assertSee('Published')
        ->assertSee('Draft')
        ->searchTable('prezzi')
        ->assertCanSeeTableRecords([$draft])
        ->assertCanNotSeeTableRecords([$about]);
});

it('creates a page and makes its address from the title', function () {
    $this->actingAs(pageUser(Role::Editor));

    Livewire::test(CreatePage::class)
        ->fillForm(['title.en' => 'Über uns & Co.'])
        ->assertSchemaStateSet(['slug.en' => 'uber-uns-co'])
        ->fillForm([
            'title.it' => 'Chi siamo',
            'body.en' => '<p>Our story</p>',
            'published_at' => now()->subHour(),
        ])
        ->assertSchemaStateSet(['slug.it' => 'chi-siamo'])
        ->call('create')
        ->assertHasNoFormErrors();

    $page = Page::sole();

    expect($page->getTranslations('slug'))->toBe(['en' => 'uber-uns-co', 'it' => 'chi-siamo'])
        ->and($page->template)->toBe('default')
        ->and($page->isPublished())->toBeTrue();

    $this->get('/uber-uns-co')->assertOk()->assertSee('Our story');
});

it('keeps an address that was typed by hand', function () {
    $this->actingAs(pageUser(Role::Editor));

    Livewire::test(CreatePage::class)
        ->fillForm(['slug.en' => 'who-we-are'])
        ->fillForm(['title.en' => 'About us'])
        ->assertSchemaStateSet(['slug.en' => 'who-we-are']);
});

it('refuses an address that cannot work', function (string $slug) {
    $this->actingAs(pageUser(Role::Editor));

    page();

    Livewire::test(CreatePage::class)
        ->fillForm(['title.en' => 'New', 'slug.en' => $slug])
        ->call('create')
        ->assertHasFormErrors(['slug.en']);
})->with([
    'taken' => 'about',
    'capitals and spaces' => 'About Us',
    'a leading slash' => '/about-us',
    'a language' => 'it/about',
    'the admin panel' => 'admin',
    'empty' => '',
]);

it('refuses an address that a route of the site answers', function (string $slug) {
    Route::get('blog', fn () => 'The blog');
    Route::get('blog/{post}', fn () => 'A post');
    Route::post('contact', fn () => 'Sent');

    $this->actingAs(pageUser(Role::Editor));

    Livewire::test(CreatePage::class)
        ->fillForm(['title.en' => 'New', 'slug.en' => 'blog', 'title.it' => 'Nuova', 'slug.it' => $slug])
        ->call('create')
        ->assertHasFormErrors(['slug.en', 'slug.it']);
})->with([
    'a route' => 'blog',
    'a route with a parameter' => 'blog/first-post',
    'a route for another method' => 'contact',
]);

it('accepts an address next to the routes of the site', function () {
    Route::get('blog/{post}', fn () => 'A post');

    $this->actingAs(pageUser(Role::Editor));

    Livewire::test(CreatePage::class)
        ->fillForm(['title.en' => 'New', 'slug.en' => 'blog', 'title.it' => 'Nuova', 'slug.it' => 'blog/first-post/comments'])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->get('/blog')->assertOk()->assertSee('New');
});

it('accepts the same address in two languages and the own address of a page', function () {
    $this->actingAs(pageUser(Role::Editor));

    $page = page(['slug' => ['en' => 'about', 'it' => 'about']]);

    Livewire::test(EditPage::class, ['record' => $page->id])
        ->assertSchemaStateSet(['title.it' => 'Chi siamo', 'slug.en' => 'about'])
        ->fillForm(['title.en' => 'About the studio', 'slug.it' => 'services/about'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->refresh()->getTranslations('title'))->toBe(['en' => 'About the studio', 'it' => 'Chi siamo'])
        ->and($page->getTranslations('slug'))->toBe(['en' => 'about', 'it' => 'services/about']);
});

it('lets an editor delete a page', function () {
    $this->actingAs(pageUser(Role::Editor));

    $page = page();

    Livewire::test(ListPages::class)->callAction(TestAction::make('delete')->table($page));

    expect(Page::count())->toBe(0);
});

it('offers the templates of the site once there is more than one', function () {
    $this->actingAs(pageUser(Role::Editor));

    Livewire::test(CreatePage::class)->assertFormFieldHidden('template');

    siteView('landing-page', 'landing');

    Livewire::test(CreatePage::class)
        ->assertFormFieldVisible('template')
        ->fillForm(['title.en' => 'Offer', 'template' => 'landing-page'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Page::sole()->template)->toBe('landing-page');
});

it('saves the fields a site adds to the page form', function () {
    config(['mela-cms.resources.pages' => SitePageResource::class]);

    $this->actingAs(pageUser(Role::Editor));

    siteView('home', 'home');

    Livewire::test(CreatePage::class)
        ->assertFormFieldHidden('fields.hero.link')
        ->fillForm([
            'title.en' => 'Welcome',
            'template' => 'home',
            'fields.hero.link' => '/book',
            'fields.hero.button.it' => 'Prenota',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CreatePage::getResource())->toBe(SitePageResource::class)
        ->and(Page::sole()->field('hero.link'))->toBe('/book')
        ->and(Page::sole()->field('hero.button.it'))->toBe('Prenota');
});

it('removes the pages screen when the site switches it off', function () {
    config(['mela-cms.resources.pages' => null]);

    $panel = (new AdminPanelProvider(app()))->panel(Panel::make());

    expect($panel->getResources())->not->toContain(PageResource::class);

    $this->actingAs(pageUser(Role::Admin));

    Livewire::test(ManageSettings::class)->assertFormFieldHidden('home_page');
});

it('lets an admin choose the home page', function () {
    $this->actingAs(pageUser(Role::Admin));

    $page = page();

    Livewire::test(ManageSettings::class)
        ->fillForm(['home_page' => $page->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Page::home()->is($page))->toBeTrue()
        ->and($page->isHome())->toBeTrue()
        ->and($page->path())->toBe('');

    Livewire::test(ListPages::class)->assertSee('Home page');
});

it('adds an image of the text to the media library', function () {
    $this->actingAs(pageUser(Role::Editor));

    Storage::fake('tmp-for-tests');

    $path = null;

    // Stored the way Livewire stores an upload, with its name in the file name.
    $upload = UploadedFile::fake()->image('Team.jpg', 600, 400);
    $upload->storeAs('livewire-tmp', $name = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($upload), 'tmp-for-tests');

    Livewire::test(CreatePage::class)
        ->assertFormFieldExists('body.en', function (RichText $field) use (&$path, $name) {
            $path = $field->saveUploadedFileAttachment(TemporaryUploadedFile::createFromLivewire($name));

            return true;
        });

    $media = Media::sole();

    expect($media->path)->toBe($path)->toStartWith('media/')
        ->and($media->name)->toBe('Team')
        ->and($media->width)->toBe(600)
        ->and($media->conversions)->toHaveKey('thumb');
});

it('gives a site entity its address in each language', function () {
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->json('title');
        $table->json('slug');
        $table->timestamp('published_at')->nullable();
        $table->timestamps();
    });

    Route::middleware('web')->get('/blog/{slug}', function (string $slug) {
        $post = Post::published()->whereSlug($slug)->firstOrFail();

        app(Languages::class)->localize(array_map(fn (string $slug) => "blog/{$slug}", $post->slugs()));

        return $post->title.'|'.implode('|', app(Languages::class)->alternates());
    });

    Post::create(['title' => ['en' => 'News', 'it' => 'Novità'], 'slug' => ['en' => 'news', 'it' => 'novita'], 'published_at' => now()]);
    Post::create(['title' => ['en' => 'Draft'], 'slug' => ['en' => 'draft']]);

    $this->get('/it/blog/novita')->assertOk()->assertSee('Novità|http://localhost/blog/news|http://localhost/it/blog/novita');
    $this->get('/blog/novita')->assertNotFound();
    $this->get('/blog/draft')->assertNotFound();

    expect(Post::whereSlug('novita', 'it')->exists())->toBeTrue();
});

it('keeps the published scope working in a query joined to another publishable table', function () {
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->json('title');
        $table->json('slug');
        $table->timestamp('published_at')->nullable();
        $table->timestamps();
    });

    $published = page();
    page(['slug' => ['en' => 'draft'], 'published_at' => null]);
    Post::create(['title' => ['en' => 'Draft'], 'slug' => ['en' => 'draft']]);

    // Both tables have `published_at`: the scope reads the one of the pages.
    $ids = Page::published()->crossJoin('posts')->pluck('mela_pages.id')->all();

    expect($ids)->toBe([$published->id]);
});
