<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Melasistema\MelaCms\Languages;

beforeEach(function () {
    $this->install(['--locales' => 'en,it']);

    Route::middleware('web')->group(function () {
        Route::get('/', fn () => 'home in '.app()->getLocale())->name('home');
        Route::get('/about', fn () => implode('|', [
            app()->getLocale(),
            url()->current(),
            route('about'),
            asset('app.css'),
        ]))->name('about');
        Route::get('/switcher', fn () => app(Languages::class)->alternates());
        Route::get('/head', fn () => Blade::render('@hreflang'));
    });
});

it('serves the default language at the root', function () {
    $this->get('/about')->assertOk()->assertSee('en|http://localhost/about|http://localhost/about|http://localhost/app.css');
});

it('serves another language under its prefix, with the same routes', function () {
    $this->get('/it/about')->assertOk()->assertSee('it|http://localhost/it/about|http://localhost/it/about|http://localhost/app.css');

    expect(app(Languages::class)->current())->toBe('it');
});

it('keeps the prefix in redirects, pagination and signed urls', function () {
    Route::middleware('web')->group(function () {
        Route::get('/old', fn () => redirect('/about'));
        Route::get('/list', fn () => (new LengthAwarePaginator([], 30, 10, options: ['path' => Paginator::resolveCurrentPath()]))->url(2));
        Route::get('/signed', fn () => request()->hasValidSignature() ? 'valid' : URL::signedRoute('signed'))->name('signed');
    });

    $this->get('/it/old')->assertRedirect('http://localhost/it/about');
    $this->get('/it/list')->assertSee('http://localhost/it/list?page=2', false);

    $signed = $this->get('/it/signed')->getContent();

    expect($signed)->toStartWith('http://localhost/it/signed?signature=');
    $this->get($signed)->assertSee('valid');
});

it('serves the home page of another language with and without a trailing slash', function () {
    $this->get('/it')->assertOk()->assertSee('home in it');
    $this->get('/it/')->assertOk()->assertSee('home in it');
    $this->get('/it?page=2')->assertOk()->assertSee('home in it');
});

it('goes back to the default language after a translated request', function () {
    $this->get('/it/about')->assertOk();

    $this->get('/about')->assertOk()->assertSee('en|http://localhost/about');
});

it('redirects the prefix of the default language to the root', function () {
    $this->get('/en/about?page=2')->assertRedirect('http://localhost/about?page=2');
    $this->get('/en')->assertRedirect('http://localhost/');
});

it('does not treat other segments as languages', function () {
    $this->get('/fr/about')->assertNotFound();
    $this->get('/italy')->assertNotFound();
});

it('applies a change of the default language at once', function () {
    app(Languages::class)->update(['en', 'it'], 'it', 'en');

    $this->get('/about')->assertOk()->assertSee('it|http://localhost/about');
    $this->get('/en/about')->assertOk()->assertSee('en|http://localhost/en/about');
    $this->get('/it/about')->assertRedirect('http://localhost/about');
});

it('writes a regional language as a lowercase slug', function () {
    config(['mela-cms.locales' => ['en', 'pt_BR']]);

    $this->get('/pt-br/about')->assertOk()->assertSee('pt_BR|http://localhost/pt-br/about');
});

it('gives the current page in every language', function () {
    $this->get('/switcher')->assertExactJson([
        'en' => 'http://localhost/switcher',
        'it' => 'http://localhost/it/switcher',
    ]);

    $this->get('/it/switcher')->assertExactJson([
        'en' => 'http://localhost/switcher',
        'it' => 'http://localhost/it/switcher',
    ]);
});

it('gives the url of a path in a language', function () {
    $this->get('/it/about');

    $languages = app(Languages::class);

    expect($languages->url('en', '/contact'))->toBe('http://localhost/contact')
        ->and($languages->url('it', 'contact'))->toBe('http://localhost/it/contact')
        ->and($languages->url('en', '/'))->toBe('http://localhost')
        ->and($languages->url('it', '/'))->toBe('http://localhost/it');
});

it('renders the hreflang tags', function () {
    $this->get('/it/head')->assertOk()
        ->assertSee('<link rel="alternate" hreflang="en" href="http://localhost/head">', false)
        ->assertSee('<link rel="alternate" hreflang="it" href="http://localhost/it/head">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="http://localhost/head">', false);
});

it('renders no hreflang tags for a site in one language', function () {
    config(['mela-cms.locales' => ['en']]);

    expect($this->get('/head')->assertOk()->getContent())->toBe('');
});

it('keeps the admin panel reachable from a translated page', function () {
    $this->get('/it/admin/login')->assertOk();
    $this->get('/admin/login')->assertOk();
});

it('can serve the languages on subdomains', function () {
    config(['mela-cms.locale_urls' => 'subdomain']);

    $this->get('http://it.example.com/about')->assertOk()
        ->assertSee('it|http://it.example.com/about|http://it.example.com/about|http://it.example.com/app.css');

    $this->get('http://example.com/about')->assertOk()->assertSee('en|http://example.com/about');
    $this->get('http://example.com/it/about')->assertNotFound();
    $this->get('http://en.example.com/about?page=2')->assertRedirect('http://example.com/about?page=2');

    $this->get('http://it.example.com/switcher')->assertExactJson([
        'en' => 'http://example.com/switcher',
        'it' => 'http://it.example.com/switcher',
    ]);
});
