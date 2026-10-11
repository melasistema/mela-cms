<?php

namespace Melasistema\MelaCms;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\HtmlString;

/**
 * Turns what a rich text field saved into HTML that is safe to print: the
 * markup is sanitized, and the address of each image is read again from the
 * media disk, so it follows the site when its domain changes.
 *
 * In a view: @richtext($page->body)
 */
class RichContent
{
    public static function html(?string $content): HtmlString
    {
        if (blank($content)) {
            return new HtmlString('');
        }

        return new HtmlString(
            RichContentRenderer::make($content)
                ->fileAttachmentsDisk(config('mela-cms.media.disk'))
                ->fileAttachmentsVisibility('public')
                ->toHtml(),
        );
    }
}
