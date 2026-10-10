<?php

use Filament\Actions\Testing\TestAction;
use Filament\Panel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Filament\AdminPanelProvider;
use Melasistema\MelaCms\Filament\Forms\MediaPicker;
use Melasistema\MelaCms\Filament\Pages\ManageSettings;
use Melasistema\MelaCms\Filament\Resources\Media\MediaResource;
use Melasistema\MelaCms\Filament\Resources\Media\Pages\ManageMedia;
use Melasistema\MelaCms\Languages;
use Melasistema\MelaCms\Media\Conversions;
use Melasistema\MelaCms\Models\Media;
use Melasistema\MelaCms\Settings;
use Melasistema\MelaCms\Tests\Fixtures\MediaForm;
use Melasistema\MelaCms\Tests\Fixtures\User;

beforeEach(function () {
    Storage::fake('public');

    $this->install(['--locales' => 'en,it']);
});

function mediaUser(?Role $role): User
{
    $user = User::create([
        'name' => 'Test',
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
    ]);

    if ($role) {
        $user->assignRole($role->value);
    }

    return $user;
}

function image(string $name = 'photo.jpg', int $width = 1200, int $height = 800): Media
{
    return Media::fromFile(UploadedFile::fake()->image($name, $width, $height)->store('media', 'public'), $name);
}

function pdf(string $name = 'price-list.pdf'): Media
{
    return Media::fromFile(UploadedFile::fake()->create($name, 20, 'application/pdf')->store('media', 'public'), $name);
}

it('adds an image to the library with its dimensions', function () {
    $media = image('Summer photo.jpg');

    expect($media->exists)->toBeTrue()
        ->and($media->name)->toBe('Summer photo')
        ->and($media->disk)->toBe('public')
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and($media->size)->toBeGreaterThan(0)
        ->and($media->width)->toBe(1200)
        ->and($media->height)->toBe(800)
        ->and($media->isImage())->toBeTrue();
});

it('makes the sizes of an image as WebP files', function () {
    $media = image();

    expect(array_keys($media->conversions))->toBe(['thumb', 'medium', 'large']);

    foreach ($media->conversions as $conversion) {
        Storage::disk('public')->assertExists($conversion['path']);

        expect($conversion['path'])->toEndWith('.webp')
            ->and(Storage::disk('public')->mimeType($conversion['path']))->toBe('image/webp');
    }

    // Cropped to the square, scaled to the width, and never enlarged.
    expect($media->conversions['thumb'])->toMatchArray(['width' => 400, 'height' => 400])
        ->and($media->conversions['medium'])->toMatchArray(['width' => 800, 'height' => 533])
        ->and($media->conversions['large'])->toMatchArray(['width' => 1200, 'height' => 800]);
});

it('gives the address of the file and of each size', function () {
    $media = image();

    expect($media->url())->toBe(Storage::disk('public')->url($media->path))
        ->and($media->url('thumb'))->toBe(Storage::disk('public')->url($media->conversions['thumb']['path']))
        ->and($media->url('poster'))->toBe($media->url())
        ->and($media->srcset())->toBe(
            $media->url('medium').' 800w, '.$media->url('large').' 1200w',
        );
});

it('keeps a file that is not an image as it is', function () {
    $media = pdf();

    expect($media->isImage())->toBeFalse()
        ->and($media->name)->toBe('price-list')
        ->and($media->width)->toBeNull()
        ->and($media->conversions)->toBeNull()
        ->and($media->url('thumb'))->toBe($media->url())
        ->and($media->srcset())->toBeNull();
});

it('adds a file that cannot be read as an image without its sizes', function () {
    Storage::disk('public')->put('media/broken.jpg', 'not an image');
    config(['mela-cms.media.conversions' => ['thumb' => ['width' => 100]]]);

    $media = new Media(['disk' => 'public', 'path' => 'media/broken.jpg', 'name' => 'broken', 'mime_type' => 'image/jpeg']);
    app(Conversions::class)->generate($media);

    expect($media->conversions)->toBeNull()
        ->and($media->width)->toBeNull();
});

it('deletes the file and its sizes with the record', function () {
    $media = image();
    $paths = [$media->path, ...array_column($media->conversions, 'path')];

    $media->delete();

    Storage::disk('public')->assertMissing($paths);
});

it('returns the files of a gallery in the order of its ids', function () {
    [$first, $second, $third] = [pdf('a.pdf'), pdf('b.pdf'), pdf('c.pdf')];
    $second->delete();

    expect(Media::inOrder([$third->id, $second->id, $first->id])->modelKeys())->toBe([$third->id, $first->id])
        ->and(Media::inOrder(null))->toBeEmpty();
});

it('saves the alternative text per language', function () {
    $media = image();
    $media->update(['alt' => ['en' => 'A beach', 'it' => 'Una spiaggia']]);

    app(Languages::class)->use('it');

    expect($media->fresh()->alt)->toBe('Una spiaggia');
});

it('makes the sizes again when the config changes', function () {
    $media = image();
    $old = $media->conversions['large']['path'];

    config(['mela-cms.media.conversions' => ['small' => ['width' => 300]]]);

    $this->artisan('mela:media-regenerate')->assertSuccessful();

    $media->refresh();

    expect(array_keys($media->conversions))->toBe(['small'])
        ->and($media->conversions['small']['width'])->toBe(300);

    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($media->path);
});

it('shows the library to every role', function (?Role $role, int $status) {
    $this->actingAs(mediaUser($role))->get('/admin/media')->assertStatus($status);
})->with([
    [Role::SuperAdmin, 200],
    [Role::Admin, 200],
    [Role::Editor, 200],
]);

it('lists the files of the library', function () {
    $this->actingAs(mediaUser(Role::Editor));

    $photo = image('Beach.jpg');
    $document = pdf('Menu.pdf');

    Livewire::test(ManageMedia::class)
        ->assertCanSeeTableRecords([$photo, $document])
        ->assertSee('Beach')
        ->assertSee($photo->url('thumb'))
        ->filterTable('type', 'documents')
        ->assertCanSeeTableRecords([$document])
        ->assertCanNotSeeTableRecords([$photo]);
});

it('uploads several files at once', function () {
    $this->actingAs(mediaUser(Role::Editor));

    Livewire::test(ManageMedia::class)
        ->callAction('upload', [
            'files' => [
                UploadedFile::fake()->image('Beach.jpg', 600, 400),
                UploadedFile::fake()->create('Menu.pdf', 20, 'application/pdf'),
            ],
        ])
        ->assertHasNoFormErrors();

    expect(Media::count())->toBe(2);

    $photo = Media::firstWhere('name', 'Beach');

    expect($photo->path)->toStartWith('media/')
        ->and($photo->width)->toBe(600)
        ->and(Media::firstWhere('name', 'Menu')->mime_type)->toBe('application/pdf');

    Storage::disk('public')->assertExists($photo->path);
    Storage::disk('public')->assertExists($photo->conversions['thumb']['path']);
});

it('refuses a file of a type that is not accepted', function () {
    $this->actingAs(mediaUser(Role::Editor));

    Livewire::test(ManageMedia::class)
        ->callAction('upload', [
            'files' => [UploadedFile::fake()->create('script.html', 1, 'text/html')],
        ])
        ->assertHasFormErrors(['files']);

    expect(Media::count())->toBe(0);
});

it('edits the name and the alternative text of a file', function () {
    $this->actingAs(mediaUser(Role::Editor));

    $media = image();

    Livewire::test(ManageMedia::class)
        ->callAction(TestAction::make('edit')->table($media), [
            'name' => 'Beach at sunset',
            'alt' => ['en' => 'A beach', 'it' => 'Una spiaggia'],
        ])
        ->assertHasNoFormErrors();

    expect($media->refresh()->name)->toBe('Beach at sunset')
        ->and($media->getTranslations('alt'))->toBe(['en' => 'A beach', 'it' => 'Una spiaggia']);
});

it('deletes a file from the library', function () {
    $this->actingAs(mediaUser(Role::Editor));

    $media = image();

    Livewire::test(ManageMedia::class)->callAction(TestAction::make('delete')->table($media));

    expect(Media::count())->toBe(0);

    Storage::disk('public')->assertMissing($media->path);
});

it('removes the library when the site switches it off', function () {
    config(['mela-cms.resources.media' => null]);

    $panel = (new AdminPanelProvider(app()))->panel(Panel::make());

    expect($panel->getResources())->not->toContain(MediaResource::class);
});

it('offers the latest files in the picker, images only when asked', function () {
    $photo = image('Beach.jpg');
    $document = pdf('Menu.pdf');

    Livewire::test(MediaForm::class)
        ->assertFormFieldExists('cover_id', function (MediaPicker $field) use ($photo) {
            expect(array_keys($field->getOptions()))->toBe([$photo->id])
                ->and($field->getOptions()[$photo->id])->toContain('Beach', e($photo->url('thumb')))
                ->and($field->getSearchResults('men'))->toBe([]);

            return true;
        })
        ->assertFormFieldExists('gallery', function (MediaPicker $field) use ($photo, $document) {
            expect(array_keys($field->getOptions()))->toBe([$document->id, $photo->id])
                ->and(array_keys($field->getSearchResults('men')))->toBe([$document->id]);

            return true;
        });
});

it('saves the picked file as an id and a gallery as a list of ids', function () {
    $photo = image('Beach.jpg');
    $document = pdf('Menu.pdf');

    Livewire::test(MediaForm::class)
        ->fillForm(['cover_id' => $photo->id, 'gallery' => [$document->id, $photo->id]])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSet('saved.cover_id', $photo->id)
        ->assertSet('saved.gallery', [$document->id, $photo->id]);
});

it('does not accept a document where an image is asked', function () {
    $document = pdf('Menu.pdf');

    Livewire::test(MediaForm::class)
        ->fillForm(['cover_id' => $document->id])
        ->call('save')
        ->assertHasFormErrors(['cover_id']);
});

it('uploads a new file from the picker and picks it', function () {
    $livewire = Livewire::test(MediaForm::class)
        ->callAction(TestAction::make('createOption')->schemaComponent('cover_id'), [
            'file' => UploadedFile::fake()->image('Beach.jpg', 600, 400),
        ])
        ->assertHasNoFormErrors();

    $media = Media::sole();

    expect($media->name)->toBe('Beach');

    $livewire->assertSet('data.cover_id', $media->id);
});

it('saves the share image of the site', function () {
    $this->actingAs(mediaUser(Role::Admin));

    $photo = image();

    Livewire::test(ManageSettings::class)
        ->fillForm(['share_image' => $photo->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Media::find(app(Settings::class)->get('site.share_image'))->is($photo))->toBeTrue();
});

it('links the public storage on install', function () {
    expect(is_link($this->projectFile('public/storage')))->toBeTrue()
        ->and(realpath($this->projectFile('public/storage')))->toBe(realpath($this->projectFile('storage/app/public')));

    // Running again leaves the link alone.
    $this->install();

    expect(is_link($this->projectFile('public/storage')))->toBeTrue();
});
