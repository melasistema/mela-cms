<?php

use Melasistema\MelaCms\Filament\Pages\EditProfile;
use Melasistema\MelaCms\Filament\Pages\ManageLanguages;
use Melasistema\MelaCms\Filament\Resources\Users\UserResource;

return [

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | The languages the site is published in. The first entry is the default
    | language, served at the root of the site.
    |
    | These are the starting values. Once an admin saves the Languages page in
    | the panel, what was saved there is used instead.
    |
    */

    'locales' => ['en'],

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Language
    |--------------------------------------------------------------------------
    |
    | The language of the admin panel, independent of the site languages.
    | null uses the app locale. Also a starting value: the Languages page
    | overrides it.
    |
    */

    'admin_locale' => null,

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
    | Core Pages
    |--------------------------------------------------------------------------
    |
    | The admin pages the core adds to the panel. They work like the resources
    | above: null removes a page, your own class replaces it.
    |
    */

    'pages' => [
        'languages' => ManageLanguages::class,
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
