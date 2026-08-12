@php
    /*
     * The navigation, as data.
     *
     * Every entry names a route; anything not yet registered is skipped
     * rather than rendered as a dead link, so each later build step lights
     * up its own entry automatically — the same rule the public navbar
     * follows.
     */
    $sections = [
        __('dashboard.nav.overview') => [
            ['route' => 'dashboard', 'label' => __('dashboard.nav.dashboard'), 'icon' => 'home'],
        ],
        __('dashboard.nav.settings_group') => [
            ['route' => 'dashboard.settings.edit', 'label' => __('dashboard.nav.settings'), 'icon' => 'cog'],
        ],
    ];

    $icons = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>',
        'cog' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 9 19.4a1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
    ];
@endphp

{{--
    Backdrop for the mobile drawer. z-40, below the panel (z-50) and above
    everything else.
--}}
<div
    x-show="sidebarOpen"
    x-cloak
    x-transition.opacity
    @click="sidebarOpen = false"
    class="fixed inset-0 z-40 bg-petrol-deep/60 lg:hidden"
></div>

{{--
    One element, two behaviours: a fixed drawer below lg, a static column
    at lg and up.

    The transform uses `-translate-x-full` under a `ltr:` / `rtl:` pair
    rather than a logical property, because there is no logical translate
    utility — and the drawer has to slide out through the INLINE START
    edge, which is the left in English and the right in Arabic.
--}}
<aside
    x-trap.noscroll="sidebarOpen"
    {{--
        The CLOSED state is in the static class list; the open state is the
        only thing Alpine adds.

        Doing it the other way round — the whole pair in :class, plus
        x-cloak to hide the element until Alpine resolved it — meant the
        DESKTOP sidebar was display:none until JavaScript booted, so the
        dashboard rendered with no navigation at all on a slow connection
        and never gained any if the bundle failed. Here the static classes
        are already correct for every case except "mobile, drawer open",
        which is the one state that genuinely needs JS.

        The transform is ltr:/rtl: rather than a logical utility because
        Tailwind has no logical translate: the drawer slides out through
        the INLINE START edge, which is the left in English and the right
        in Arabic.
    --}}
    :class="sidebarOpen && '!translate-x-0'"
    class="fixed inset-y-0 start-0 z-50 flex w-72 shrink-0 flex-col border-e border-white/10 bg-petrol-deep text-mist transition-transform duration-200 ease-in-out ltr:-translate-x-full rtl:translate-x-full lg:static lg:!translate-x-0"
    role="navigation"
    aria-label="{{ __('dashboard.topbar.menu_dialog_label') }}"
>
    <div class="flex h-[72px] shrink-0 items-center justify-between gap-2 border-b border-white/10 px-4">
        <a href="{{ route('dashboard') }}" class="flex min-h-[44px] items-center rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-amber">
            {{-- logo-light: the reversed lockup, for the petrol sidebar.
                 Intrinsic dimensions are the file's real 530x363 (a
                 STACKED lockup), so the box is reserved correctly. --}}
            <img src="{{ asset('logo-light.png') }}" alt="{{ __('site.brand.name') }}" width="530" height="363" class="h-11 w-auto">
        </a>

        <button
            type="button"
            @click="sidebarOpen = false"
            class="-me-2 inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg text-mist-dim transition hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-amber lg:hidden"
            aria-label="{{ __('dashboard.topbar.close_menu') }}"
        >
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path d="M6 6l12 12M6 18L18 6" stroke-linecap="round" />
            </svg>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-5">
        @foreach ($sections as $sectionLabel => $items)
            @php
                $visible = array_filter($items, fn ($item) => Illuminate\Support\Facades\Route::has($item['route']));
            @endphp

            @if ($visible)
                <p class="px-3 pb-2 pt-4 text-xs font-extrabold uppercase tracking-wider text-mist-dim/70 first:pt-0">
                    {{ $sectionLabel }}
                </p>

                <ul class="space-y-1">
                    @foreach ($visible as $item)
                        @php
                            // Prefix match so a section's child screens keep
                            // the parent highlighted — "dashboard.settings.edit"
                            // and a future "dashboard.settings.advanced" are
                            // the same place to a person reading the sidebar.
                            $current = request()->routeIs($item['route']) || request()->routeIs(rtrim($item['route'], '.edit').'.*');
                        @endphp
                        <li>
                            <a
                                href="{{ route($item['route']) }}"
                                @if ($current) aria-current="page" @endif
                                @class([
                                    'flex min-h-[44px] items-center gap-3 rounded-xl px-3 text-sm font-bold transition',
                                    'bg-orange-ink text-white' => $current,
                                    'text-mist hover:bg-white/5 hover:text-white' => ! $current,
                                ])
                                @click="sidebarOpen = false"
                            >
                                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    {!! $icons[$item['icon']] !!}
                                </svg>
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endforeach
    </nav>

    <div class="shrink-0 border-t border-white/10 p-3">
        <a
            href="{{ route('home') }}"
            class="flex min-h-[44px] items-center gap-3 rounded-xl px-3 text-sm font-bold text-mist-dim transition hover:bg-white/5 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-amber"
        >
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                <path d="M15 3h6v6" />
                <path d="M10 14 21 3" />
            </svg>
            {{ __('dashboard.nav.view_site') }}
        </a>
    </div>
</aside>
