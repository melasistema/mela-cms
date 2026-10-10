<?php

namespace Melasistema\MelaCms\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Melasistema\MelaCms\Concerns\HasCmsAccess;

/**
 * Stands in for a client site's App\Models\User after `mela:install`.
 */
class User extends Authenticatable implements FilamentUser
{
    use HasCmsAccess;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];
}
