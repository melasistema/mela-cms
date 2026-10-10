<?php

use Melasistema\MelaCms\Filament\Pages\EditProfile;
use Melasistema\MelaCms\Filament\Resources\Users\UserResource;

return [

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | The languages the site is published in. The first entry is the default.
    |
    */

    'locales' => ['en'],

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Path
    |--------------------------------------------------------------------------
    */

    'admin_path' => 'admin',

    /*
    |--------------------------------------------------------------------------
    | Core Resources
    |--------------------------------------------------------------------------
    |
    | The admin screens the core adds to the panel. Set an entry to null to
    | remove the screen, or to your own class that extends the core one to
    | change it.
    |
    */

    'resources' => [
        'users' => UserResource::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Profile Page
    |--------------------------------------------------------------------------
    |
    | The page where a logged-in user changes their own name, email and
    | password. Set it to null to remove the page, or to your own class that
    | extends this one to change it.
    |
    */

    'profile_page' => EditProfile::class,

];
