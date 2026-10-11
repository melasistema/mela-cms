<?php

namespace Melasistema\MelaCms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Melasistema\MelaCms\Media\Conversions;
use Melasistema\MelaCms\Models\Media;

class RegenerateMediaCommand extends Command
{
    protected $signature = 'mela:media-regenerate';

    protected $description = 'Make the image sizes of every file in the media library again, after `media.conversions` changed in the config';

    public function handle(Conversions $conversions): int
    {
        $count = 0;

        Media::query()->lazyById()->each(function (Media $media) use ($conversions, &$count) {
            $conversions->generate($media);
            $media->save();

            $count++;
        });

        $this->components->info("Image sizes made again for {$count} ".Str::plural('file', $count).'.');

        return self::SUCCESS;
    }
}
