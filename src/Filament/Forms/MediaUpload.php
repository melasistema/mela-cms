<?php

namespace Melasistema\MelaCms\Filament\Forms;

use Filament\Forms\Components\FileUpload;

/**
 * The upload field of the media library: disk, directory, accepted types and
 * maximum size come from `media` in config/mela-cms.php.
 *
 * It only puts a file on the disk. In a resource use MediaPicker, which also
 * adds the file to the library.
 */
class MediaUpload extends FileUpload
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->disk(fn (): string => config('mela-cms.media.disk'))
            ->directory(fn (): string => config('mela-cms.media.directory'))
            ->visibility('public')
            ->acceptedFileTypes(fn (): array => config('mela-cms.media.accepted_types'))
            ->maxSize(fn (): int => config('mela-cms.media.max_size'));
    }

    /**
     * Accept only the image types among the accepted ones.
     */
    public function imagesOnly(bool $condition = true): static
    {
        return $this->acceptedFileTypes(fn (): array => array_values(array_filter(
            config('mela-cms.media.accepted_types'),
            fn (string $type): bool => ! $condition || str_starts_with($type, 'image/'),
        )));
    }
}
