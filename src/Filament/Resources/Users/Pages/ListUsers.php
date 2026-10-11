<?php

namespace Melasistema\MelaCms\Filament\Resources\Users\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    use ResolvesUserResource;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
