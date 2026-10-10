<?php

namespace Melasistema\MelaCms\Filament\Resources\Media;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Component as ColumnLayout;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Melasistema\MelaCms\Filament\Forms\Translations;
use Melasistema\MelaCms\Filament\Resources\Media\Pages\ManageMedia;
use Melasistema\MelaCms\Models\Media;

/**
 * The media library: every uploaded file, with its name and the alternative
 * text of an image in each site language.
 *
 * A site that needs more extends this class, overrides formComponents() or
 * tableColumns(), and sets its class as `resources.media` in
 * config/mela-cms.php.
 */
class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'media';

    protected static ?string $modelLabel = 'file';

    protected static ?string $pluralModelLabel = 'media';

    protected static ?string $navigationLabel = 'Media';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::formComponents());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::tableColumns())
            ->contentGrid(['sm' => 2, 'md' => 3, 'xl' => 5])
            ->defaultSort('id', 'desc')
            ->paginationPageOptions([20, 40, 80])
            ->filters([
                SelectFilter::make('type')
                    ->options(['images' => 'Images', 'documents' => 'Documents'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'images' => $query->whereLike('mime_type', 'image/%'),
                        'documents' => $query->where(fn (Builder $query) => $query->whereNotLike('mime_type', 'image/%')->orWhereNull('mime_type')),
                        default => $query,
                    }),
            ])
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

    /**
     * @return array<Component>
     */
    public static function formComponents(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Translations::make([
                TextInput::make('alt')
                    ->label('Alternative text')
                    ->helperText('What the image shows, for people who cannot see it and for search engines.')
                    ->maxLength(255),
            ])->visible(fn (?Media $record): bool => $record?->isImage() ?? true),
        ];
    }

    /**
     * @return array<Column|ColumnLayout>
     */
    public static function tableColumns(): array
    {
        return [
            Stack::make([
                ImageColumn::make('preview')
                    ->state(fn (Media $record): ?string => $record->isImage() ? ($record->conversions['thumb']['path'] ?? $record->path) : null)
                    ->disk(fn (Media $record): string => $record->disk)
                    ->visibility('public')
                    ->defaultImageUrl(static::documentIcon())
                    ->imageHeight(160)
                    ->imageWidth('100%')
                    ->extraImgAttributes(['style' => 'object-fit: cover; border-radius: 0.5rem;', 'loading' => 'lazy']),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->limit(40)
                    ->description(fn (Media $record): string => implode(' · ', array_filter([
                        str($record->path)->afterLast('.')->upper()->toString(),
                        $record->width ? "{$record->width} × {$record->height}" : null,
                        Number::fileSize($record->size),
                    ]))),
            ])->space(2),
        ];
    }

    /**
     * Shown in the grid in place of a file that is not an image.
     */
    protected static function documentIcon(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 160"><rect width="160" height="160" fill="#f4f4f5"/>'
            .'<path d="M62 44h24l14 14v54a4 4 0 0 1-4 4H62a4 4 0 0 1-4-4V48a4 4 0 0 1 4-4zm24 0v14h14" fill="none" stroke="#a1a1aa" stroke-width="4" stroke-linejoin="round"/></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMedia::route('/'),
        ];
    }
}
