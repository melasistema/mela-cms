<?php

namespace Melasistema\MelaCms\Policies;

use Illuminate\Database\Eloquent\Model;
use Melasistema\MelaCms\Enums\Role;

/**
 * Who may manage the users of the admin panel.
 *
 * A super-admin never reaches these methods: the Gate::before callback in the
 * service provider lets them through first.
 */
class UserPolicy
{
    public function viewAny(Model $user): bool
    {
        return $user->hasRole(Role::Admin->value);
    }

    public function create(Model $user): bool
    {
        return $user->hasRole(Role::Admin->value);
    }

    public function update(Model $user, Model $target): bool
    {
        return $user->hasRole(Role::Admin->value)
            && ! $target->hasRole(Role::SuperAdmin->value);
    }

    public function delete(Model $user, Model $target): bool
    {
        return $this->update($user, $target) && $user->isNot($target);
    }
}
