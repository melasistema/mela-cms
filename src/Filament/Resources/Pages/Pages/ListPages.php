<?php

namespace Melasistema\MelaCms\Filament\Resources\Pages\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPages extends ListRecords
{
    use ResolvesPageResource;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
