<?php

namespace Melasistema\MelaCms\Media;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Melasistema\MelaCms\Models\Media;
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
     * Read the dimensions of the image and write its sizes. Sets `width`,
     * `height` and `conversions` on the model without saving it.
     *
     * A file that cannot be read as an image is left with the original only:
     * an upload does not fail because of it.
     */
    public function generate(Media $media): void
    {
        $this->remove($media);

        if (! in_array($media->mime_type, static::CONVERTIBLE, true)) {
            return;
        }

        try {
            $binary = $media->storage()->get($media->path);
            $image = $this->manager()->decodeBinary($binary);

            $media->width = $image->width();
            $media->height = $image->height();

            // Resizing would keep the first frame only. The original still moves.
            if ($image->isAnimated()) {
                return;
            }

            foreach (config('mela-cms.media.conversions') as $name => $size) {
                $resized = $this->manager()->decodeBinary($binary);

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
