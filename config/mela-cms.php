<?php

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

];
