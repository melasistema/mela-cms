<?php

use Melasistema\MelaCms\Install\Changelog;

it('lists the entries of the versions after the installed one, newest first', function () {
    $path = $this->projectFile('CHANGELOG.md');
    file_put_contents($path, <<<'MD'
        # Changelog

        ## [0.6.0](https://github.com/melasistema/mela-cms/compare/v0.5.4...v0.6.0) (2026-11-02)


        ### ⚠ BREAKING CHANGES

        * rename the media disk setting

        ### Features

        * **media:** add a focal point to images ([#30](https://github.com/melasistema/mela-cms/issues/30)) ([abc1234](https://github.com/melasistema/mela-cms/commit/abc1234))

        ## [0.5.4](https://github.com/melasistema/mela-cms/compare/v0.5.3...v0.5.4) (2026-10-20)


        ### Bug Fixes

        * keep the order of the gallery ([def5678](https://github.com/melasistema/mela-cms/commit/def5678))

        ## [0.5.3](https://github.com/melasistema/mela-cms/compare/v0.5.2...v0.5.3) (2026-10-15)


        ### Bug Fixes

        * already installed ([0123abc](https://github.com/melasistema/mela-cms/commit/0123abc))
        MD);

    expect((new Changelog($path))->between('0.5.3', '0.6.0'))->toBe([
        'Breaking: rename the media disk setting',
        '**media:** add a focal point to images',
        'keep the order of the gallery',
    ])->and((new Changelog($path))->between('0.5.3', '0.5.4'))->toBe([
        'keep the order of the gallery',
    ]);
});

it('reads the changelog shipped with the core', function () {
    expect((new Changelog)->between('0.5.1', '0.5.2'))
        ->toBe(['qualify the publication date column in the published scope']);
});

it('lists nothing without a changelog', function () {
    expect((new Changelog($this->projectFile('missing.md')))->between('0.1.0', '9.0.0'))->toBe([]);
});
