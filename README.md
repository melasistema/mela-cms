# MelaCMS

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

MelaCMS is a CMS for Laravel, built for freelancers and studios who write client websites with coding agents and still need to hand the client a clean admin panel.

It is a Composer package. Each website is a normal Laravel app that requires it, and one command adds the admin panel, the roles, a super-admin account and the GitHub workflows that test, release and deploy the site.

**Stack:** Laravel 13, Filament 5, Blade, Tailwind and Alpine, with Vue component islands when a page needs one.

> [!NOTE]
> MelaCMS is at an early stage (`0.x`). The release and deploy backbone is in place; the content features are being built on top of it. See [Status](#status).

## Why

WordPress is hard to work on with a coding agent: the site lives in the database and in plugins, not in code an agent can read, change and test. Multilanguage needs a paid plugin, and every site drifts into its own mix of plugins and versions.

MelaCMS takes the other route:

- **The site is code.** Models, migrations, admin screens and views live in the site's repository, where an agent can work on them and tests can cover them.
- **The core is a dependency.** It arrives through Composer and is updated with `composer update`, so every site runs a known version.
- **Agents get instructions.** The installer writes a MelaCMS section into the site's `AGENTS.md` and `CLAUDE.md`: what lives where, which commands to run, how releases work.
- **Shipping is set up from the first commit.** CI, versioning, changelog and deploy come with the install.

## Status

| Area | State |
| :--- | :--- |
| Admin panel (Filament 5) at `/admin` | Available |
| Roles `super-admin`, `admin`, `editor` | Available |
| Users screen: create, edit and delete users, assign a role | Available |
| Profile page: every user changes their own name, email and password | Available |
| `mela:install`, `mela:update` and `mela:super-admin` commands | Available |
| CI, release and deploy workflows for client sites | Available, in use on a live site |
| Languages page: site languages, default language, admin panel language | Available |
| Admin panel in the chosen language | Available for Filament's own texts; the core's own labels are English only |
| Localized URLs: default language at the root, the others at `/it/...` or `it.example.com` | Available |
| Language switcher data and `hreflang` tags | Available |
| Translatable content: JSON columns, one form with a tab per language | Available |
| Settings page: site name, description and share image, contact details, social profiles | Available |
| Media library: upload, image sizes, a form field to pick a file | Available |
| Pages: title, address and text per language, templates, cover image, publication date, served by the core | Available |
| Building blocks for a site's own entities: translated slugs, publication date, rich text | Available |
| SEO: title, description and share image per page, defaults from the settings, `@seo` tags, `/sitemap.xml` | Available |
| Guide for coding agents: a complete entity, from the migration to the test | Available |

## Requirements

- PHP 8.4
- Laravel 13
- Node 24, for the site's asset build
- A database supported by Laravel
- The `intl` PHP extension, for the names of the languages
- The GD or Imagick PHP extension with WebP support, for the image sizes of the media library

## Installation

Start from a fresh Laravel app:

```bash
laravel new client-site && cd client-site
composer config repositories.mela-cms vcs https://github.com/melasistema/mela-cms
composer require melasistema/mela-cms
php artisan mela:install
```

Then open `/admin` and log in with the super-admin account you just created.

If artisan stops with "Invalid URI: Host is malformed", look at `APP_URL` in `.env`: some versions of the Laravel installer write the port twice (`http://localhost:8000:8000`).

Before the first push, tell Composer which PHP version the server runs:

```bash
composer config platform.php 8.4.24   # the version `php -v` prints on the server
composer update --lock
```

Composer then resolves every update for that version, on any machine. Dependabot needs it: without the pin it resolves for PHP 8.4.0, which some packages no longer accept, and its update fails with `dependency_file_not_resolvable`.

### While the package is private

MelaCMS is not on Packagist yet, which is why the `composer config repositories` line is there. Composer also needs a GitHub token that can read this repository: a fine-grained personal access token with read-only **Contents** access to `melasistema/mela-cms`.

On your machine:

```bash
composer config --global github-oauth.github.com <token>
```

On the site's GitHub repository, so the workflows can install the package:

| Where | Name | Value |
| :--- | :--- | :--- |
| Actions secret | `COMPOSER_AUTH` | `{"github-oauth":{"github.com":"<token>"}}` |
| Dependabot secret | `COMPOSER_AUTH` | Same value |
| Dependabot secret | `COMPOSER_TOKEN` | The token alone |

`COMPOSER_AUTH` holds the whole JSON, not the token alone: with the bare token the workflows stop with "COMPOSER_AUTH environment variable is malformed".

Then uncomment the two `registries` blocks in the site's `.github/dependabot.yml`.

### What `mela:install` does

1. Publishes `config/mela-cms.php` and writes the site locales into it.
2. Publishes the roles and permissions migration and runs the migrations.
3. Adds `implements FilamentUser` and the `HasCmsAccess` trait to `app/Models/User.php`. Without them nobody can log in to the admin.
4. Creates the roles and the super-admin account.
5. Publishes Filament's assets and adds `@php artisan filament:upgrade` to the `post-autoload-dump` scripts in `composer.json`, so the assets follow every Composer install.
6. Links `public/storage` to `storage/app/public`, where the media library keeps its files.
7. Copies the workflow templates into `.github/`.
8. Adds the MelaCMS section to `AGENTS.md` and `CLAUDE.md`.

The command is safe to run again. Each step skips what is already in place, and a workflow file you have changed is kept unless you confirm replacing it.

### Options

| Option | Purpose |
| :--- | :--- |
| `--name=` | Super-admin name |
| `--email=` | Super-admin email |
| `--password=` | Super-admin password, at least 8 characters |
| `--locales=` | Comma-separated site locales, for example `en,it` |
| `--force` | Replace project files that differ from the core templates |

Without the options the command asks for each value. When it runs without interaction (in CI, for example), `--email` and `--password` are required:

```bash
php artisan mela:install --no-interaction \
    --email=admin@example.com --password=change-me-now --locales=en,it
```

If the email belongs to an existing user, that user keeps their password and gains the `super-admin` role.

## Configuration

`config/mela-cms.php`:

| Key | Default | Meaning |
| :--- | :--- | :--- |
| `locales` | `['en']` | Languages the site is published in. The first is the default. A starting value: see [Languages](#languages) |
| `locale_urls` | `'path'` | Where the other languages live: `'path'` for `example.com/it`, `'subdomain'` for `it.example.com` |
| `admin_locale` | `null` | Language of the admin panel. `null` uses the app locale. Also a starting value |
| `admin_path` | `admin` | URL path of the admin panel |
| `pages` | `['settings' => ManageSettings::class, 'languages' => ManageLanguages::class]` | The admin pages the core adds. Works like `resources` |
| `profile_page` | `EditProfile::class` | The page where a user edits their own account. `null` removes it; your own class replaces it |
| `resources` | `['pages' => PageResource::class, 'media' => MediaResource::class, 'users' => UserResource::class]` | The admin screens the core adds. Set an entry to `null` to remove the screen, or to your own class to replace it |
| `public_pages` | `true` | The core answers every address no route of the site matches with the published page at that address. See [Pages](#pages) |
| `sitemap` | `[Page::class]` | What `/sitemap.xml` lists. See [SEO](#seo) |
| `media` | See [Media](#media) | Disk, directory, accepted types, maximum size and image sizes of the media library |

## Building a site

The admin panel is registered by the core. It picks up the site's own Filament classes from these directories, so adding a screen to the admin means adding a class to the site:

- `app/Filament/Resources`
- `app/Filament/Pages`
- `app/Filament/Widgets`

Everything specific to the site stays in the site's repository: models, migrations, Filament resources, Blade views, routes and the theme. Nothing in `vendor/melasistema/mela-cms` is ever edited.

### Roles

| Role | Access |
| :--- | :--- |
| `super-admin` | Passes every authorization check |
| `admin` | Everything an editor does, plus the languages, the settings and the users, except super-admin accounts |
| `editor` | Writes, publishes and deletes pages and the site's own content, and uses the media library. Cannot see the users, languages and settings screens |

The roles are checked in policies; there are no per-role permissions to configure. A site resource without a policy is open to every role. To restrict one, register a policy for its model with `Gate::policy()`.

Roles are provided by [spatie/laravel-permission](https://github.com/spatie/laravel-permission), so the usual methods work on the user model:

```php
use Melasistema\MelaCms\Enums\Role;

$user->assignRole(Role::Editor->value);
$user->hasRole(Role::Admin->value);
```

A user with none of the three roles cannot enter the panel.

### Users

The core adds a **Users** screen at `/admin/users`. Each user has one role.

- Only a super-admin can give the `super-admin` role, or edit and delete a super-admin account.
- Nobody can delete their own account or change their own role.

The rules are in `Melasistema\MelaCms\Policies\UserPolicy`. To change them, register your own policy for the user model in the site's `AppServiceProvider`:

```php
Gate::policy(User::class, \App\Policies\UserPolicy::class);
```

To add fields, extend the core resource and name your class in `config/mela-cms.php`:

```php
namespace App\Filament\Resources;

use Filament\Forms\Components\TextInput;
use Melasistema\MelaCms\Filament\Resources\Users\UserResource as CoreUserResource;

class UserResource extends CoreUserResource
{
    public static function formComponents(): array
    {
        return [
            ...parent::formComponents(),
            TextInput::make('phone'),
        ];
    }
}
```

```php
// config/mela-cms.php
'resources' => [
    'users' => \App\Filament\Resources\UserResource::class,
],
```

`tableColumns()` works the same way for the list. Set `'users' => null` to remove the screen.

### Profile

Every user, whatever their role, has a profile page at `/admin/profile`, reached from the user menu. They can change their name, email and password there; changing the email or the password asks for the current password.

To add fields, extend `Melasistema\MelaCms\Filament\Pages\EditProfile`, override `form()`, and set your class as `profile_page` in `config/mela-cms.php`. Set it to `null` to remove the page.

### Languages

The core adds a **Languages** page at `/admin/languages`, for super-admins and admins. It holds three choices:

| Choice | Meaning |
| :--- | :--- |
| Site languages | The languages the site is published in |
| Default language | The one served at the root of the site |
| Admin panel language | The language of the panel. It does not have to be a site language |

`locales` and `admin_locale` in `config/mela-cms.php` are the starting values. Once the page has been saved, the saved values are used and the config entries are ignored. They are stored in the `mela_settings` table.

Read the languages in site code through `Melasistema\MelaCms\Languages`, never from the config:

```php
use Melasistema\MelaCms\Languages;

$languages = app(Languages::class);

$languages->all();          // ['en', 'it'], the default first
$languages->default();      // 'en'
$languages->translations(); // ['it']
$languages->has('it');      // true
Languages::name('it');      // 'Italiano'
```

#### Language URLs

The default language is at the root of the site. Every other language has a prefix:

| URL | Language, with `en` as the default |
| :--- | :--- |
| `example.com/about` | English |
| `example.com/it/about` | Italian |
| `example.com/en/about` | Redirects to `example.com/about` |

Write each route once, without a language in it:

```php
Route::get('/about', AboutController::class)->name('about');
```

On `/it/about` the same route runs with the app locale set to `it`, and `route()`, `url()`, redirects and pagination links keep the `/it` prefix. `asset()` and Vite URLs stay at the root. When an admin changes the default language, the new one moves to the root and the old one gets a prefix at once: there are no routes to rebuild and no cache to clear.

To put the languages on subdomains (`it.example.com`), set `'locale_urls' => 'subdomain'` in `config/mela-cms.php`, point each subdomain to the app, and set `SESSION_DOMAIN=.example.com` if a login has to be shared between them.

For a language switcher and for `hreflang`:

```blade
<head>
    @hreflang
</head>

@foreach (app(\Melasistema\MelaCms\Languages::class)->alternates() as $locale => $url)
    <a href="{{ $url }}">{{ \Melasistema\MelaCms\Languages::name($locale) }}</a>
@endforeach
```

```php
$languages->current();              // 'it' on /it/about
$languages->alternates();           // ['en' => 'https://example.com/about', 'it' => 'https://example.com/it/about']
$languages->url('it', '/contact');  // 'https://example.com/it/contact'
```

The admin panel answers under a prefix too (`/it/admin`), in the admin language. A Livewire component on a translated page sends its updates to the root of the site: links it renders again during an update lose the prefix unless they are built with `$languages->url()`.

#### Translatable content

Translations are stored in the model's own table, in JSON columns, through [spatie/laravel-translatable](https://spatie.be/docs/laravel-translatable) (installed with the core). No translation tables, and no data moves when the default language changes.

```php
// Migration
$table->json('title');
$table->json('body')->nullable();

// Model
use Spatie\Translatable\HasTranslations;

class Article extends Model
{
    use HasTranslations;

    public array $translatable = ['title', 'body'];
}
```

`$article->title` is the title in the language of the request. A translation that is missing falls back to the default language of the site.

In a Filament form, wrap the translatable fields in `Translations::make()` and write each field once:

```php
use Melasistema\MelaCms\Filament\Forms\Translations;

Translations::make([
    TextInput::make('title')->required(),
    RichEditor::make('body'),
]),
```

| Behaviour | Detail |
| :--- | :--- |
| Tabs | One per site language, the default first. A site in one language gets the fields without tabs |
| `required()` | Applies to the default language only. Every other rule applies to each language |
| Removed language | Its translations stay in the database and come back if the language is added again |

To change the page, extend `Melasistema\MelaCms\Filament\Pages\ManageLanguages` (its fields are in `formComponents()`) and name your class under `pages` in the config. Set `'languages' => null` to remove it.

### Settings

The core adds a **Settings** page at `/admin/settings`, for super-admins and admins, with what every site has:

| Section | Fields | Read as |
| :--- | :--- | :--- |
| Site | Name and description, one per site language; home page; share image | `site.name`, `site.description`, `site.home_page`, `site.share_image` |
| Search engines | Title format; a switch that asks search engines not to list the site | `site.seo.title_format`, `site.seo.noindex` |
| Contact | Email, phone, address | `site.contact.email`, `site.contact.phone`, `site.contact.address` |
| Social profiles | Facebook, Instagram, LinkedIn, X, YouTube, TikTok | `site.social.instagram`, ... |

Read them in site code through `Melasistema\MelaCms\Settings`:

```php
use Melasistema\MelaCms\Settings;

$settings = app(Settings::class);

$settings->get('site.contact.email');                    // 'info@example.com', or null
$settings->get('site.contact.phone', '-');               // with a default
$settings->translated('site.name', config('app.name'));  // in the language of the request
Media::find($settings->get('site.share_image'));         // a file of the media library, or null
array_filter($settings->get('site.social', []));         // ['instagram' => 'https://...'], only the ones filled in
```

`translated()` is for a value that has one entry per language. An empty translation falls back to the default language of the site, then to the default given.

The site name is also the name shown in the admin panel. Until one is saved, the panel shows `APP_NAME`.

To add settings of your own, extend the page, add fields, and name your class under `pages` in the config. Every field is saved under `site` at its own name:

```php
namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Melasistema\MelaCms\Filament\Pages\ManageSettings;

class Settings extends ManageSettings
{
    public static function formComponents(): array
    {
        return [
            ...parent::formComponents(),
            Section::make('Booking')->schema([
                TextInput::make('booking.url')->url(),   // read as site.booking.url
            ]),
        ];
    }
}
```

```php
// config/mela-cms.php
'pages' => [
    'settings' => App\Filament\Pages\Settings::class,
],
```

Wrap a field in `Translations::make()` to have it per language. `socialNetworks()` can be overridden to change the list of profiles. Set `'settings' => null` to remove the page.

All settings are stored in the `mela_settings` table and read with one query per request.

### Pages

The Pages screen (`/admin/pages`, every role) holds the pages of the site. A page has:

| Field | Detail |
| :--- | :--- |
| Title, address, text | One per site language. The address is written from the title and can be changed; it may have levels (`services/web-design`) |
| Published on | Empty: a draft. A date to come: public from that date |
| Template | One of the site's views in `resources/views/pages`. Shown once the site has more than one |
| Cover image | A file of the media library |

#### How a page reaches the site

The core answers every address that no route of the site matches. It looks for the published page at that address in the language of the request, and shows it with the view of its template:

| Request | Page shown |
| :--- | :--- |
| `/about` | The page whose address is `about` in the default language |
| `/it/chi-siamo` | The page whose Italian address is `chi-siamo` |
| `/it/about` | The page whose address is `about` in the default language, when it has no Italian address |
| `/` | The page chosen as home page on the Settings page |

A route of the site always wins. A fresh Laravel app has a route for `/` in `routes/web.php`: remove it to let the home page setting work. A draft answers with a 404, except for someone logged in who may edit pages. `@hreflang` and `alternates()` give the address of the page in each language.

Until the site has its own view, the core shows a page with a plain one. Create `resources/views/pages/default.blade.php` to give the pages the look of the site:

```blade
<x-layout :title="$page->title">
    <h1>{{ $page->title }}</h1>

    @if ($page->cover)
        <img src="{{ $page->cover->url('large') }}" alt="{{ $page->cover->alt }}">
    @endif

    @richtext($page->body)
</x-layout>
```

Every other file in that directory is a template an editor can choose: `resources/views/pages/landing-page.blade.php` is offered as "Landing Page". A page whose template was removed is shown with `default`.

| In a view | Returns |
| :--- | :--- |
| `$page->title`, `$page->slug` | In the language of the request, with the default language as fallback |
| `@richtext($page->body)` | The text as sanitized HTML |
| `$page->cover` | The cover image, a [`Media`](#media) record, or `null` |
| `$page->url()`, `$page->url('it')` | The full address of the page |
| `$page->isHome()` | Whether it is the home page |
| `Page::published()->get()` | The pages visitors may see, for example to build a menu |

Print the text with `@richtext`, not with `{!! !!}`: it removes what could run in the browser and reads the address of each image again from the media disk.

To serve the pages yourself, set `'public_pages' => false` in the config and find a page with `Page::published()->whereSlug($slug)->firstOrFail()`. A `Route::fallback()` of the site also replaces the core's.

#### Fields for one template

A template that needs more than a title and a text gets its fields from the site. Extend the resource and add them under `fields`:

```php
namespace App\Filament\Resources;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Melasistema\MelaCms\Filament\Forms\Translations;
use Melasistema\MelaCms\Filament\Resources\Pages\PageResource as CorePageResource;

class PageResource extends CorePageResource
{
    public static function formComponents(): array
    {
        return [
            ...parent::formComponents(),
            Section::make('Hero')
                ->visible(fn (Get $get): bool => $get('template') === 'home')
                ->schema([
                    TextInput::make('fields.hero.link'),
                    Translations::make([TextInput::make('fields.hero.button')]),
                ]),
        ];
    }
}
```

```php
// config/mela-cms.php
'resources' => [
    'pages' => \App\Filament\Resources\PageResource::class,
],
```

```blade
<a href="{{ $page->field('hero.link') }}">{{ $page->translated('hero.button') }}</a>
```

The values are saved in one JSON column, so no migration is needed.

### Your own content

An entity that is not a page (a post, a project, a product) belongs to the site: a migration, a model, a Filament resource in `app/Filament/Resources`, a route and a view. The core gives the parts that should work the same everywhere.

The full recipe is in [`docs/entity.md`](docs/entity.md): what to decide first, every file of a complete `Service` entity (migration, model, admin screen, controller, routes, views, sitemap entry, test) and the common variants. It is installed with the package, and the agent guidelines in the site's `AGENTS.md` and `CLAUDE.md` tell a coding agent to read it at `vendor/melasistema/mela-cms/docs/entity.md` before building an entity. Its code is run by the core's tests. What follows is the short version.

```php
// The migration
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->json('title');
    $table->json('slug');
    $table->json('body')->nullable();
    $table->foreignId('cover_id')->nullable()->constrained('mela_media')->nullOnDelete();
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
});
```

```php
// The model
use Melasistema\MelaCms\Concerns\HasSlug;
use Melasistema\MelaCms\Concerns\Publishable;
use Spatie\Translatable\HasTranslations;

class Post extends Model
{
    use HasSlug, HasTranslations, Publishable;

    public array $translatable = ['title', 'slug', 'body'];
}
```

```php
// The form of the resource
Translations::make([
    TextInput::make('title')->required()->live(onBlur: true)->afterStateUpdated(SlugInput::fill()),
    SlugInput::make('slug')->required(),
    RichText::make('body'),
]),
DateTimePicker::make('published_at'),
MediaPicker::make('cover_id')->images(),
```

```php
// The route, written once for every language
Route::get('/blog/{slug}', function (string $slug) {
    $post = Post::published()->whereSlug($slug)->firstOrFail();

    app(Languages::class)->localize(array_map(fn ($slug) => "blog/{$slug}", $post->slugs()));

    return view('posts.show', ['post' => $post]);
});
```

| Part | What it does |
| :--- | :--- |
| `HasSlug` | `whereSlug($slug)` finds the record in the language of the request; a language without a slug uses the one of the default language. `slugs()` gives the slug in every language |
| `Publishable` | `published()` keeps what visitors may see; `isPublished()` and `status()` (draft, scheduled, published), which a table column shows as a badge with `->state(fn ($record) => $record->status())->badge()` |
| `SlugInput` | Accepts lowercase letters, numbers and hyphens, and refuses a slug another record of the model has in the same language. `SlugInput::fill()` on the title writes the slug while it is empty. `->path()` allows levels |
| `RichText` | A rich text field whose images go into the media library. Printed with `@richtext($post->body)` |
| `Languages::localize()` | Tells the language switcher and `@hreflang` where the current page is in each language |
| `ContentPolicy` | Every core role may do everything. Not needed for that, since a resource without a policy is open to every role; write your own policy to restrict a model |

### SEO

Put `@seo` in the `<head>` of the layout, next to `@hreflang`. It prints the `<title>`, the description, the canonical address, the `robots` tag when the page is not to be listed, and the Open Graph and Twitter tags used when the address is shared.

```blade
<head>
    <meta charset="utf-8">
    @seo
    @hreflang
</head>
```

| Tag | Value, first one that exists |
| :--- | :--- |
| Title | What the editor wrote for search engines, the title of the record, the name of the site. Written in the title format of the Settings page, `:title | :site` by default. The home page is called like the site |
| Description | What the editor wrote for search engines, the `excerpt` of the record when it has one, the description of the site |
| Image | The share image of the record, its cover, the share image of the site. The original file is used, not a WebP size |
| `noindex` | The record asks for it, the record is not published, or the Settings page asks search engines not to list the site |
| Canonical | The current address without its query string |

For a page of the core there is nothing to do: each page has a "Search engines" section in its form, and the core fills the tags before showing the view.

For a route of the site, fill the tags from a record or value by value:

```php
use Melasistema\MelaCms\Seo;

Route::get('/blog/{slug}', function (string $slug, Seo $seo) {
    $post = Post::published()->whereSlug($slug)->firstOrFail();

    $seo->for($post)->type('article');

    return view('posts.show', ['post' => $post]);
});

Route::get('/blog', function (Seo $seo) {
    $seo->title('Blog')->description('Everything we wrote.');

    return view('posts.index');
});
```

`Seo::for()` reads the title, the `excerpt` and the `cover_id` of any model. To let an editor write the title, description and share image of a record, give the model a nullable `json` column `seo` and the `Melasistema\MelaCms\Concerns\HasSeo` trait, and add `SeoFields::make()` to the form of its resource.

#### Sitemap

`/sitemap.xml` lists every published page in every site language, with its last change and the other languages as alternates. A page kept out of search engines is left out. Add the site's own content under `sitemap` in the config:

```php
'sitemap' => [
    \Melasistema\MelaCms\Models\Page::class,
    \App\Models\Post::class,   // a model with a url(?string $locale) method
    'blog',                     // a page served by a route of the site
],
```

```php
// On the Post model
public function url(?string $locale = null): string
{
    $locale ??= app(Languages::class)->current();

    return app(Languages::class)->url($locale, 'blog/'.$this->getTranslation('slug', $locale));
}
```

An empty array removes the sitemap. It also answers 404 while the Settings page asks search engines not to list the site. A fresh Laravel app has a static `public/robots.txt`: add a `Sitemap: https://example.com/sitemap.xml` line to it.

### Media

The core adds a **Media** library at `/admin/media`, for every role. A file is uploaded once, several at a time if needed, and can then be used by any record of the site. Each file has a name and, for an image, an alternative text per site language.

Every image gets these sizes at upload, as WebP files next to the original:

| Size | Result |
| :--- | :--- |
| `thumb` | 400 × 400, cropped to fill the square |
| `medium` | 800 wide, same proportions |
| `large` | 1600 wide, same proportions |

An image is never enlarged, and the original is kept as uploaded. Animated GIFs, PDFs and other documents keep the original only.

#### Giving a model an image

Add a nullable foreign key to the `mela_media` table, and a relation:

```php
// migration
$table->foreignId('cover_id')->nullable()->constrained('mela_media')->nullOnDelete();
$table->json('gallery')->nullable();
```

```php
use Melasistema\MelaCms\Models\Media;

class Article extends Model
{
    protected function casts(): array
    {
        return ['gallery' => 'array'];
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_id');
    }

    public function galleryMedia(): Collection
    {
        return Media::inOrder($this->gallery);   // skips a file that was deleted
    }
}
```

In the Filament form, `MediaPicker` picks a file from the library or uploads a new one into it:

```php
use Melasistema\MelaCms\Filament\Forms\MediaPicker;

MediaPicker::make('cover_id')->images(),             // saves the id of the file
MediaPicker::make('gallery')->images()->multiple(),  // saves a list of ids
```

`images()` limits the field to images. `MediaPicker` extends Filament's `Select`, so `required()`, `label()` and the rest work as usual.

In a Blade view:

```blade
@if ($article->cover)
    <img
        src="{{ $article->cover->url('large') }}"
        srcset="{{ $article->cover->srcset() }}"
        sizes="(min-width: 1024px) 800px, 100vw"
        width="{{ $article->cover->width }}"
        height="{{ $article->cover->height }}"
        alt="{{ $article->cover->alt }}"
    >
@endif
```

| Method | Returns |
| :--- | :--- |
| `url()` | The address of the original file |
| `url('thumb')` | The address of a size. A file without that size gives the original |
| `srcset()` | The sizes that keep the proportions, for the `srcset` attribute. `null` when there are none |
| `alt` | The alternative text in the language of the request, with the default language as fallback |
| `isImage()` | Whether the file is an image |

#### Configuration

```php
// config/mela-cms.php
'media' => [
    'disk' => 'public',
    'directory' => 'media',
    'max_size' => 10240,   // kilobytes
    'accepted_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'],
    'conversions' => [
        'thumb' => ['width' => 400, 'height' => 400],
        'medium' => ['width' => 800],
        'large' => ['width' => 1600],
    ],
    'quality' => 80,
],
```

A size with a width and a height is cropped to fill them; with one of the two it keeps its proportions. After changing `conversions` or `quality`, make the sizes of the files already uploaded again:

```bash
php artisan mela:media-regenerate
```

The disk must be public. With the default one the files are served through the `public/storage` link, which `mela:install` and the deploy workflow create. PHP's `upload_max_filesize` and `post_max_size` must allow `max_size`, and Livewire has its own limit of 12 MB for an upload.

SVG is not accepted by default: an SVG file can carry a script, and it would run on your domain. Add `image/svg+xml` to `accepted_types` only if everyone who can log in is trusted.

To change the screen, extend `Melasistema\MelaCms\Filament\Resources\Media\MediaResource`, override `formComponents()` or `tableColumns()`, and set your class as `resources.media` in the config. To change who may use the library, register your own policy for `Media` with `Gate::policy()`.

## Updating the core

On your machine, in the site:

```bash
composer update melasistema/mela-cms
php artisan mela:update
```

While the core is below `1.0`, a new minor version is outside the constraint (`^0.2` does not allow `0.3.0`). Raise it instead: `composer require melasistema/mela-cms:^0.3`.

`mela:update` runs new core migrations, restores any missing core role, links `public/storage` if the link is missing and refreshes the MelaCMS section of `AGENTS.md` and `CLAUDE.md`. It never overwrites a file the site owns: it lists the workflow files that differ from the core templates and leaves the decision to you. To take the new version of one, copy it from `vendor/melasistema/mela-cms/stubs/`, or run `mela:install` again to review them one by one.

Then commit `composer.json`, `composer.lock`, the two agent guides and any workflow file you copied, as a `fix:` (for example `fix: update MelaCMS to 0.3.0`), and release the site as usual. A `chore:` would not make a release, so nothing would be deployed.

Never update on the server. The deploy builds `vendor/` from the committed `composer.lock`, so the next deploy would put the old core back, and the new one would have gone live without passing CI.

### With Dependabot

The installed `.github/dependabot.yml` opens a pull request on the site whenever a new core version is released, with the constraint raised and CI already run. It replaces the Composer command, not the rest: after merging it, pull, run `composer install` and `php artisan mela:update`, and commit the agent guides. Without `composer install` first, `mela:update` works on the old core still in `vendor/` and finds nothing to do.

Dependabot needs the PHP version pin from [Installation](#installation) and, while the package is private, its two secrets.

## Releasing and deploying a site

The installer copies four files into the site. From then on they belong to the site and can be changed freely.

| File | Runs on | Does |
| :--- | :--- | :--- |
| `.github/workflows/ci.yml` | Push to `development`, pull requests | Pint check, asset build, `php artisan test` |
| `.github/workflows/release.yml` | Push to `master` | CI, then the release PR, then the deploy when a release is created |
| `.github/workflows/deploy.yml` | Called by the release, or by hand | Builds the site and copies it to the server over SSH |
| `.github/dependabot.yml` | Daily | Pull request for a new MelaCMS version |

The flow on a site:

1. Work on `development` with [conventional commits](https://www.conventionalcommits.org) (`feat: ...`, `fix: ...`, `feat!: ...` for a breaking change).
2. Merge `development` into `master` and push.
3. [release-please](https://github.com/googleapis/release-please) opens a release PR with the next version and the `CHANGELOG.md` entry.
4. Merge the release PR. This creates the tag and the GitHub Release, and deploys that tag.

So a release takes two merges. Merging `development` into `master` only opens the release PR and deploys nothing.

One-time setup on the site's GitHub repository:

- Create the `development` and `master` branches.
- Under Settings → Actions → General → Workflow permissions, enable **Allow GitHub Actions to create and approve pull requests**.
- Commit `package-lock.json`. The workflows install with `npm ci`, which needs it.
- To deploy, set the variables and secrets listed at the top of `.github/workflows/deploy.yml`: `DEPLOY_PATH`, `SSH_HOST`, `SSH_USER`, `SSH_KEY` and `MAINTENANCE_TOKEN`. When `php` on the server's command line is older than 8.4, which is common on shared hosting, also set the `PHP_BIN` variable to the 8.4 binary, for example `php8.4`. The deploy stops with a message that says so.

One-time setup on the server:

- PHP 8.4 on the command line, with the extensions listed under [Requirements](#requirements). Check with `php -v` and `php -m` over SSH: the command-line PHP can differ from the one serving the site.
- The web root points to `public/` inside `DEPLOY_PATH`.
- A `.env` in `DEPLOY_PATH` with `APP_KEY`, `APP_URL` and the database. The deploy never copies or changes it.

The deploy runs the migrations and creates no account, so after the first deploy nobody can log in yet. Once, over SSH, in the app directory on the server:

```bash
php artisan mela:super-admin
```

It creates the three roles if they are missing, asks for an email, a name and a password, and creates the super-admin account. If the email belongs to an existing user, that user keeps their name and password and gains the role, so the same command also brings back access to a site that has lost its last super-admin. Without interaction, pass `--email`, `--password` and optionally `--name`. The other users are then created in the panel.

The site is built in GitHub Actions, so the server needs only PHP 8.4 and SSH access. Two limits to know about: the deploy copies over the live directory, so files removed from the repository stay on the server, and there is no rollback step.

The post-deployment script stops at the first error, so a failed migration leaves the site in maintenance mode. To deploy again without a new release:

- A failed deploy: fix the cause, then **Re-run failed jobs** on the workflow run, or `gh run rerun <run id> --failed`. Variables and secrets are read again.
- Any tag or branch: Actions → Deploy → Run workflow.

| Problem | Cause | Fix |
| :--- | :--- | :--- |
| The deploy stops with "'php' on the server is PHP 8.3..." | The command-line `php` is older than 8.4 | Set the `PHP_BIN` variable, then run the failed deploy again |
| The copy step ends with `dial tcp ...:22: i/o timeout` | The server did not answer in time. Nothing was copied | Run the failed deploy again |
| `development` was merged into `master` and nothing was deployed | That merge only opens the release PR | Merge the release PR |
| Nobody can log in on a freshly deployed site | The deploy creates no account | `php artisan mela:super-admin` over SSH |
| Dependabot's update fails with `dependency_file_not_resolvable` | No PHP version pin in `composer.json` | The pin from [Installation](#installation), committed |

A site that is not deployed through GitHub Actions can delete the `deploy` job and `deploy.yml`. Versioning and the changelog keep working without them.

## Contributing

Changes are welcome as pull requests to `master`.

```bash
git clone https://github.com/melasistema/mela-cms && cd mela-cms
composer install
```

| Task | Command |
| :--- | :--- |
| Run the tests | `composer test` |
| Check formatting (what CI runs) | `composer lint` |
| Fix formatting | `composer format` |

The tests run inside a throwaway Laravel app provided by [Orchestra Testbench](https://packages.tools/testbench), on in-memory SQLite. No `.env` or database server is needed.

### Trying a change in a real app

The package has no `artisan` and no pages of its own. To click through the admin while you work, require your clone into a throwaway Laravel app next to it, through a Composer path repository:

```bash
cd .. && laravel new mela-cms-sandbox && cd mela-cms-sandbox
composer config repositories.mela-cms path ../mela-cms
composer require melasistema/mela-cms:@dev
php artisan mela:install
npm install
composer run dev
```

Composer symlinks `vendor/melasistema/mela-cms` to the clone, so an edit in the package is live in the app at `http://localhost:8000/admin`. The sandbox is disposable: delete it and create it again whenever you want a clean install. A path repository is for development only and never belongs in a real site.

### How a change gets in

1. Create a branch from `master`, named after the work: `feat/media-library`, `fix/login-redirect`.
2. Open a pull request to `master`. Every push runs the formatting check, the tests and a check on the pull request title.
3. Give the pull request a conventional-commit title, such as `feat: add the media library`. Pull requests are squash-merged, so the title becomes the commit on `master` and the line in the changelog. The commits inside the branch can be named freely.
4. Add a test for new behaviour.

### How releases are made

Nobody tags a release by hand. After each merge to `master`, release-please opens or updates a single release PR that collects the `feat` and `fix` changes since the last version and proposes the next one. Merging that PR creates the `vX.Y.Z` tag and the GitHub Release.

Versions follow [semantic versioning](https://semver.org). While the package is below `1.0`, a breaking change raises the minor version. Changes are listed in [CHANGELOG.md](CHANGELOG.md).

## License

MelaCMS is open source under the **MIT License**: free to use, modify and self-host. See [LICENSE](LICENSE) for the full text.

© 2026 Luca Visciola (Melasistema)
