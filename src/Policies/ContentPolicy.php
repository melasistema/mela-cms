<?php

namespace Melasistema\MelaCms\Policies;

use Illuminate\Database\Eloquent\Model;
use Melasistema\MelaCms\Enums\Role;

/**
 * Who may work on the content of the site: everyone with a core role. An
 * editor writes, publishes and deletes content; what an admin has more is the
 * users, the languages and the settings.
 *
 * Registered for the core's pages. A site that wants the same rule spelled
 * out for one of its models registers it with Gate::policy().
 */
class ContentPolicy
{
    public function viewAny(Model $user): bool
    {
        return $user->hasAnyRole(array_column(Role::cases(), 'value'));
    }

    public function view(Model $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(Model $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(Model $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(Model $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function deleteAny(Model $user): bool
    {
        return $this->viewAny($user);
    }
}
