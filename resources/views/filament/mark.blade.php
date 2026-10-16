{{--
    The MelaCMS ring mark, inline so it follows the panel theme: Ink and Mela
    Blue in light mode, white and Sky in dark mode. `$id` keeps the ids of two
    marks on one page apart.
--}}
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 512 512" class="mela-mark" role="img" aria-label="MelaCMS">
    <style>
        .mela-mark { --mela-mark-up: #0b1622; --mela-mark-down: #1d6fa5; }
        .dark .mela-mark { --mela-mark-up: #ffffff; --mela-mark-down: #38bdf8; }
    </style>
    <defs>
        <clipPath id="{{ $id }}-clip"><circle cx="256" cy="256" r="200"/></clipPath>
        <mask id="{{ $id }}-mask"><polyline points="96,500 176,150 256,350 336,150 416,500" fill="none" stroke="white" stroke-width="50" stroke-linejoin="miter" stroke-miterlimit="10"/></mask>
        <linearGradient id="{{ $id }}-ring" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#1d6fa5"/><stop offset="1" stop-color="#38bdf8"/>
        </linearGradient>
    </defs>
    <circle cx="256" cy="256" r="222" fill="none" stroke="url(#{{ $id }}-ring)" stroke-width="30"/>
    <g clip-path="url(#{{ $id }}-clip)"><g mask="url(#{{ $id }}-mask)">
        <rect width="512" height="512" style="fill: var(--mela-mark-up)"/>
        <rect x="176" width="80" height="512" style="fill: var(--mela-mark-down)"/>
        <rect x="336" width="176" height="512" style="fill: var(--mela-mark-down)"/>
    </g></g>
</svg>
