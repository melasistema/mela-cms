## MelaCMS

This site is built on [MelaCMS](https://github.com/melasistema/mela-cms). This section is maintained by the CMS: `php artisan mela:update` rewrites it, so keep the project's own notes outside the `mela-cms-guidelines` tags.

### What is where

- **MelaCMS core** lives in `vendor/melasistema/mela-cms`. Never edit it. It provides the admin panel, roles (`super-admin`, `admin`, `editor`), the `mela:install` and `mela:update` commands, and core migrations.
- **This repo** holds everything specific to the site: models, migrations, Filament resources, Blade views, routes, the theme.

### Stack

- Laravel 13, PHP 8.4, Node 24.
- Admin: Filament 5 at `/admin` (path set in `config/mela-cms.php`). The panel is registered by the core and discovers this site's classes in `app/Filament/Resources`, `app/Filament/Pages` and `app/Filament/Widgets`.
- Public site: Blade views, Tailwind via Vite, Alpine for interactions. No Inertia. Vue is not installed; add it only when a page needs a component island.
- Site languages are chosen by an admin on the core's Languages page (`/admin/languages`); `locales` in `config/mela-cms.php` is only the starting value. Read them through `app(\Melasistema\MelaCms\Languages::class)` (`all()`, `default()`, `translations()`, `has()`), never from the config. The first language is the default.
- Language URLs are handled by the core: the default language is at the root, the others under a prefix (`/it/about`) or, with `'locale_urls' => 'subdomain'`, on a subdomain. Write each route once, without a locale parameter or a locale prefix, and never set the app locale yourself. `route()` and `url()` keep the language of the request.
- For a language switcher use `app(\Melasistema\MelaCms\Languages::class)->alternates()` (`[locale => url]` of the current page) or `->url($locale, $path)`; put `@hreflang` in the `<head>` of the layout. In a test, request a translated page as `$this->get('/it/about')`.
- Translatable content uses `spatie/laravel-translatable`, installed with the core: a `json` column, the `Spatie\Translatable\HasTranslations` trait and `public array $translatable = [...]` on the model. Do not create translation tables or one column per language. `$model->title` is in the language of the request and falls back to the default language of the site.
- In a Filament form, wrap translatable fields in `\Melasistema\MelaCms\Filament\Forms\Translations::make([TextInput::make('title')->required(), ...])`: it adds one tab per site language. Write each field once, without a locale in its name; `required()` applies to the default language only.
- The admin panel has its own language, also set on that page. To change the page, extend `Melasistema\MelaCms\Filament\Pages\ManageLanguages` and name the class under `pages` in `config/mela-cms.php`; `null` removes it.
- Site name, description, contact details and social profiles are filled in by an admin on the core's Settings page (`/admin/settings`); do not hard-code them in views or add a settings table. Read them with `app(\Melasistema\MelaCms\Settings::class)->get('site.contact.email')`, and the per-language ones with `->translated('site.name', config('app.name'))`. Keys: `site.name`, `site.description` (translated), `site.contact.{email,phone,address}`, `site.social.{facebook,instagram,linkedin,x,youtube,tiktok}`.
- For a setting specific to this site, extend `Melasistema\MelaCms\Filament\Pages\ManageSettings`, add fields to `formComponents()` (a field named `booking.url` is read as `site.booking.url`), and name the class under `pages.settings` in `config/mela-cms.php`. Do not create it in `app/Filament/Pages` without that config entry, or the panel shows two settings pages.
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
