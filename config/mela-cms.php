<?php

use Melasistema\MelaCms\Filament\Pages\EditProfile;
use Melasistema\MelaCms\Filament\Pages\ManageLanguages;
use Melasistema\MelaCms\Filament\Pages\ManageSettings;
use Melasistema\MelaCms\Filament\Resources\Media\MediaResource;
use Melasistema\MelaCms\Filament\Resources\Pages\PageResource;
use Melasistema\MelaCms\Filament\Resources\Users\UserResource;
use Melasistema\MelaCms\Models\Page;

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
    | Language URLs
    |--------------------------------------------------------------------------
    |
    | Where the other languages live. The default language is always at the
    | root of the site.
    |
    | 'path'       example.com/it
    | 'subdomain'  it.example.com (each subdomain must point to this app)
    |
    */

    'locale_urls' => 'path',

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
        'pages' => PageResource::class,
        'media' => MediaResource::class,
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
        'settings' => ManageSettings::class,
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

    /*
    |--------------------------------------------------------------------------
    | Public Pages
    |--------------------------------------------------------------------------
    |
    | With true, the core answers every address that no route of the site
    | matches with the published page at that address, shown by the view
    | resources/views/pages/{template}.blade.php. The routes of the site
    | always come first. Set it to false to serve the pages yourself.
    |
    */

    'public_pages' => true,

    /*
    |--------------------------------------------------------------------------
    | Sitemap
    |--------------------------------------------------------------------------
    |
    | What /sitemap.xml lists, in every site language. An entry is a model
    | class with a `url(?string $locale)` method, or the path of a page the
    | site serves with its own route ('blog'). Of a model with a publication
    | date only what is published is listed. An empty array removes the
    | sitemap.
    |
    */

    'sitemap' => [
        Page::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Library
    |--------------------------------------------------------------------------
    |
    | Where uploaded files are stored and what may be uploaded. The disk is
    | one of config/filesystems.php and must be public. `max_size` is in
    | kilobytes.
    |
    | Each conversion is a size made of every image at upload, as a WebP file.
    | With a width and a height the image is cropped to fill them; with one of
    | the two it keeps its proportions. An image is never enlarged. After a
    | change here, run `php artisan mela:media-regenerate`.
    |
    */

    'media' => [
        'disk' => 'public',
        'directory' => 'media',
        'max_size' => 10240,
        'accepted_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'application/pdf',
        ],
        'conversions' => [
            'thumb' => ['width' => 400, 'height' => 400],
            'medium' => ['width' => 800],
            'large' => ['width' => 1600],
        ],
        'quality' => 80,
    ],

];
