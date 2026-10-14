# Adding an entity to a MelaCMS site

Read this before you create an entity in a MelaCMS site: a service, a post, a project, a team member, a product. It is written for coding agents and for developers. Everything here is written in the site's repo; nothing in `vendor/` changes.

A page is not an entity. Pages already exist in the core (`/admin/pages`). Build an entity only when the content has its own fields or its own list on the site.

## The slice

An entity is complete when all of these exist. Leave one out only for a reason given under [Variants](#variants).

| # | File | What it holds |
| :--- | :--- | :--- |
| 1 | `database/migrations/..._create_services_table.php` | The table |
| 2 | `app/Models/Service.php` | The model, with the core traits |
| 3 | `app/Filament/Resources/Services/ServiceResource.php` and its three pages | The admin screen. The panel finds it by itself |
| 4 | `app/Http/Controllers/ServiceController.php` | The list and the single record on the site |
| 5 | `routes/web.php` | Two routes, written once for every language |
| 6 | `resources/views/services/index.blade.php`, `show.blade.php` | The views |
| 7 | `config/mela-cms.php` | The entity in the `sitemap` list |
| 8 | `tests/Feature/ServiceTest.php` | A test of the site address and of the admin form |

Names follow Laravel: a singular model (`Service`), a plural table, route and view directory (`services`).

## Choose the columns first

Each answer adds columns to the migration and a part of the core to the model and the form. Add only what the entity needs.

| The entity has | Migration | Model | Form |
| :--- | :--- | :--- | :--- |
| Text in each language | `json` column per field | `HasTranslations`, field in `$translatable` | Field inside `Translations::make([...])` |
| Its own address on the site | `json('slug')` | `HasSlug`, `slug` in `$translatable` | `SlugInput::make('slug')` |
| Drafts and a publication date | `timestamp('published_at')->nullable()` | `Publishable` | `DateTimePicker::make('published_at')` |
| Formatted text | `json('body')->nullable()` | `body` in `$translatable` | `RichText::make('body')` |
| One image | `foreignId('cover_id')->nullable()->constrained('mela_media')->nullOnDelete()` | `cover()` relation to `Media` | `MediaPicker::make('cover_id')->images()` |
| Title, description and image written for search engines | `json('seo')->nullable()` | `HasSeo` | `SeoFields::make()` |

Two column names are read by the core without any code: `excerpt` becomes the description for search engines when the editor wrote none, and `cover_id` becomes the image shown when the address is shared.

There is no table for SEO, for translations, for slugs or for images. Do not create one.

## Worked example: services

A `Service` with everything in the table above. Replace `Service`, `services` and the fields with those of your entity. The code blocks below are run by the core's test suite, so they work as written.

### 1. Migration

Create it with `php artisan make:migration create_services_table`, so its date comes after the core migrations: `cover_id` points to `mela_media`, and MySQL refuses a foreign key to a table that does not exist yet.

**`database/migrations/2026_10_14_000000_create_services_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('slug');
            $table->json('excerpt')->nullable();
            $table->json('body')->nullable();
            $table->foreignId('cover_id')->nullable()->constrained('mela_media')->nullOnDelete();
            $table->json('seo')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
```

A translated field is a `json` column, never one column per language.

### 2. Model

**`app/Models/Service.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Melasistema\MelaCms\Concerns\HasSeo;
use Melasistema\MelaCms\Concerns\HasSlug;
use Melasistema\MelaCms\Concerns\Publishable;
use Melasistema\MelaCms\Languages;
use Melasistema\MelaCms\Models\Media;
use Spatie\Translatable\HasTranslations;

class Service extends Model
{
    use HasSeo;
    use HasSlug;
    use HasTranslations;
    use Publishable;

    protected $guarded = [];

    /**
     * @var array<string>
     */
    public array $translatable = ['title', 'slug', 'excerpt', 'body'];

    /**
     * @return BelongsTo<Media, $this>
     */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_id');
    }

    /**
     * The address of the service inside the site in every language:
     * [locale => path]. The only place that knows the `services/` prefix.
     *
     * @return array<string, string>
     */
    public function paths(): array
    {
        return array_map(fn (?string $slug): string => "services/{$slug}", $this->slugs());
    }

    /**
     * The full address, in the language of the request or in the given one.
     */
    public function url(?string $locale = null): string
    {
        $languages = app(Languages::class);
        $locale ??= $languages->current();

        return $languages->url($locale, $this->paths()[$locale]);
    }
}
```

`$service->title` is in the language of the request and falls back to the default language of the site. The casts of `seo` and `published_at` come from the traits.

### 3. Admin screen

The panel discovers every resource in `app/Filament/Resources`. Nothing is registered by hand.

**`app/Filament/Resources/Services/ServiceResource.php`**

```php
<?php

namespace App\Filament\Resources\Services;

use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Melasistema\MelaCms\Enums\PublicationStatus;
use Melasistema\MelaCms\Filament\Forms\MediaPicker;
use Melasistema\MelaCms\Filament\Forms\RichText;
use Melasistema\MelaCms\Filament\Forms\SeoFields;
use Melasistema\MelaCms\Filament\Forms\SlugInput;
use Melasistema\MelaCms\Filament\Forms\Translations;
use Melasistema\MelaCms\Languages;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        Translations::make([
                            TextInput::make('title')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(SlugInput::fill()),
                            SlugInput::make('slug')
                                ->label('Address')
                                ->prefix('/services/')
                                ->required(),
                            Textarea::make('excerpt')
                                ->helperText('One or two sentences, shown in the list of services and in search results.')
                                ->rows(3)
                                ->maxLength(300),
                            RichText::make('body')
                                ->label('Text'),
                        ]),
                        SeoFields::make(),
                    ]),
                Section::make()
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        DateTimePicker::make('published_at')
                            ->label('Published on')
                            ->helperText('Empty: a draft. A date to come: public from that date.')
                            ->seconds(false),
                        MediaPicker::make('cover_id')
                            ->label('Cover image')
                            ->images(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->weight('medium')
                    // A translated column is searched in every site language.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(function (Builder $query) use ($search) {
                        foreach (app(Languages::class)->all() as $locale) {
                            $query->orWhereLike("title->{$locale}", "%{$search}%");
                        }
                    }))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('title->'.app(Languages::class)->default(), $direction)),
                TextColumn::make('status')
                    ->state(fn (Service $record): PublicationStatus => $record->status())
                    ->badge(),
                TextColumn::make('updated_at')
                    ->label('Last change')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }
}
```

**`app/Filament/Resources/Services/Pages/ListServices.php`**

```php
<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServices extends ListRecords
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

**`app/Filament/Resources/Services/Pages/CreateService.php`**

```php
<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;
}
```

**`app/Filament/Resources/Services/Pages/EditService.php`**

```php
<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
```

Rules for the form:

- Write each translated field once, inside `Translations::make([...])`, without a language in its name. `required()` applies to the default language only.
- An image field is a `MediaPicker`, never a `FileUpload`.
- No policy is needed: every role (`super-admin`, `admin`, `editor`) may create, publish and delete.

### 4. Controller

**`app/Http/Controllers/ServiceController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Contracts\View\View;
use Melasistema\MelaCms\Languages;
use Melasistema\MelaCms\Seo;

class ServiceController
{
    public function index(Seo $seo): View
    {
        $seo->title('Services');

        return view('services.index', [
            'services' => Service::published()->with('cover')->latest('published_at')->get(),
        ]);
    }

    public function show(string $slug, Languages $languages, Seo $seo): View
    {
        $service = Service::published()->whereSlug($slug)->firstOrFail();

        // The language switcher and the hreflang tags follow the slug of each language.
        $languages->localize($service->paths());

        // What @seo prints in the <head> of the layout.
        $seo->for($service);

        return view('services.show', ['service' => $service]);
    }
}
```

`whereSlug()` looks for the slug in the language of the request. A language without a slug uses the one of the default language, then of the next site language that has one. Both calls in `show()` are needed on every route that shows one record: without `localize()` the language switcher points to an address that does not exist, and without `for()` the page has the title of the site.

### 5. Routes

**`routes/web.php`**, add:

```php
use App\Http\Controllers\ServiceController;

Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
Route::get('/services/{slug}', [ServiceController::class, 'show'])->name('services.show');
```

Write each route once. The core answers `/it/services/...` with the same route: do not add a `{locale}` parameter, a route group per language, or a call to `app()->setLocale()`.

### 6. Views

The views use the layout of the site. Whatever the layout is, its `<head>` prints `@seo` and `@hreflang` and has no `<title>` or meta tags written by hand. A site without a layout yet starts from this one:

**`resources/views/components/layout.blade.php`**

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @seo
    @hreflang
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <nav>
        @foreach (app(\Melasistema\MelaCms\Languages::class)->alternates() as $locale => $url)
            <a href="{{ $url }}" hreflang="{{ $locale }}">{{ strtoupper($locale) }}</a>
        @endforeach
    </nav>

    <main>
        {{ $slot }}
    </main>
</body>
</html>
```

**`resources/views/services/index.blade.php`**

```blade
<x-layout>
    <h1>Services</h1>

    @foreach ($services as $service)
        <article>
            @if ($service->cover)
                <img
                    src="{{ $service->cover->url('medium') }}"
                    alt="{{ $service->cover->alt }}"
                    loading="lazy"
                >
            @endif

            <h2><a href="{{ $service->url() }}">{{ $service->title }}</a></h2>

            <p>{{ $service->excerpt }}</p>
        </article>
    @endforeach
</x-layout>
```

**`resources/views/services/show.blade.php`**

```blade
<x-layout>
    <article>
        <h1>{{ $service->title }}</h1>

        @if ($service->cover)
            <img
                src="{{ $service->cover->url('large') }}"
                @if ($service->cover->srcset()) srcset="{{ $service->cover->srcset() }}" sizes="100vw" @endif
                width="{{ $service->cover->width }}"
                height="{{ $service->cover->height }}"
                alt="{{ $service->cover->alt }}"
            >
        @endif

        @richtext($service->body)
    </article>
</x-layout>
```

Rules for the views:

- Link to a record with `$service->url()`, which keeps the language of the request.
- Print rich text with `@richtext(...)`, never with `{!! !!}`.
- Print an image with `$media->url('thumb' | 'medium' | 'large')` and its translated `alt`.
- Style with Tailwind classes and add interactions with Alpine, as in the rest of the site.

### 7. Sitemap

**`config/mela-cms.php`**, add to `sitemap`:

```php
'sitemap' => [
    \Melasistema\MelaCms\Models\Page::class,
    \App\Models\Service::class,
    'services',
],
```

A model class lists every published record through its `url()` method, in each language. A plain path lists a page served by a route of the site, here the list of services. A config file published by an older version of the core has no `sitemap` key: add the whole key as above.

### 8. Test

**`tests/Feature/ServiceTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\Services\Pages\CreateService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Melasistema\MelaCms\Languages;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $locale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->locale = app(Languages::class)->default();
    }

    public function test_a_published_service_is_shown_at_its_address(): void
    {
        Service::create([
            'title' => [$this->locale => 'Web design'],
            'slug' => [$this->locale => 'web-design'],
            'excerpt' => [$this->locale => 'Sites that load fast.'],
            'published_at' => now(),
        ]);

        $this->get('/services')->assertOk()->assertSee('Web design');

        $this->get('/services/web-design')
            ->assertOk()
            ->assertSee('Web design')
            ->assertSee('<meta name="description" content="Sites that load fast.">', false);
    }

    public function test_a_draft_is_not_shown(): void
    {
        Service::create([
            'title' => [$this->locale => 'Coming soon'],
            'slug' => [$this->locale => 'coming-soon'],
        ]);

        $this->get('/services')->assertOk()->assertDontSee('Coming soon');
        $this->get('/services/coming-soon')->assertNotFound();
    }

    public function test_an_editor_creates_a_service(): void
    {
        $editor = User::factory()->create()->assignRole('editor');

        Livewire::actingAs($editor)
            ->test(CreateService::class)
            ->fillForm([
                "title.{$this->locale}" => 'Web design',
                "slug.{$this->locale}" => 'web-design',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('web-design', Service::sole()->slug);
    }
}
```

In a test a translated address is requested as `$this->get('/it/servizi/...')`, with the language prefix in the path.

## Variants

**No address of its own** (team members, testimonials, FAQ shown inside a page). Leave out `slug`, `seo`, `HasSlug`, `HasSeo`, `SlugInput`, `SeoFields`, the `show` route, `paths()`, `url()` and the sitemap entry. Keep `published_at` only if editors need drafts. Load the records in the controller or the view of the page that shows them.

**One language only.** If the site will never have a second language, the columns can be `string` and `text` instead of `json`, without `HasTranslations` and `Translations::make()`. `HasSlug` and `SlugInput` work the same. Prefer the translated form when in doubt: adding a language later then needs no migration.

**A gallery.** A `json('gallery')->nullable()` column cast to `array`, `MediaPicker::make('gallery')->images()->multiple()` in the form, and `Media::inOrder($service->gallery)` to load the files in the chosen order.

**A fixed order instead of the newest first.** An `unsignedInteger('sort')->default(0)` column, `->reorderable('sort')` and `->defaultSort('sort')` on the table, `->orderBy('sort')` in the controller.

**An editor previews a draft.** Register the core's policy for the model in `AppServiceProvider::boot()` with `Gate::policy(Service::class, \Melasistema\MelaCms\Policies\ContentPolicy::class)`, then in `show()` load the record without `published()` and add `abort_unless($service->isPublished() || $request->user()?->can('update', $service), 404);`. `Seo::for()` keeps a draft out of search engines.

**Only some roles may edit.** Write a policy for the model and register it with `Gate::policy()`. Check roles with `$user->hasRole('admin')`.

**An entity with a parent** (a post in a category). Each of the two is an entity with its own slice. The route of the child has one parameter per level (`/blog/{category}/{slug}`), and its `paths()` builds the whole path in each language from both slugs.

## Do not

- Do not edit anything in `vendor/melasistema/mela-cms`.
- Do not create a table or a package for translations, media, SEO or settings. The core has one of each.
- Do not write `<title>`, description, canonical, robots or Open Graph tags in a view.
- Do not add a locale to a route, a field name or a column name.
- Do not read languages from `config('mela-cms.locales')`; use `app(\Melasistema\MelaCms\Languages::class)`.
- Do not hard-code the site name, contact details or social links; read them from `app(\Melasistema\MelaCms\Settings::class)`.
- Do not register the resource in a panel provider or in `config/mela-cms.php`: `resources` there is for the core's own screens.

## Before finishing

1. `php artisan migrate`
2. `vendor/bin/pint`
3. `php artisan test`
4. Open `/admin`, create a record in every site language, publish it, and open its address in each language.
5. Commit as `feat: add services`, so the next release of the site lists it.
