<?php

namespace Melasistema\MelaCms\Filament\Forms;

use Filament\Forms\Components\RichEditor;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Melasistema\MelaCms\Models\Media;

/**
 * A rich text field whose images go into the media library: an image added
 * to the text is uploaded with the limits of the library, gets its sizes and
 * shows up on the Media screen.
 *
 *     Translations::make([RichText::make('body')])
 *
 * Print the saved text in a view with @richtext($page->body).
 */
class RichText extends RichEditor
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->fileAttachmentsDisk(fn (): string => config('mela-cms.media.disk'))
            ->fileAttachmentsDirectory(fn (): string => config('mela-cms.media.directory'))
            ->fileAttachmentsVisibility('public')
            ->fileAttachmentsAcceptedFileTypes(fn (): array => array_values(array_filter(
                config('mela-cms.media.accepted_types'),
                fn (string $type): bool => str_starts_with($type, 'image/'),
            )))
            ->fileAttachmentsMaxSize(fn (): int => config('mela-cms.media.max_size'))
            ->saveUploadedFileAttachmentUsing(function (RichText $component, TemporaryUploadedFile $file): string {
                $path = $file->store($component->getFileAttachmentsDirectory(), [
                    'disk' => $component->getFileAttachmentsDiskName(),
                ]);

                Media::fromFile($path, $file->getClientOriginalName(), $component->getFileAttachmentsDiskName());

                return $path;
            });
    }
}
