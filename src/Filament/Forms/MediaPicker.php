<?php

namespace Melasistema\MelaCms\Filament\Forms;

use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Melasistema\MelaCms\Models\Media;

/**
 * A form field that picks a file from the media library, or uploads a new one
 * into it. It saves the id of the file, or a list of ids with multiple().
 *
 *     MediaPicker::make('cover_id')->images()
 *     MediaPicker::make('gallery')->images()->multiple()
 *
 * The first needs a nullable foreign key to `mela_media`, the second a JSON
 * column cast to array.
 */
class MediaPicker extends Select
{
    protected bool $imagesOnly = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->searchable()
            ->allowHtml()
            // The latest uploads are offered before anything is typed.
            ->options(fn (MediaPicker $component): array => $component->mediaLabels(
                $component->mediaQuery()->limit($component->getOptionsLimit())->get(),
            ))
            ->getSearchResultsUsing(fn (MediaPicker $component, string $search): array => $component->mediaLabels(
                $component->mediaQuery()->whereLike('name', "%{$search}%")->limit($component->getOptionsLimit())->get(),
            ))
            ->getOptionLabelUsing(fn (MediaPicker $component, mixed $value): ?string => $component->mediaLabels(Media::query()->whereKey($value)->get())[$value] ?? null)
            ->getOptionLabelsUsing(fn (MediaPicker $component, array $values): array => $component->mediaLabels(Media::inOrder($values)))
            // A searchable select accepts any value, so the id is checked here.
            ->rule(fn (MediaPicker $component): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component) {
                $ids = array_unique(Arr::wrap($value));

                if ($component->mediaQuery()->whereKey($ids)->count() !== count($ids)) {
                    $fail(__('validation.exists', ['attribute' => $component->getValidationAttribute()]));
                }
            })
            ->createOptionModalHeading('Upload')
            ->createOptionForm(fn (MediaPicker $component): array => [
                MediaUpload::make('file')
                    ->hiddenLabel()
                    ->imagesOnly($component->imagesOnly)
                    ->storeFileNamesIn('file_name')
                    ->required(),
            ])
            ->createOptionUsing(fn (array $data): int => Media::fromFile($data['file'], $data['file_name'] ?? null)->getKey());
    }

    /**
     * Offer and accept images only.
     */
    public function images(bool $condition = true): static
    {
        $this->imagesOnly = $condition;

        return $this;
    }

    /**
     * @return Builder<Media>
     */
    protected function mediaQuery(): Builder
    {
        return Media::query()
            ->when($this->imagesOnly, fn (Builder $query) => $query->whereLike('mime_type', 'image/%'))
            ->latest('id');
    }

    /**
     * @param  Collection<int, Media>  $media
     * @return array<int, string>
     */
    protected function mediaLabels(Collection $media): array
    {
        return $media->mapWithKeys(fn (Media $media): array => [$media->getKey() => static::mediaLabel($media)])->all();
    }

    /**
     * The name of the file, after a small preview when it is an image.
     */
    protected static function mediaLabel(Media $media): string
    {
        $name = e($media->name);

        if (! $media->isImage()) {
            return $name;
        }

        return '<span style="display: flex; align-items: center; gap: 0.5rem;">'
            .'<img src="'.e($media->url('thumb')).'" alt="" style="width: 2rem; height: 2rem; object-fit: cover; border-radius: 0.25rem;">'
            .$name
            .'</span>';
    }
}
