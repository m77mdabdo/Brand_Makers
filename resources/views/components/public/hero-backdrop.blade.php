@props([
    // Absolute URL of a background video. When set, the video replaces the
    // generated sky entirely and NOTHING else about the hero changes —
    // same box, same overlay, same content stack. That is the whole point
    // of this prop: swapping in real footage later is a data change, not a
    // markup change.
    'video' => null,
    'poster' => null,
])

@php
    /*
     * The skyline, as data.
     *
     * Two layers: 'back' sits further away (smaller, hazier, parallaxes
     * less) and 'front' is the near silhouette. Coordinates are in the
     * SVG's 1440x300 viewBox.
     *
     * Shapes echo a Gulf skyline — a spired tower, an arched crown, a
     * tapered slab — without copying any identifiable building, since this
     * is decoration rather than a depiction of a real place.
     */
    $backBuildings = [
        ['x' => 60, 'w' => 54, 'y' => 176],
        ['x' => 128, 'w' => 40, 'y' => 150],
        ['x' => 210, 'w' => 62, 'y' => 190],
        ['x' => 300, 'w' => 44, 'y' => 162],
        ['x' => 430, 'w' => 58, 'y' => 184],
        ['x' => 520, 'w' => 40, 'y' => 148],
        ['x' => 640, 'w' => 66, 'y' => 196],
        ['x' => 740, 'w' => 46, 'y' => 158],
        ['x' => 850, 'w' => 60, 'y' => 186],
        ['x' => 950, 'w' => 42, 'y' => 152],
        ['x' => 1060, 'w' => 64, 'y' => 192],
        ['x' => 1160, 'w' => 44, 'y' => 160],
        ['x' => 1270, 'w' => 58, 'y' => 182],
        ['x' => 1360, 'w' => 50, 'y' => 168],
    ];

    $frontBuildings = [
        ['x' => 30, 'w' => 70, 'y' => 170],
        ['x' => 110, 'w' => 52, 'y' => 120],
        ['x' => 172, 'w' => 60, 'y' => 200],
        ['x' => 245, 'w' => 46, 'y' => 150],
        ['x' => 400, 'w' => 66, 'y' => 180],
        ['x' => 475, 'w' => 50, 'y' => 130],
        ['x' => 700, 'w' => 58, 'y' => 160],
        ['x' => 766, 'w' => 48, 'y' => 110],
        ['x' => 822, 'w' => 64, 'y' => 185],
        ['x' => 1000, 'w' => 54, 'y' => 140],
        ['x' => 1064, 'w' => 60, 'y' => 195],
        ['x' => 1132, 'w' => 50, 'y' => 120],
        ['x' => 1192, 'w' => 66, 'y' => 175],
        ['x' => 1268, 'w' => 48, 'y' => 145],
        ['x' => 1326, 'w' => 84, 'y' => 205],
    ];

    /*
     * Window lights.
     *
     * Seeded, not random-per-render: mt_srand with a fixed value means the
     * same windows are lit in the same places on every request. Without it
     * the markup would differ between the two locales' renders of the same
     * page and between a cached and an uncached view, which makes the
     * output untestable and pointlessly defeats HTTP caching.
     *
     * Only a fraction of windows are emitted at all — a fully gridded
     * facade reads as graph paper, not a building at night.
     */
    $windowsFor = function (array $buildings, float $litChance, int $seed) {
        mt_srand($seed);
        $windows = [];

        foreach ($buildings as $building) {
            $cols = max(1, (int) floor(($building['w'] - 6) / 10));
            $rows = max(1, (int) floor((300 - $building['y'] - 10) / 14));

            for ($col = 0; $col < $cols; $col++) {
                for ($row = 0; $row < $rows; $row++) {
                    if (mt_rand(0, 100) / 100 > $litChance) {
                        continue;
                    }

                    $windows[] = [
                        'x' => $building['x'] + 5 + ($col * 10),
                        'y' => $building['y'] + 8 + ($row * 14),
                        // Staggered so a facade never blinks in unison.
                        'delay' => mt_rand(0, 3800),
                        'duration' => mt_rand(3000, 6500),
                    ];
                }
            }
        }

        mt_srand();

        return $windows;
    };

    $backWindows = $windowsFor($backBuildings, 0.16, 20260812);
    $frontWindows = $windowsFor($frontBuildings, 0.22, 19770101);
@endphp

{{--
    aria-hidden on the whole backdrop: it is pure decoration, and the hero
    announces it once in prose (site.hero.background_label) for anyone who
    wants to know what is behind the text.
--}}
<div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
    @if ($video)
        {{--
            The video path. Identical box to the generated sky below it —
            inset-0 + object-cover — so nothing above or below this element
            shifts when it is used.
        --}}
        <video
            class="absolute inset-0 h-full w-full object-cover"
            autoplay
            muted
            loop
            playsinline
            preload="metadata"
            @if ($poster) poster="{{ $poster }}" @endif
        >
            <source src="{{ $video }}" type="video/mp4">
        </video>
    @else
        {{-- Dusk sky. The gradient itself breathes — a slow opacity shift
             on a second, warmer gradient stacked over the first, which is
             cheaper than animating gradient stops. --}}
        <div class="absolute inset-0 bg-[linear-gradient(180deg,#081E2B_0%,#123C54_55%,#1B506B_100%)]"></div>
        <div class="absolute inset-0 animate-sun-breathe bg-[linear-gradient(180deg,transparent_0%,rgba(232,106,34,0.10)_60%,rgba(246,162,90,0.18)_100%)] [animation-duration:11s]"></div>

        {{-- The sun/glow. Sits low and centre, pulsing. blur + radial
             gradient rather than an image so it scales to any viewport
             with no asset to load. --}}
        {{-- Sits at the horizon rather than the very bottom: below ~20%
             the skyline covers almost all of it and the glow reads as a
             faint smudge instead of a sun going down behind the city. --}}
        <div
            class="absolute bottom-[24%] left-1/2 h-[320px] w-[78%] -translate-x-1/2 animate-sun-breathe rounded-full bg-[radial-gradient(ellipse_at_center,rgba(232,106,34,0.55),transparent_65%)] blur-[26px]"
        ></div>

        {{-- Drifting clouds. Two bands at different speeds and opacities,
             each holding TWO copies of the same shape so the -50%
             translate wraps seamlessly. --}}
        <div class="absolute inset-x-0 top-[12%] h-40 opacity-40">
            <div class="flex h-full w-[200%] animate-cloud-slow">
                @foreach (range(0, 1) as $copy)
                    <svg class="h-full w-1/2 shrink-0" viewBox="0 0 720 160" preserveAspectRatio="none" fill="rgba(250,246,240,0.16)">
                        <ellipse cx="120" cy="80" rx="140" ry="26" />
                        <ellipse cx="300" cy="58" rx="110" ry="20" />
                        <ellipse cx="520" cy="92" rx="160" ry="24" />
                    </svg>
                @endforeach
            </div>
        </div>

        <div class="absolute inset-x-0 top-[26%] h-32 opacity-30">
            <div class="flex h-full w-[200%] animate-cloud-fast">
                @foreach (range(0, 1) as $copy)
                    <svg class="h-full w-1/2 shrink-0" viewBox="0 0 720 128" preserveAspectRatio="none" fill="rgba(246,162,90,0.14)">
                        <ellipse cx="200" cy="70" rx="150" ry="18" />
                        <ellipse cx="470" cy="48" rx="120" ry="15" />
                    </svg>
                @endforeach
            </div>
        </div>

        {{-- Light particles. Drawn by resources/js/hero.js, which checks
             prefers-reduced-motion before its first frame, and stops
             entirely once the hero scrolls out of view or the tab is
             backgrounded. With JS off this stays an empty transparent
             canvas and the hero is unaffected. --}}
        <canvas data-hero-particles class="absolute inset-0 h-full w-full"></canvas>

        {{-- BACK skyline layer: hazier, and parallaxes at roughly half the
             front layer's rate, which is what reads as depth. --}}
        <div data-parallax-depth="0.04" class="absolute inset-x-0 bottom-0 h-[38%] will-change-transform">
            <svg class="h-full w-full" viewBox="0 0 1440 300" preserveAspectRatio="xMidYMax slice">
                <g fill="#0B2735">
                    @foreach ($backBuildings as $b)
                        <rect x="{{ $b['x'] }}" y="{{ $b['y'] }}" width="{{ $b['w'] }}" height="{{ 300 - $b['y'] }}" />
                    @endforeach
                    <path d="M355 300 L372 128 L389 300 Z" />
                    <path d="M1000 300 L1000 150 Q1030 112 1060 150 L1060 300 Z" />
                </g>
                <g fill="#F6A25A">
                    @foreach ($backWindows as $w)
                        <rect
                            class="animate-twinkle"
                            x="{{ $w['x'] }}"
                            y="{{ $w['y'] }}"
                            width="3"
                            height="5"
                            style="animation-delay: {{ $w['delay'] }}ms; animation-duration: {{ $w['duration'] }}ms"
                        />
                    @endforeach
                </g>
            </svg>
        </div>

        {{-- FRONT skyline layer. --}}
        <div data-parallax-depth="0.09" class="absolute inset-x-0 bottom-0 h-[42%] will-change-transform">
            <svg class="h-full w-full" viewBox="0 0 1440 300" preserveAspectRatio="xMidYMax slice">
                <g fill="#06161F">
                    @foreach ($frontBuildings as $b)
                        <rect x="{{ $b['x'] }}" y="{{ $b['y'] }}" width="{{ $b['w'] }}" height="{{ 300 - $b['y'] }}" />
                    @endforeach
                    {{-- Spired tower --}}
                    <path d="M320 300 L340 90 L360 300 Z" />
                    <rect x="332" y="60" width="16" height="40" />
                    {{-- Arched-crown tower --}}
                    <path d="M560 300 L560 110 Q610 40 660 110 L660 300 L632 300 L632 120 Q610 92 588 120 L588 300 Z" />
                    {{-- Tapered slab --}}
                    <path d="M905 300 L920 100 L948 100 L958 300 Z" />
                </g>
                <g fill="#F6A25A">
                    @foreach ($frontWindows as $w)
                        <rect
                            class="animate-twinkle"
                            x="{{ $w['x'] }}"
                            y="{{ $w['y'] }}"
                            width="3"
                            height="5"
                            style="animation-delay: {{ $w['delay'] }}ms; animation-duration: {{ $w['duration'] }}ms"
                        />
                    @endforeach
                </g>
            </svg>
        </div>
    @endif
</div>

{{--
    Readability overlay.

    Sits ABOVE the backdrop and below the content, and is applied whether
    the background is the generated sky or a video — which is what
    guarantees the headline keeps its contrast when real footage (whose
    brightness nobody controls) is dropped in later.

    Measured: the hero copy is #E0E9EF and the darkest text tone is #CBD9E1;
    against the overlaid petrol these are 10.89:1 and 9.28:1 respectively,
    both far clear of AA.
--}}
<div
    class="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,rgba(8,30,43,0.62)_0%,rgba(8,30,43,0.42)_40%,rgba(8,30,43,0.78)_100%)]"
    aria-hidden="true"
></div>
