<?php

namespace Melasistema\MelaCms\Filament\Resources\Media\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Melasistema\MelaCms\Filament\Forms\MediaUpload;
use Melasistema\MelaCms\Models\Media;

/**
 * The library: every file in a grid, edited in a dialog. Several files can be
 * uploaded at once.
 */
class ManageMedia extends ManageRecords
{
    use ResolvesMediaResource;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label('Upload')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->visible(fn (): bool => static::getResource()::canCreate())
                ->schema([
                    MediaUpload::make('files')
                        ->hiddenLabel()
                        ->multiple()
                        ->storeFileNamesIn('file_names')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    foreach ($data['files'] as $path) {
                        Media::fromFile($path, $data['file_names'][$path] ?? null);
                    }

                    Notification::make()
                        ->success()
                        ->title(trans_choice('{1} 1 file uploaded|[2,*] :count files uploaded', count($data['files'])))
                        ->send();
                }),
        ];
    }
}
