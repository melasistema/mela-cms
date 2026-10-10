<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Melasistema\MelaCms\Languages;
use Melasistema\MelaCms\Tests\Fixtures\Article;
use Melasistema\MelaCms\Tests\Fixtures\ArticleForm;

beforeEach(function () {
    $this->install(['--locales' => 'en,it']);

    Schema::create('articles', function (Blueprint $table) {
        $table->id();
        $table->json('title');
        $table->json('body')->nullable();
        $table->timestamps();
    });

    Route::middleware('web')->get('/articles/{article}', fn (Article $article) => $article->title.'|'.$article->body);
});

it('shows a translatable attribute in the language of the request', function () {
    $article = Article::create([
        'title' => ['en' => 'Hello', 'it' => 'Ciao'],
        'body' => ['en' => 'Only in English'],
    ]);

    $this->get("/articles/{$article->id}")->assertSee('Hello|Only in English');
    $this->get("/it/articles/{$article->id}")->assertSee('Ciao|Only in English');
});

it('falls back to the default language of the site, whatever it is', function () {
    $article = Article::create(['title' => ['it' => 'Ciao']]);

    $this->get("/articles/{$article->id}")->assertOk()->assertDontSee('Ciao');

    app(Languages::class)->update(['en', 'it'], 'it', 'en');

    $this->get("/en/articles/{$article->id}")->assertSee('Ciao');
});

it('edits every language in one form', function () {
    Livewire::test(ArticleForm::class)
        ->assertSee('English')
        ->assertSee('Italiano')
        ->fillForm(['title' => ['en' => 'Hello', 'it' => 'Ciao'], 'body' => ['en' => 'Text']])
        ->call('save')
        ->assertHasNoFormErrors();

    $article = Article::sole();

    expect($article->getTranslations('title'))->toBe(['en' => 'Hello', 'it' => 'Ciao'])
        ->and($article->getTranslations('body'))->toBe(['en' => 'Text']);
});

it('fills the form with the saved translations', function () {
    $article = Article::create(['title' => ['en' => 'Hello', 'it' => 'Ciao']]);

    Livewire::test(ArticleForm::class, ['articleId' => $article->id])
        ->assertSchemaStateSet(['title' => ['en' => 'Hello', 'it' => 'Ciao']])
        ->fillForm(['title' => ['it' => 'Salve']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($article->refresh()->getTranslations('title'))->toBe(['en' => 'Hello', 'it' => 'Salve']);
});

it('requires a field in the default language only, and keeps the other rules', function () {
    Livewire::test(ArticleForm::class)
        ->fillForm(['title' => ['it' => 'Ciao']])
        ->call('save')
        ->assertHasFormErrors(['title.en' => 'required'])
        ->assertHasNoFormErrors(['title.it']);

    Livewire::test(ArticleForm::class)
        ->fillForm(['title' => ['en' => 'Hello', 'it' => str_repeat('a', 21)]])
        ->call('save')
        ->assertHasFormErrors(['title.it' => 'max']);
});

it('keeps the translation of a language that was removed from the site', function () {
    $article = Article::create(['title' => ['en' => 'Hello', 'fr' => 'Bonjour']]);

    Livewire::test(ArticleForm::class, ['articleId' => $article->id])
        ->fillForm(['title' => ['en' => 'Hi']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($article->refresh()->getTranslations('title'))->toBe(['en' => 'Hi', 'fr' => 'Bonjour']);
});

it('shows the fields without tabs on a site in one language', function () {
    config(['mela-cms.locales' => ['en']]);

    Livewire::test(ArticleForm::class)
        ->assertDontSee('English')
        ->fillForm(['title' => ['en' => 'Hello']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Article::sole()->getTranslations('title'))->toBe(['en' => 'Hello']);
});
