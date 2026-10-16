# Changelog

## [0.7.0](https://github.com/melasistema/mela-cms/compare/v0.6.0...v0.7.0) (2026-10-16)


### Features

* ask about a template only when the core changed it ([164ecf3](https://github.com/melasistema/mela-cms/commit/164ecf3e22a06f1afb8451d1cacbe0155229045d))


### Bug Fixes

* run Composer on the PHP that runs artisan in mela:update ([c975485](https://github.com/melasistema/mela-cms/commit/c9754851409caa6d93898d325872c10d2e853245))

## [0.6.0](https://github.com/melasistema/mela-cms/compare/v0.5.3...v0.6.0) (2026-10-16)


### Features

* update the core with one command in mela:update ([ea09262](https://github.com/melasistema/mela-cms/commit/ea09262c84a49e230d9707cf02d36e4148d79904))

## [0.5.3](https://github.com/melasistema/mela-cms/compare/v0.5.2...v0.5.3) (2026-10-15)


### Bug Fixes

* print the full locale in og:locale and query the SEO cover image once ([f002700](https://github.com/melasistema/mela-cms/commit/f002700cbe2cab81c48d37b3fa2b89d589d1823e))

## [0.5.2](https://github.com/melasistema/mela-cms/compare/v0.5.1...v0.5.2) (2026-10-15)


### Bug Fixes

* qualify the publication date column in the published scope ([27b086e](https://github.com/melasistema/mela-cms/commit/27b086e1de8bd92338c2391f72ecd6067ab52e2e))

## [0.5.1](https://github.com/melasistema/mela-cms/compare/v0.5.0...v0.5.1) (2026-10-14)


### Bug Fixes

* skip the sizes of an image that does not fit in the memory limit ([b5147ae](https://github.com/melasistema/mela-cms/commit/b5147ae777dd55eef9c12f6a32fe3fb16e69e2e1))

## [0.5.0](https://github.com/melasistema/mela-cms/compare/v0.4.5...v0.5.0) (2026-10-14)


### Features

* let a site configure the admin panel ([7db2750](https://github.com/melasistema/mela-cms/commit/7db27509a24a7cd09d3331655f61a6cdb371921a))

## [0.4.5](https://github.com/melasistema/mela-cms/compare/v0.4.4...v0.4.5) (2026-10-14)


### Bug Fixes

* date the migration of the entity guide after the core tables ([#14](https://github.com/melasistema/mela-cms/issues/14)) ([52bbaaf](https://github.com/melasistema/mela-cms/commit/52bbaaf72d0830d9e99eae0b03df766363e4ad10))

## [0.4.4](https://github.com/melasistema/mela-cms/compare/v0.4.3...v0.4.4) (2026-10-14)


### Bug Fixes

* find a page in the admin search whatever the case of its letters ([#12](https://github.com/melasistema/mela-cms/issues/12)) ([8eecdd0](https://github.com/melasistema/mela-cms/commit/8eecdd09bfc98c65e86ff64935621ba2793b2ac2))

## [0.4.3](https://github.com/melasistema/mela-cms/compare/v0.4.2...v0.4.3) (2026-10-13)


### Bug Fixes

* keep the address of a page when the default language changes ([b8c332c](https://github.com/melasistema/mela-cms/commit/b8c332c5bcf3c3f5923c92c34133d1ba1443dbfb))

## [0.4.2](https://github.com/melasistema/mela-cms/compare/v0.4.1...v0.4.2) (2026-10-13)


### Bug Fixes

* refuse a page address that a route of the site already answers ([4eda948](https://github.com/melasistema/mela-cms/commit/4eda948a118a5c30c8ce6c63bfb07b0c5456eb2c))

## [0.4.1](https://github.com/melasistema/mela-cms/compare/v0.4.0...v0.4.1) (2026-10-13)


### Bug Fixes

* create the core roles after every migration run ([fd1a4d0](https://github.com/melasistema/mela-cms/commit/fd1a4d0a1e0ce3ad3fc1ad4bd8724ee4f6ebc14d))

## [0.4.0](https://github.com/melasistema/mela-cms/compare/v0.3.1...v0.4.0) (2026-10-12)


### Features

* add mela:update --check and run it in the site's CI ([174d3f0](https://github.com/melasistema/mela-cms/commit/174d3f04f4bdf94017465c955c64181be1b8730f))

## [0.3.1](https://github.com/melasistema/mela-cms/compare/v0.3.0...v0.3.1) (2026-10-12)


### Bug Fixes

* stop the deploy with a message naming PHP_BIN when the server's PHP is below 8.4 ([6580805](https://github.com/melasistema/mela-cms/commit/65808050767598dc4a38286f7b4f6ecc3e769e60))

## [0.3.0](https://github.com/melasistema/mela-cms/compare/v0.2.2...v0.3.0) (2026-10-12)


### Features

* add mela:super-admin to create the first account on a deployed site ([d29d8de](https://github.com/melasistema/mela-cms/commit/d29d8def93bb9d4c5e22e2790901e765efe10826))

## [0.2.2](https://github.com/melasistema/mela-cms/compare/v0.2.1...v0.2.2) (2026-10-12)


### Bug Fixes

* remove the caches of the previous release before the deploy runs artisan ([a48148a](https://github.com/melasistema/mela-cms/commit/a48148a3b5c383c3b3b90f4339b83523a5f5c307))

## [0.2.1](https://github.com/melasistema/mela-cms/compare/v0.2.0...v0.2.1) (2026-10-12)


### Bug Fixes

* give missing config keys their defaults when the config is cached ([0d44552](https://github.com/melasistema/mela-cms/commit/0d445523e9f344857f6adb39e3702e9d6517a66d))

## [0.2.0](https://github.com/melasistema/mela-cms/compare/v0.1.0...v0.2.0) (2026-10-11)


### Features

* add languages, localized URLs and translatable content ([ef7a1c6](https://github.com/melasistema/mela-cms/commit/ef7a1c6e7493b4ce2709baf1ea5d1d0da868106e))
* add pages and the building blocks for site content ([ef7a1c6](https://github.com/melasistema/mela-cms/commit/ef7a1c6e7493b4ce2709baf1ea5d1d0da868106e))
* add SEO tags and the sitemap ([ef7a1c6](https://github.com/melasistema/mela-cms/commit/ef7a1c6e7493b4ce2709baf1ea5d1d0da868106e))
* add the entity guide for coding agents ([ef7a1c6](https://github.com/melasistema/mela-cms/commit/ef7a1c6e7493b4ce2709baf1ea5d1d0da868106e))
* add the media library ([ef7a1c6](https://github.com/melasistema/mela-cms/commit/ef7a1c6e7493b4ce2709baf1ea5d1d0da868106e))
* add the site settings page ([ef7a1c6](https://github.com/melasistema/mela-cms/commit/ef7a1c6e7493b4ce2709baf1ea5d1d0da868106e))
* add the users screen and the profile page ([#2](https://github.com/melasistema/mela-cms/issues/2)) ([ef7a1c6](https://github.com/melasistema/mela-cms/commit/ef7a1c6e7493b4ce2709baf1ea5d1d0da868106e))

## 0.1.0 (2026-10-10)


### Features

* add mela:install and mela:update commands ([f51972a](https://github.com/melasistema/mela-cms/commit/f51972ada8adb9167b260856e0239baf1ae26cc6))


### Bug Fixes

* target the pushed branch in release-please workflows ([c08a97d](https://github.com/melasistema/mela-cms/commit/c08a97ddefeafb04282b1ad59d972bbd3db4e127))
