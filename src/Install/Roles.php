<?php

namespace Melasistema\MelaCms\Install;

use Illuminate\Database\Eloquent\Model;
use Melasistema\MelaCms\Enums\Role;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

final readonly class Roles
{
    public function __construct(private PermissionRegistrar $registrar) {}

    /**
     * Creates the core roles that do not exist yet.
     */
    public function sync(): void
    {
        foreach (Role::values() as $role) {
            RoleModel::findOrCreate($role);
        }

        $this->registrar->forgetCachedPermissions();
    }

    public function hasSuperAdmin(): bool
    {
        return RoleModel::findByName(Role::SuperAdmin->value)->users()->exists();
    }

    /**
     * Assigned through the role so it works in the same process that has just
     * added the HasCmsAccess trait to the user model's source.
     */
    public function makeSuperAdmin(Model $user): void
    {
        RoleModel::findByName(Role::SuperAdmin->value)
            ->users()
            ->syncWithoutDetaching([$user->getKey()]);

        $this->registrar->forgetCachedPermissions();
    }
}
