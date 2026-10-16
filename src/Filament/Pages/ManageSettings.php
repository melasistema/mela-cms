<?php

namespace Melasistema\MelaCms\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\UnorderedList;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Melasistema\MelaCms\Filament\Forms\MediaPicker;
use Melasistema\MelaCms\Filament\Forms\Translations;
use Melasistema\MelaCms\Install\Changelog;
use Melasistema\MelaCms\Install\CoreVersion;
use Melasistema\MelaCms\Models\Page;
use Melasistema\MelaCms\Seo;
use Melasistema\MelaCms\Settings;
use Melasistema\MelaCms\SiteRoutes;
use RuntimeException;

/**
 * Where an admin fills in what every site has: its name, description, home
 * page and share image, the contact details and the social profiles.
 *
 * Everything in the form is saved under `site`, each field at its own name:
 * the field `contact.email` is read back as `site.contact.email`. A site adds
 * its own settings by extending this class, adding fields to formComponents(),
 * and naming its class under `pages` in config/mela-cms.php.
 *
 * Below the form, outside what is saved, the page shows the MelaCMS version
 * of the site and what it brought. On a local site it can also look for a
 * newer version, which is installed with `php artisan mela:update`.
 */
class ManageSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $slug = 'settings';

    protected static ?string $title = 'Settings';

    /**
     * The newest MelaCMS release, once looked for and newer than the site's.
     */
    #[Locked]
    public ?string $newerCore = null;

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
                        ->visible(fn (): bool => static::servesPages()),
                    Callout::make('The site has its own route for /')
                        ->key('home_route')
                        ->description('So the page chosen here is not shown at the root of the site. To show it, a developer removes the / route from routes/web.php.')
                        ->warning()
                        ->visible(fn (): bool => static::servesPages() && SiteRoutes::answer('/')),
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
     * Whether the site has pages to choose a home page from.
     */
    protected static function servesPages(): bool
    {
        return (bool) config('mela-cms.public_pages') && config('mela-cms.resources.pages') !== null;
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

    /**
     * @return array<Component>
     */
    protected function afterForm(): array
    {
        $installed = app(CoreVersion::class)->installed();
        $entries = $installed === null ? [] : app(Changelog::class)->of($installed);

        return [
            Section::make('MelaCMS')
                ->key('core')
                ->description($installed === null
                    ? 'A development copy of the core, not a release.'
                    : "Version {$installed}")
                ->afterHeader([
                    Action::make('checkCore')
                        ->label('Check for a new version')
                        ->link()
                        ->visible($installed !== null && app()->isLocal())
                        ->action(fn () => $this->checkCore($installed)),
                ])
                ->schema([
                    Callout::make(fn (): string => "MelaCMS {$this->newerCore} is available")
                        ->description('Run this command in the project, on your computer, then commit and release the site as usual.')
                        ->info()
                        ->footer([
                            Text::make('php artisan mela:update')
                                ->fontFamily(FontFamily::Mono)
                                ->copyable(),
                        ])
                        ->visible(fn (): bool => $this->newerCore !== null),
                    Text::make("What's new in {$installed}")
                        ->weight(FontWeight::SemiBold)
                        ->visible($entries !== []),
                    UnorderedList::make(array_map(
                        fn (string $entry) => Text::make(new HtmlString(Str::inlineMarkdown($entry, ['html_input' => 'escape']))),
                        $entries,
                    ))->visible($entries !== []),
                ]),
        ];
    }

    /**
     * Asks Composer for the newest release, which takes a few seconds, so only
     * when the button is pressed.
     */
    protected function checkCore(string $installed): void
    {
        try {
            $latest = app(CoreVersion::class)->latest();
        } catch (RuntimeException $exception) {
            Notification::make()
                ->danger()
                ->title('Could not look for a new version')
                ->body($exception->getMessage())
                ->send();

            return;
        }

        if ($latest !== null && version_compare($latest, $installed, '>')) {
            $this->newerCore = $latest;

            return;
        }

        Notification::make()
            ->success()
            ->title("MelaCMS {$installed} is the newest version")
            ->send();
    }

    public function save(): void
    {
        app(Settings::class)->merge('site', $this->form->getState());

        $this->notifySaved();
    }
}
