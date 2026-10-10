<?php

namespace Melasistema\MelaCms\Filament\Resources\Pages\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    use ResolvesPageResource;

    protected function getHeaderActions(): array
    {
        return [
            static::getResource()::viewOnSiteAction(),
            DeleteAction::make(),
        ];
    }
}
