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
| Own-profile page | Planned |
| `mela:install` and `mela:update` commands | Available |
| CI, release and deploy workflows for client sites | Available, not yet proven on a live site |
| Site locales | Stored in the config; nothing reads them yet |
| Translatable content and localized routes | Planned |
| Content model, media, SEO | Planned |

## Requirements

- PHP 8.4
- Laravel 13
- Node 24, for the site's asset build
- A database supported by Laravel

## Installation

Start from a fresh Laravel app:

```bash
laravel new client-site && cd client-site
composer config repositories.mela-cms vcs https://github.com/melasistema/mela-cms
composer require melasistema/mela-cms
php artisan mela:install
```

Then open `/admin` and log in with the super-admin account you just created.

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

Then uncomment the two `registries` blocks in the site's `.github/dependabot.yml`.

### What `mela:install` does

1. Publishes `config/mela-cms.php` and writes the site locales into it.
2. Publishes the roles and permissions migration and runs the migrations.
3. Adds `implements FilamentUser` and the `HasCmsAccess` trait to `app/Models/User.php`. Without them nobody can log in to the admin.
4. Creates the roles and the super-admin account.
5. Publishes Filament's assets and adds `@php artisan filament:upgrade` to the `post-autoload-dump` scripts in `composer.json`, so the assets follow every Composer install.
6. Copies the workflow templates into `.github/`.
7. Adds the MelaCMS section to `AGENTS.md` and `CLAUDE.md`.

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
| `locales` | `['en']` | Languages the site is published in. The first is the default |
| `admin_path` | `admin` | URL path of the admin panel |
| `resources` | `['users' => UserResource::class]` | The admin screens the core adds. Set an entry to `null` to remove the screen, or to your own class to replace it |

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
| `admin` | Can enter the admin panel and manage users, except super-admin accounts |
| `editor` | Can enter the admin panel. Cannot see the users screen |

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

## Updating the core

```bash
composer update melasistema/mela-cms
php artisan mela:update
```

`mela:update` runs new core migrations, restores any missing core role and refreshes the MelaCMS section of `AGENTS.md` and `CLAUDE.md`. It never overwrites a file the site owns: it lists the workflow files that differ from the core templates and leaves the decision to you. Run `mela:install` again to review them one by one.

The installed `.github/dependabot.yml` opens a pull request on the site whenever a new core version is released.

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

One-time setup on the site's GitHub repository:

- Create the `development` and `master` branches.
- Under Settings → Actions → General → Workflow permissions, enable **Allow GitHub Actions to create and approve pull requests**.
- Commit `package-lock.json`. The workflows install with `npm ci`, which needs it.
- To deploy, set the variables and secrets listed at the top of `.github/workflows/deploy.yml`: `DEPLOY_PATH`, `SSH_HOST`, `SSH_USER`, `SSH_KEY` and `MAINTENANCE_TOKEN`.

The site is built in GitHub Actions, so the server needs only PHP 8.4 and SSH access. Two limits to know about: the deploy copies over the live directory, so files removed from the repository stay on the server, and there is no rollback step.

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
