<?php

namespace Melasistema\MelaCms\Filament\Resources\Users\Pages;

use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use ResolvesUserResource;

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('index');
    }
}
