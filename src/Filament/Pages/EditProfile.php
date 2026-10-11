<?php

namespace Melasistema\MelaCms\Filament\Pages;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;

/**
 * Where a logged-in user changes their own name, email and password.
 *
 * A site that needs more fields extends this class, overrides form(), and
 * sets its class as `profile_page` in config/mela-cms.php.
 */
class EditProfile extends BaseEditProfile
{
    /**
     * The stored hash never reaches the browser, even if the user model does
     * not hide it.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        unset($data['password']);

        return parent::mutateFormDataBeforeFill($data);
    }
}
