{{--
    The footer of every admin page, the login page included. The version is
    shown to signed-in users only, so a guest cannot read it from the login page.
--}}
<style>
    .mela-footer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem 1rem;
        width: 100%;
        padding: 1.5rem 1rem;
        font-size: 0.875rem;
        line-height: 1.25rem;
        color: var(--gray-500);
    }

    .dark .mela-footer {
        color: var(--gray-400);
    }

    .mela-footer a {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: inherit;
    }

    .mela-footer a:hover strong {
        color: var(--primary-600);
    }

    .dark .mela-footer a:hover strong {
        color: var(--primary-400);
    }

    .mela-footer strong {
        font-weight: 600;
        color: var(--gray-700);
    }

    .dark .mela-footer strong {
        color: var(--gray-200);
    }

    .fi-simple-layout .mela-footer {
        justify-content: center;
    }

    @media (min-width: 768px) {
        .mela-footer {
            padding-inline: 1.5rem;
        }
    }

    @media (min-width: 1024px) {
        .mela-footer {
            padding-inline: 2rem;
        }
    }
</style>

<footer class="mela-footer">
    <a href="https://docs.mela-cms.melasistema.com" target="_blank" rel="noopener">
        @include('mela-cms::filament.mark', ['id' => 'mela-footer-mark', 'size' => 20])
        <span>Built with <strong>MelaCMS</strong></span>
    </a>

    @if ($version)
        <span>Version {{ $version }}</span>
    @endif
</footer>
