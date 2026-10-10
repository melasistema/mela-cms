<?php

namespace Melasistema\MelaCms\Filament\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Melasistema\MelaCms\Enums\Role;

/**
 * A panel page with one form and a save button, for admins and super-admins.
 * The fields are in formComponents(), so a class that extends a core page can
 * change them.
 *
 * @property-read Schema $form
 */
abstract class SettingsPage extends Page
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @return array<Component>
     */
    abstract public static function formComponents(): array;

    abstract public function save(): void;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasAnyRole([Role::SuperAdmin->value, Role::Admin->value]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(static::formComponents())
            ->statePath('data');
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

    protected function notifySaved(): void
    {
        Notification::make()
            ->success()
            ->title(__('filament-panels::resources/pages/edit-record.notifications.saved.title'))
            ->send();
    }
}
