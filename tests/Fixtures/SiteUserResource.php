<?php

namespace Melasistema\MelaCms\Tests\Fixtures;

use Filament\Forms\Components\TextInput;
use Melasistema\MelaCms\Filament\Resources\Users\UserResource;

/**
 * Stands in for a client site's own users resource with an extra field.
 */
class SiteUserResource extends UserResource
{
    public static function formComponents(): array
    {
        return [
            ...parent::formComponents(),
            TextInput::make('phone'),
        ];
    }
}
