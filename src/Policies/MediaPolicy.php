<?php

namespace Melasistema\MelaCms\Policies;

use Illuminate\Database\Eloquent\Model;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Models\Media;

/**
 * Who may use the media library: everyone with a core role, editors included,
 * since writing content means uploading files.
 */
class MediaPolicy
{
    public function viewAny(Model $user): bool
    {
        return $user->hasAnyRole(array_column(Role::cases(), 'value'));
    }

    public function create(Model $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(Model $user, Media $media): bool
    {
        return $this->viewAny($user);
    }

    public function delete(Model $user, Media $media): bool
    {
        return $this->viewAny($user);
    }

    public function deleteAny(Model $user): bool
    {
        return $this->viewAny($user);
    }
}
