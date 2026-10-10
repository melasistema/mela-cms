<?php

namespace Melasistema\MelaCms\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Melasistema\MelaCms\Filament\Forms\MediaPicker;
use Melasistema\MelaCms\Filament\Forms\Translations;
use Melasistema\MelaCms\Models\Page;
use Melasistema\MelaCms\Seo;
use Melasistema\MelaCms\Settings;

/**
 * Where an admin fills in what every site has: its name, description, home
 * page and share image, the contact details and the social profiles.
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
                    Select::make('home_page')
                        ->label('Home page')
                        ->helperText('The page shown at the root of the site.')
                        ->options(fn (): array => Page::query()->get()->mapWithKeys(fn (Page $page): array => [$page->getKey() => $page->title])->all())
                        ->searchable()
                        ->visible(fn (): bool => (bool) config('mela-cms.public_pages') && config('mela-cms.resources.pages') !== null),
                    MediaPicker::make('share_image')
                        ->label('Share image')
                        ->helperText('Shown when a page without an image of its own is shared on a social network.')
                        ->images(),
                ]),
            Section::make('Search engines')
                ->schema([
                    TextInput::make('seo.title_format')
                        ->label('Title format')
                        ->helperText('How the title of a page is written in search results and in the browser tab. :title is the title of the page, :site the name of the site.')
                        ->placeholder(Seo::DEFAULT_TITLE_FORMAT)
                        ->maxLength(100),
                    Toggle::make('seo.noindex')
                        ->label('Ask search engines not to list the site')
                        ->helperText('For a site that is not ready yet. Every page asks not to be listed and the sitemap is switched off.'),
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
