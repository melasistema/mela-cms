<?php

namespace Melasistema\MelaCms\Media;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Melasistema\MelaCms\Models\Media;
use RuntimeException;
use Throwable;

/**
 * Makes the sizes of an image listed under `media.conversions` in the config,
 * as WebP files next to the original. The original is never changed.
 */
class Conversions
{
    /**
     * The formats a size is made from. An SVG has no pixels to resize.
     */
    protected const array CONVERTIBLE = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];

    /**
     * What GD takes for one pixel of a decoded image: four bytes of colour,
     * with room for its own bookkeeping.
     */
    protected const int BYTES_PER_PIXEL = 5;

    /**
     * Read the dimensions of the image and write its sizes. Sets `width`,
     * `height` and `conversions` on the model without saving it.
     *
     * A file that cannot be read as an image, or an image too large for the
     * memory limit, is left with the original only: an upload does not fail
     * because of it.
     */
    public function generate(Media $media): void
    {
        $this->remove($media);

        if (! in_array($media->mime_type, static::CONVERTIBLE, true)) {
            return;
        }

        try {
            $binary = $media->storage()->get($media->path);

            // A fatal error for exceeding the memory limit cannot be caught,
            // so an image too large to decode is not decoded at all.
            [$width, $height] = getimagesizefromstring($binary) ?: [0, 0];

            if (! $this->fitsInMemory($width, $height)) {
                $media->width = $width;
                $media->height = $height;

                throw new RuntimeException("The image [{$media->path}] of {$width}x{$height} pixels needs more memory than the PHP memory limit allows. Its sizes are not made.");
            }

            $image = $this->manager()->decodeBinary($binary);

            $media->width = $image->width();
            $media->height = $image->height();

            // Resizing would keep the first frame only. The original still moves.
            if ($image->isAnimated()) {
                return;
            }

            foreach (config('mela-cms.media.conversions') as $name => $size) {
                $resized = clone $image;

                isset($size['width'], $size['height'])
                    ? $resized->coverDown($size['width'], $size['height'])
                    : $resized->scaleDown($size['width'] ?? null, $size['height'] ?? null);

                $path = $this->path($media, $name);

                $media->storage()->put($path, (string) $resized->encode(new WebpEncoder(config('mela-cms.media.quality'))));

                $media->conversions = [
                    ...$media->conversions ?? [],
                    $name => ['path' => $path, 'width' => $resized->width(), 'height' => $resized->height()],
                ];
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Whether GD can hold the image, a copy of it and a size in what is left
     * of the memory limit. Imagick allocates outside of the PHP limit and
     * has limits of its own.
     */
    protected function fitsInMemory(int $width, int $height): bool
    {
        $limit = ini_parse_quantity(ini_get('memory_limit'));

        if ($limit <= 0 || extension_loaded('imagick')) {
            return true;
        }

        return $width * $height * static::BYTES_PER_PIXEL * 3 < $limit - memory_get_usage();
    }

    protected function remove(Media $media): void
    {
        $media->storage()->delete(array_column($media->conversions ?? [], 'path'));

        $media->conversions = null;
    }

    protected function path(Media $media, string $conversion): string
    {
        $directory = trim(dirname($media->path), './');

        return ltrim($directory.'/conversions/', '/').pathinfo($media->path, PATHINFO_FILENAME).'-'.$conversion.'.webp';
    }

    protected function manager(): ImageManager
    {
        return new ImageManager(extension_loaded('imagick') ? ImagickDriver::class : GdDriver::class);
    }
}
