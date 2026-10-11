{{--
    The plain page of the core, used until the site has its own
    resources/views/pages/default.blade.php.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @seo
    @hreflang
</head>
<body>
    <main>
        <h1>{{ $page->title }}</h1>

        @if ($page->cover)
            <img
                src="{{ $page->cover->url('large') }}"
                @if ($page->cover->srcset()) srcset="{{ $page->cover->srcset() }}" sizes="100vw" @endif
                width="{{ $page->cover->width }}"
                height="{{ $page->cover->height }}"
                alt="{{ $page->cover->alt }}"
            >
        @endif

        @richtext($page->body)
    </main>
</body>
</html>
