## MelaCMS

This site is built on [MelaCMS](https://github.com/melasistema/mela-cms). This section is maintained by the CMS: `php artisan mela:update` rewrites it, so keep the project's own notes outside the `mela-cms-guidelines` tags.

### What is where

- **MelaCMS core** lives in `vendor/melasistema/mela-cms`. Never edit it. It provides the admin panel, roles (`super-admin`, `admin`, `editor`), the `mela:install` and `mela:update` commands, and core migrations.
- **This repo** holds everything specific to the site: models, migrations, Filament resources, Blade views, routes, the theme.

### Stack

- Laravel 13, PHP 8.4, Node 24.
- Admin: Filament 5 at `/admin` (path set in `config/mela-cms.php`). The panel is registered by the core and discovers this site's classes in `app/Filament/Resources`, `app/Filament/Pages` and `app/Filament/Widgets`.
- Public site: Blade views, Tailwind via Vite, Alpine for interactions. No Inertia. Vue is not installed; add it only when a page needs a component island.
- Site locales are listed in `config/mela-cms.php`.
- The user model uses `Melasistema\MelaCms\Concerns\HasCmsAccess`; check roles with `$user->hasRole(...)`.

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
