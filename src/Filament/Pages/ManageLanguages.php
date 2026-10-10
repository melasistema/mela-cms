<?php

namespace Melasistema\MelaCms\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Languages;

/**
 * Where an admin chooses the languages of the site, which of them is the
 * default, and the language of the admin panel.
 *
 * A site that needs something else extends this class and names its class
 * under `pages` in config/mela-cms.php.
 *
 * @property-read Schema $form
 */
class ManageLanguages extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?string $slug = 'languages';

    protected static ?string $title = 'Languages';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasAnyRole([Role::SuperAdmin->value, Role::Admin->value]);
    }

    public function mount(): void
    {
        $languages = app(Languages::class);

        $this->form->fill([
            'locales' => $languages->all(),
            'default' => $languages->default(),
            'admin' => $languages->admin(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(static::formComponents())
            ->statePath('data');
    }

    /**
     * @return array<Component>
     */
    public static function formComponents(): array
    {
        return [
            Select::make('locales')
                ->label('Site languages')
                ->helperText('The languages the site is published in.')
                ->options(fn () => app(Languages::class)->available())
                ->multiple()
                ->required()
                ->live(),
            Select::make('default')
                ->label('Default language')
                ->helperText('Served at the root of the site. Every other language gets its own address.')
                ->options(fn (Get $get) => Arr::only(app(Languages::class)->available(), $get('locales') ?? []))
                ->required()
                ->native(false),
            Select::make('admin')
                ->label('Admin panel language')
                ->helperText('The language of this panel. It does not have to be a site language.')
                ->options(fn () => app(Languages::class)->availableForAdmin())
                ->required()
                ->searchable()
                ->native(false),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        app(Languages::class)->update($data['locales'], $data['default'], $data['admin']);

        Notification::make()
            ->success()
            ->title(__('filament-panels::resources/pages/edit-record.notifications.saved.title'))
            ->send();

        // Reloaded so the panel shows in the admin language just chosen.
        $this->redirect(static::getUrl());
    }
}
