## MelaCMS

This site is built on [MelaCMS](https://github.com/melasistema/mela-cms). This section is maintained by the CMS: `php artisan mela:update` rewrites it, so keep the project's own notes outside the `mela-cms-guidelines` tags.

### What is where

- **MelaCMS core** lives in `vendor/melasistema/mela-cms`. Never edit it. It provides the admin panel, roles (`super-admin`, `admin`, `editor`), the `mela:install`, `mela:update` and `mela:super-admin` commands, and core migrations.
- **This repo** holds everything specific to the site: models, migrations, Filament resources, Blade views, routes, the theme.

### Stack

- Laravel 13, PHP 8.4, Node 24.
- Admin: Filament 5 at `/admin` (path set in `config/mela-cms.php`). The panel is registered by the core and discovers this site's classes in `app/Filament/Resources`, `app/Filament/Pages` and `app/Filament/Widgets`.
- Public site: Blade views, Tailwind via Vite, Alpine for interactions. No Inertia. Vue is not installed; add it only when a page needs a component island.

### Languages

- Site languages are chosen by an admin on the core's Languages page (`/admin/languages`); `locales` in `config/mela-cms.php` is only the starting value. Read them through `app(\Melasistema\MelaCms\Languages::class)` (`all()`, `default()`, `translations()`, `has()`), never from the config. The first language is the default.
- Language URLs are handled by the core: the default language is at the root, the others under a prefix (`/it/about`) or, with `'locale_urls' => 'subdomain'`, on a subdomain. Write each route once, without a locale parameter or a locale prefix, and never set the app locale yourself. `route()` and `url()` keep the language of the request.
- For a language switcher use `app(\Melasistema\MelaCms\Languages::class)->alternates()` (`[locale => url]` of the current page) or `->url($locale, $path)`; put `@hreflang` in the `<head>` of the layout. In a test, request a translated page as `$this->get('/it/about')`.
- Translatable content uses `spatie/laravel-translatable`, installed with the core: a `json` column, the `Spatie\Translatable\HasTranslations` trait and `public array $translatable = [...]` on the model. Do not create translation tables or one column per language. `$model->title` is in the language of the request and falls back to the default language of the site.
- In a Filament form, wrap translatable fields in `\Melasistema\MelaCms\Filament\Forms\Translations::make([TextInput::make('title')->required(), ...])`: it adds one tab per site language. Write each field once, without a locale in its name; `required()` applies to the default language only.
- The admin panel has its own language, also set on that page. To change the page, extend `Melasistema\MelaCms\Filament\Pages\ManageLanguages` and name the class under `pages` in `config/mela-cms.php`; `null` removes it.

### Settings

- Site name, description, contact details and social profiles are filled in by an admin on the core's Settings page (`/admin/settings`); do not hard-code them in views or add a settings table. Read them with `app(\Melasistema\MelaCms\Settings::class)->get('site.contact.email')`, and the per-language ones with `->translated('site.name', config('app.name'))`. Keys: `site.name`, `site.description` (translated), `site.home_page` (a page id), `site.share_image` (a media id), `site.seo.title_format`, `site.seo.noindex`, `site.contact.{email,phone,address}`, `site.social.{facebook,instagram,linkedin,x,youtube,tiktok}`.
- For a setting specific to this site, extend `Melasistema\MelaCms\Filament\Pages\ManageSettings`, add fields to `formComponents()` (a field named `booking.url` is read as `site.booking.url`), and name the class under `pages.settings` in `config/mela-cms.php`. Do not create it in `app/Filament/Pages` without that config entry, or the panel shows two settings pages.

### Media

- Files and images live in the core's media library (`/admin/media`, model `Melasistema\MelaCms\Models\Media`, table `mela_media`); do not add `spatie/laravel-medialibrary`, a `FileUpload` field with its own path column, or another media table. For one image on a model: a migration column `$table->foreignId('cover_id')->nullable()->constrained('mela_media')->nullOnDelete()`, a `belongsTo(Media::class, 'cover_id')` relation, and in the Filament form `\Melasistema\MelaCms\Filament\Forms\MediaPicker::make('cover_id')->images()`. For a gallery: a `json` column cast to `array`, `MediaPicker::make('gallery')->images()->multiple()`, and `Media::inOrder($model->gallery)` to load the files.
- In a view print an image with `$media->url('large')` (sizes: `thumb` 400 × 400 cropped, `medium` 800 wide, `large` 1600 wide, all WebP; `url()` is the original), `$media->srcset()`, `$media->width`, `$media->height` and `$media->alt` (translated). Eager-load the relation in lists. The sizes are set under `media.conversions` in `config/mela-cms.php`; after changing them run `php artisan mela:media-regenerate`. The default share image of the site is `Media::find($settings->get('site.share_image'))`.

### Pages

- Pages (`/admin/pages`, model `Melasistema\MelaCms\Models\Page`, table `mela_pages`) are provided and served by the core: any address no route of this site matches shows the published page at that address, with the view `resources/views/pages/{template}.blade.php` (`default` when the template has no view). Do not write a route, a controller or a model for pages. The view gets `$page`: `$page->title`, `@richtext($page->body)`, `$page->cover` (a `Media` or null), `$page->url()`, `$page->isHome()`. Each file in `resources/views/pages` is a template an editor can choose. The home page is chosen on the Settings page; remove the `/` route from `routes/web.php` so it is used. Build a menu from `Page::published()->get()` and `$page->url()`.
- For fields only one page template needs, extend `Melasistema\MelaCms\Filament\Resources\Pages\PageResource`, add fields named `fields.<key>` to `formComponents()` (shown with `->visible(fn (Get $get) => $get('template') === 'home')`), and set the class as `resources.pages` in `config/mela-cms.php`. Read them with `$page->field('hero.link')`, or `$page->translated('hero.button')` for a field inside `Translations`. No migration is needed.

### Adding an entity

An entity is content of this site that is not a page: a service, a post, a project, a team member, a product.

**Before creating or changing one, read `vendor/melasistema/mela-cms/docs/entity.md` and follow it.** It has the decisions to take, a complete worked example and the variants. An entity is finished only when the whole slice exists:

1. A migration: `json` columns for translated fields, `slug`, `cover_id`, `seo`, `published_at` as the entity needs.
2. A model in `app/Models` with `Spatie\Translatable\HasTranslations` and the core traits `Melasistema\MelaCms\Concerns\HasSlug`, `Publishable` and `HasSeo`, a `paths()` and a `url(?string $locale = null)` method.
3. A Filament resource in `app/Filament/Resources`, built with the fields of `Melasistema\MelaCms\Filament\Forms`: `Translations`, `SlugInput`, `RichText`, `MediaPicker`, `SeoFields`.
4. A controller and two routes in `routes/web.php` (list and single record), written once for every language. The single record is found with `Service::published()->whereSlug($slug)->firstOrFail()`, then `app(\Melasistema\MelaCms\Languages::class)->localize($service->paths())` and `app(\Melasistema\MelaCms\Seo::class)->for($service)`.
5. Blade views in the layout of the site.
6. The model class in `sitemap` in `config/mela-cms.php`.
7. A feature test of the address on the site and of the admin form. In a test the roles do not exist until `app(\Melasistema\MelaCms\Install\Roles::class)->sync()` has run.

- Print rich text with `@richtext($model->body)`, never `{!! !!}`. Show the status of a record in a table with `TextColumn::make('status')->state(fn ($record) => $record->status())->badge()`.

### SEO

- SEO is handled by the core. Put `@seo` and `@hreflang` in the `<head>` of every layout and do not write `<title>`, description, canonical, robots or Open Graph tags by hand. Pages fill the tags by themselves. In a route of this site call `app(\Melasistema\MelaCms\Seo::class)->for($model)` (reads the title, `excerpt` as the description and `cover_id` as the image, and marks an unpublished record `noindex`) or `->title('Blog')->description('...')->image($media)->noindex()->type('article')`. To let editors write title, description and share image for an entity: a nullable `json` column `seo`, the trait `Melasistema\MelaCms\Concerns\HasSeo`, and `\Melasistema\MelaCms\Filament\Forms\SeoFields::make()` in the form.
- `/sitemap.xml` is served by the core and lists the pages. For an entity with its own address add its class to `sitemap` in `config/mela-cms.php` and give the model a `url(?string $locale = null): string` method built with `Languages::url()`; for a page served by a plain route add its path (`'blog'`).

### Users and roles

- Every role, editors included, may create, publish and delete content; admins also manage users, languages and settings. A site resource needs no policy for that. Write a policy and register it with `Gate::policy()` only to restrict a model.
- The user model uses `Melasistema\MelaCms\Concerns\HasCmsAccess`; check roles with `$user->hasRole(...)`.
- The core provides the profile page (`/admin/profile`), where each user edits their own name, email and password. To add fields, extend `Melasistema\MelaCms\Filament\Pages\EditProfile`, override `form()`, and set the class as `profile_page` in `config/mela-cms.php`.
- The core provides the Users screen (`/admin/users`); do not build another one. To add user fields, extend `Melasistema\MelaCms\Filament\Resources\Users\UserResource`, override `formComponents()` or `tableColumns()`, and set the class as `resources.users` in `config/mela-cms.php`. To change who may manage users, register a policy for the user model with `Gate::policy()` in `AppServiceProvider`.

### Commands

| Task | Command |
| :--- | :--- |
| Run the site locally | `composer dev` |
| Tests | `php artisan test` |
| Format PHP | `vendor/bin/pint` |
| Check formatting (what CI runs) | `vendor/bin/pint --test` |
| Update the CMS core | `composer update melasistema/mela-cms && php artisan mela:update` |

### Git, releases and deploys

- Work on `development`; `master` is what is live.
- Use conventional commits (`feat:`, `fix:`, `feat!:` for breaking changes). They decide the next version and write `CHANGELOG.md`; never edit `CHANGELOG.md` by hand.
- A push to `development` runs CI (`.github/workflows/ci.yml`).
- A push to `master` runs CI, then release-please opens or updates a release PR. Merging that PR tags the release and deploys it (`.github/workflows/release.yml`, `deploy.yml`).
- Dependabot opens a PR on `development` when a new MelaCMS core version is released.

### Before finishing a change

1. `vendor/bin/pint`
2. `php artisan test`
3. New behaviour has a test.
