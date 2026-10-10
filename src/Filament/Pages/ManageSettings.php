<?php

namespace Melasistema\MelaCms\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Melasistema\MelaCms\Filament\Forms\MediaPicker;
use Melasistema\MelaCms\Filament\Forms\Translations;
use Melasistema\MelaCms\Settings;

/**
 * Where an admin fills in what every site has: its name, description and
 * share image, the contact details and the social profiles.
 *
 * Everything in the form is saved under `site`, each field at its own name:
 * the field `contact.email` is read back as `site.contact.email`. A site adds
 * its own settings by extending this class, adding fields to formComponents(),
 * and naming its class under `pages` in config/mela-cms.php.
 */
class ManageSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $slug = 'settings';

    protected static ?string $title = 'Settings';

    public function mount(): void
    {
        $this->form->fill(app(Settings::class)->get('site', []));
    }

    public static function formComponents(): array
    {
        return [
            Section::make('Site')
                ->schema([
                    Translations::make([
                        TextInput::make('name')
                            ->label('Site name')
                            ->placeholder(config('app.name'))
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Description')
                            ->helperText('One or two sentences about the site. Search engines show it for pages without a description of their own.')
                            ->rows(3)
                            ->maxLength(300),
                    ]),
                    MediaPicker::make('share_image')
                        ->label('Share image')
                        ->helperText('Shown when a page without an image of its own is shared on a social network.')
                        ->images(),
                ]),
            Section::make('Contact')
                ->columns(2)
                ->schema([
                    TextInput::make('contact.email')
                        ->label('Email')
                        ->email()
                        ->maxLength(255),
                    TextInput::make('contact.phone')
                        ->label('Phone')
                        ->tel()
                        ->maxLength(50),
                    Textarea::make('contact.address')
                        ->label('Address')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Social profiles')
                ->description('The full address of each profile. Leave empty what the site does not have.')
                ->columns(2)
                ->schema(array_map(
                    fn (string $network, string $label) => TextInput::make("social.{$network}")
                        ->label($label)
                        ->url()
                        ->maxLength(255),
                    array_keys(static::socialNetworks()),
                    static::socialNetworks(),
                )),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function socialNetworks(): array
    {
        return [
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'linkedin' => 'LinkedIn',
            'x' => 'X',
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
        ];
    }

    public function save(): void
    {
        app(Settings::class)->merge('site', $this->form->getState());

        $this->notifySaved();
    }
}
