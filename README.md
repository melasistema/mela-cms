<p align="center">
  <a href="https://docs.mela-cms.melasistema.com">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset=".github/art/logo-dark.svg">
      <img src=".github/art/logo.svg" alt="MelaCMS" width="112" height="112">
    </picture>
  </a>
</p>

<h1 align="center">MelaCMS</h1>

<p align="center">
  <strong>The CMS for the agentic era.</strong><br>
  Your coding agent builds the site, your client gets a clean admin panel, and it all runs on a cheap shared host.
</p>

<p align="center">
  <a href="https://github.com/melasistema/mela-cms/actions/workflows/release.yml"><img src="https://img.shields.io/github/actions/workflow/status/melasistema/mela-cms/release.yml?branch=master&label=CI" alt="CI"></a>
  <a href="https://github.com/melasistema/mela-cms/releases"><img src="https://img.shields.io/github/v/release/melasistema/mela-cms?color=1D6FA5" alt="Latest release"></a>
  <a href="https://docs.mela-cms.melasistema.com"><img src="https://img.shields.io/badge/docs-docs.mela--cms.melasistema.com-1D6FA5" alt="Documentation"></a>
  <img src="https://img.shields.io/badge/PHP-8.4-4A5B6A?logo=php&logoColor=white" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/Laravel-13-4A5B6A?logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/Filament-5-4A5B6A" alt="Filament 5">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-1D6FA5" alt="License: MIT"></a>
</p>

<p align="center">
  <a href="https://docs.mela-cms.melasistema.com/v1/guide/introduction"><strong>Documentation</strong></a> ·
  <a href="https://docs.mela-cms.melasistema.com/manifesto">Manifesto</a> ·
  <a href="https://docs.mela-cms.melasistema.com/vision">Vision</a> ·
  <a href="CHANGELOG.md">Changelog</a>
</p>

---

MelaCMS is a Laravel package for freelancers and studios who write client websites with coding agents and still need to hand the client a clean admin panel.

Each website is a normal Laravel app that requires it. One command adds the admin panel, the roles and a super-admin account, the GitHub workflows that test, release and deploy the site, and the instructions for the coding agents that work on it.

> [!NOTE]
> MelaCMS is at an early stage (`0.x`) and in use on a live site. While the version is below `1.0`, a minor version can contain a breaking change; the [changelog](CHANGELOG.md) says when.

## Why

WordPress was the fastest way to deliver a small website. With a coding agent it is the slowest: the site lives in database rows and third-party plugins that an agent cannot read, change or test.

MelaCMS keeps what made WordPress win (an admin panel for the client, cheap hosting, one developer per site) and turns everything else into code. [Read the manifesto →](https://docs.mela-cms.melasistema.com/manifesto)

## Features

- **The site is code.** Models, migrations, admin screens and views live in the site's repository, where an agent can work on them and tests can cover them.
- **Built for coding agents.** The installer writes the rules of MelaCMS into `AGENTS.md` and `CLAUDE.md`, and the core ships a tested guide that takes an agent from the migration to the test.
- **A panel your client understands.** Pages, a media library, languages, settings and users in a Filament 5 admin panel, with roles for super-admins, admins and editors.
- **Languages built in.** Translated content and addresses, a language switcher, `hreflang`, and a default language that can change at any time.
- **Media and SEO built in.** WebP image sizes at upload, title and share tags per page, and `/sitemap.xml`.
- **Flexible and fast.** Each kind of content has its own indexed table, translations sit in the same row, and a template field needs no migration.
- **Shipping from the first commit.** CI, conventional commits, versions, changelog and an SSH deploy to shared hosting come with the install. Core updates take one command.

## Quick start

Requires PHP 8.4 with `intl` and GD or Imagick, Laravel 13, and Node 24 for the site's asset build.

```bash
laravel new client-site && cd client-site
composer require melasistema/mela-cms
php artisan mela:install
```

Open `/admin` and log in with the account you just created.

> [!IMPORTANT]
> Before the site's first push, pin the PHP version of your server in `composer.json`. The [installation guide](https://docs.mela-cms.melasistema.com/v1/guide/setup) shows how, and why Dependabot needs it.

## Documentation

The full documentation is at **[docs.mela-cms.melasistema.com](https://docs.mela-cms.melasistema.com)**.

| Getting started | Building a site | Shipping and reference |
| :--- | :--- | :--- |
| [What is MelaCMS?](https://docs.mela-cms.melasistema.com/v1/guide/introduction) | [Admin panel and users](https://docs.mela-cms.melasistema.com/v1/building/admin-panel) | [Releasing and deploying](https://docs.mela-cms.melasistema.com/v1/guide/deploying) |
| [Installation](https://docs.mela-cms.melasistema.com/v1/guide/setup) | [Languages](https://docs.mela-cms.melasistema.com/v1/building/languages) | [Updating the core](https://docs.mela-cms.melasistema.com/v1/guide/updating) |
| [For WordPress developers](https://docs.mela-cms.melasistema.com/v1/guide/coming-from-wordpress) | [Settings](https://docs.mela-cms.melasistema.com/v1/building/settings) | [Configuration](https://docs.mela-cms.melasistema.com/v1/reference/configuration) |
| [Working with coding agents](https://docs.mela-cms.melasistema.com/v1/guide/agents) | [Pages](https://docs.mela-cms.melasistema.com/v1/building/pages) | [Commands](https://docs.mela-cms.melasistema.com/v1/reference/commands) |
| | [Your own content](https://docs.mela-cms.melasistema.com/v1/building/entities) | |
| | [Media](https://docs.mela-cms.melasistema.com/v1/building/media) | |
| | [SEO](https://docs.mela-cms.melasistema.com/v1/building/seo) | |

A coding agent working on a site reads [`docs/entity.md`](docs/entity.md) from `vendor/`: the tested recipe for a complete entity, from the migration to the test.

## Updating

On your machine, in the site, on the `development` branch:

```bash
php artisan mela:update
```

It requires the newest release, applies it, and prints the commit message to release the site with. Never update on the server. [How updates work →](https://docs.mela-cms.melasistema.com/v1/guide/updating)

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

`master` is protected, for maintainers too: it changes only through a pull request whose checks pass. There is no `development` branch.

1. Fork the repository and create a branch from `master`, named after the work: `feat/media-library`, `fix/login-redirect`.
2. Open a pull request to `master`. Every push runs the formatting check, the tests (SQLite and MySQL, PHP 8.4 and 8.5, highest and lowest dependencies) and a check on the pull request title. All of them must pass before it can be merged.
3. Give the pull request a conventional-commit title, such as `feat: add the media library`. Pull requests are squash-merged, so the title becomes the commit on `master` and the line in the changelog. The commits inside the branch can be named freely.
4. Keep one feature or one fix per pull request, so each gets its own line in the changelog.
5. Add a test for new behaviour, and update the documentation when behaviour a site developer relies on changes.

The title type decides what is released:

| Title | Effect |
| :--- | :--- |
| `feat: ...` | New minor version, listed in the changelog |
| `fix: ...` | New patch version, listed in the changelog |
| `feat!: ...`, `fix!: ...` | Breaking change |
| `docs:`, `test:`, `refactor:`, `ci:`, `chore:` | No release |

A change to a template copied into sites (`stubs/`) is a `feat` or a `fix`, even for a workflow file: sites receive it only through a release.

### How releases are made

Nobody tags a release by hand. After each merge to `master`, release-please opens or updates a single release PR that collects the `feat` and `fix` changes since the last version and proposes the next one. Merging that PR creates the `vX.Y.Z` tag and the GitHub Release.

Versions follow [semantic versioning](https://semver.org). While the package is below `1.0`, a breaking change raises the minor version.

## License

MelaCMS is open source under the [MIT License](LICENSE): free to use, modify and self-host. The MelaCMS name and logo are trademarks, see the [brand page](https://docs.mela-cms.melasistema.com/brand#trademark).

© 2026 Luca Visciola (Melasistema)
