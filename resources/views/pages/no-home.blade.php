{{--
    Shown at the root of the site while there is no home page to show: none
    chosen on the Settings page, or the chosen one not published. Someone who
    can open the admin also gets a link to it.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $siteName }}</title>
    <style>
        :root { color-scheme: light dark; --text: #0b1622; --muted: #475569; --link: #1d6fa5; --background: #f8fafc; }
        @media (prefers-color-scheme: dark) {
            :root { --text: #f8fafc; --muted: #94a3b8; --link: #38bdf8; --background: #0b1622; }
            main .mela-mark { --mela-mark-up: #ffffff; --mela-mark-down: #38bdf8; }
        }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1rem; box-sizing: border-box; background: var(--background); color: var(--text); font-family: ui-sans-serif, system-ui, sans-serif; text-align: center; }
        h1 { margin: 1.5rem 0 0.5rem; font-size: 1.5rem; font-weight: 600; }
        p { margin: 0.5rem 0; color: var(--muted); line-height: 1.5; }
        a { color: var(--link); font-weight: 600; }
    </style>
</head>
<body>
    <main>
        @include('mela-cms::filament.mark', ['id' => 'mela-no-home-mark', 'size' => 56])

        <h1>{{ $siteName }}</h1>
        <p>This site has no home page yet.</p>

        @if ($adminUrl)
            <p>Choose one on the Settings page of the <a href="{{ $adminUrl }}">admin</a>, among the published pages.</p>
        @endif
    </main>
</body>
</html>
