<?php

namespace Melasistema\MelaCms\Concerns;

use Filament\Panel;
use Melasistema\MelaCms\Enums\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * Gives a user model MelaCMS roles and access to the admin panel.
 *
 * The model must also implement Filament\Models\Contracts\FilamentUser;
 * `php artisan mela:install` adds both to the app's user model.
 */
trait HasCmsAccess
{
    use HasRoles;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(Role::values());
    }
}
