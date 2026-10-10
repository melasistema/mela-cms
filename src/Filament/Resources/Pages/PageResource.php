<?php

namespace Melasistema\MelaCms\Filament\Resources\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Melasistema\MelaCms\Enums\PublicationStatus;
use Melasistema\MelaCms\Filament\Forms\MediaPicker;
use Melasistema\MelaCms\Filament\Forms\RichText;
use Melasistema\MelaCms\Filament\Forms\SlugInput;
use Melasistema\MelaCms\Filament\Forms\Translations;
use Melasistema\MelaCms\Filament\Resources\Pages\Pages\CreatePage;
use Melasistema\MelaCms\Filament\Resources\Pages\Pages\EditPage;
use Melasistema\MelaCms\Filament\Resources\Pages\Pages\ListPages;
use Melasistema\MelaCms\Languages;
use Melasistema\MelaCms\Models\Page;

/**
 * The pages of the site: title, address and text in each language, a
 * template, a cover image and a publication date.
 *
 * A site whose template needs more fields extends this class, adds them to
 * formComponents() under `fields` (a field named `fields.hero.button` is read
 * in the view as $page->field('hero.button')), and sets its class as
 * `resources.pages` in config/mela-cms.php.
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $slug = 'pages';

    protected static ?string $modelLabel = 'page';

    protected static ?string $pluralModelLabel = 'pages';

    protected static ?int $navigationSort = -1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::formComponents())->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::tableColumns())
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                static::viewOnSiteAction(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<Component>
     */
    public static function formComponents(): array
    {
        return [
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
                            ->helperText('The address of the page on the site, after the domain. A language left empty uses the address of the default language.')
                            ->prefix('/')
                            ->path()
                            ->required(),
                        RichText::make('body')
                            ->label('Text'),
                    ]),
                ]),
            Section::make()
                ->columnSpan(['lg' => 1])
                ->schema([
                    DateTimePicker::make('published_at')
                        ->label('Published on')
                        ->helperText('Empty: a draft, seen only by who is logged in. A date to come: public from that date.')
                        ->seconds(false),
                    Select::make('template')
                        ->helperText('How the page looks on the site.')
                        ->options(fn (): array => Page::templates())
                        ->default('default')
                        ->required()
                        ->native(false)
                        // With one template there is nothing to choose.
                        ->visible(fn (): bool => count(Page::templates()) > 1),
                    MediaPicker::make('cover_id')
                        ->label('Cover image')
                        ->images(),
                ]),
        ];
    }

    /**
     * @return array<Column>
     */
    public static function tableColumns(): array
    {
        return [
            TextColumn::make('title')
                ->weight('medium')
                ->description(fn (Page $record): string => $record->isHome() ? 'Home page' : '/'.$record->path())
                // The title and the address are searched in every site language.
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(function (Builder $query) use ($search) {
                    foreach (app(Languages::class)->all() as $locale) {
                        $query
                            ->orWhereLike("title->{$locale}", "%{$search}%")
                            ->orWhereLike("slug->{$locale}", "%{$search}%");
                    }
                }))
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('title->'.app(Languages::class)->default(), $direction)),
            TextColumn::make('template')
                ->formatStateUsing(fn (string $state): string => Page::templates()[$state] ?? $state)
                ->visible(fn (): bool => count(Page::templates()) > 1),
            TextColumn::make('status')
                ->state(fn (Page $record): PublicationStatus => $record->status())
                ->badge(),
            TextColumn::make('updated_at')
                ->label('Last change')
                ->since()
                ->sortable(),
        ];
    }

    /**
     * Opens the page on the site. A draft is shown too, to who may edit it.
     */
    public static function viewOnSiteAction(): Action
    {
        return Action::make('viewOnSite')
            ->label('View')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->url(fn (Page $record): string => $record->url(), shouldOpenInNewTab: true)
            ->visible(fn (): bool => (bool) config('mela-cms.public_pages'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
