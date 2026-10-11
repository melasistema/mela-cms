<?php

namespace Melasistema\MelaCms\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Melasistema\MelaCms\Media\Conversions;
use Spatie\Translatable\HasTranslations;

/**
 * A file in the media library. It is uploaded once and can be used by any
 * record of the site: a model points to it with a foreign key, or with a list
 * of ids for a gallery.
 *
 * @property string $disk
 * @property string $path
 * @property string $name
 * @property string|null $alt
 * @property string|null $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property array<string, array{path: string, width: int, height: int}>|null $conversions
 */
class Media extends Model
{
    use HasTranslations;

    protected $table = 'mela_media';

    protected $guarded = [];

    /**
     * @var array<string>
     */
    public array $translatable = ['alt'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'conversions' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Media $media) {
            $media->storage()->delete([
                $media->path,
                ...array_column($media->conversions ?? [], 'path'),
            ]);
        });
    }

    /**
     * Add a file that is already on the media disk to the library, and make
     * its image sizes.
     */
    public static function fromFile(string $path, ?string $name = null, ?string $disk = null): static
    {
        $media = new static([
            'disk' => $disk ?? config('mela-cms.media.disk'),
            'path' => $path,
        ]);

        $media->fill([
            'name' => Str::limit(pathinfo($name ?? $path, PATHINFO_FILENAME), 250, ''),
            'mime_type' => $media->storage()->mimeType($path) ?: null,
            'size' => $media->storage()->size($path),
        ]);

        app(Conversions::class)->generate($media);

        $media->save();

        return $media;
    }

    /**
     * The files with these ids, in the order of the ids. For a gallery saved
     * as a list of ids; an id whose file was deleted is skipped.
     *
     * @param  array<int|string>|null  $ids
     * @return Collection<int, static>
     */
    public static function inOrder(?array $ids): Collection
    {
        $media = static::query()->findMany($ids ?? [])->keyBy(fn (Media $media) => $media->getKey());

        return new Collection(array_values(array_filter(array_map(
            fn (int|string $id) => $media->get($id),
            $ids ?? [],
        ))));
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    /**
     * The address of the file, or of one of its sizes. A size the file does
     * not have (it is not an image, or the size was added to the config
     * later) gives the original.
     */
    public function url(?string $conversion = null): string
    {
        return $this->storage()->url($this->conversions[$conversion]['path'] ?? $this->path);
    }

    /**
     * The sizes that keep the proportions of the image, for the `srcset`
     * attribute of an <img>. Null when there are none.
     */
    public function srcset(): ?string
    {
        $sources = [];

        foreach ($this->conversions ?? [] as $name => $conversion) {
            if (config("mela-cms.media.conversions.{$name}.height") === null) {
                $sources[$conversion['width']] ??= $this->storage()->url($conversion['path']).' '.$conversion['width'].'w';
            }
        }

        ksort($sources);

        return $sources === [] ? null : implode(', ', $sources);
    }

    public function storage(): FilesystemAdapter
    {
        return Storage::disk($this->disk);
    }
}
