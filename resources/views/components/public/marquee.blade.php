@props([
    // A list of plain strings.
    'items',
    // 'base' moves the way the locale reads; 'reverse' moves against it.
    // Alternating these down the page is what makes adjacent rows shear
    // past each other — and the whole pattern flips when the locale does,
    // so the effect is identical in Arabic and English rather than
    // mirrored into nonsense.
    'direction' => 'base',
    'tone' => 'orange',
])

@php
    $items = collect($items)->filter()->values();

    $readingDir = app()->getLocale() === 'ar' ? 'rtl' : 'ltr';
    $flip = ['rtl' => 'ltr', 'ltr' => 'rtl'];
    $flow = $direction === 'reverse' ? $flip[$readingDir] : $readingDir;

    /*
     * One "unit" is the run of items the animation translates by. The
     * track holds exactly TWO units and moves by 50% of its own width, so
     * the frame at 100% is pixel-identical to the frame at 0% and the loop
     * has no seam.
     *
     * A unit must be WIDER than the viewport or the far edge would show
     * empty space for part of every cycle, so a short set repeats until it
     * clears 1600px (comfortably past the 1200px wrap).
     */
    $approxItemWidth = 240;
    $minPerUnit = (int) ceil(1600 / $approxItemWidth);
    $perUnit = max(1, (int) ceil($minPerUnit / max(1, $items->count())));
    $unitCount = $perUnit * max(1, $items->count());

    // A constant 55px/s regardless of how many items exist, so two
    // marquees on one page never read as unrelated widgets running at
    // random rates.
    $duration = max(18, (int) round($unitCount * $approxItemWidth / 55));

    $toneClasses = $tone === 'petrol'
        ? 'bg-petrol text-white'
        : 'bg-orange-ink text-white';
@endphp

{{--
    The Alpine here is three attributes and exists for one reason: a touch
    screen has no :hover to pause with, so a swipe would compose with a
    still-running transform. Pointer and keyboard users are handled by
    :hover / :focus-within in CSS, so the marquee stays pausable with
    JavaScript disabled.

    role="marquee" is deliberately NOT used — it is not a live region, it
    is decoration repeating text that appears elsewhere on the page, so it
    is hidden from assistive tech entirely and given a plain label.
--}}
<div
    class="cm-marquee {{ $toneClasses }} py-3.5"
    data-marquee-direction="{{ $flow }}"
    x-data="{ held: false }"
    :data-paused="held ? 'true' : 'false'"
    @touchstart.passive="held = true"
    @touchend.window="held = false"
    @touchcancel.window="held = false"
    aria-hidden="true"
>
    <div class="cm-marquee__track" style="--cm-marquee-duration: {{ $duration }}s">
        @foreach (range(0, 1) as $half)
            @foreach (range(0, $perUnit - 1) as $pass)
                @foreach ($items as $item)
                    {{-- The track is pinned to ltr (see app.css), so each
                         item restates the reading direction for its own
                         text. --}}
                    <span dir="{{ $readingDir }}" class="flex shrink-0 items-center gap-6 whitespace-nowrap px-6 text-base font-extrabold">
                        {{ $item }}
                        <span class="opacity-60" aria-hidden="true">&bull;</span>
                    </span>
                @endforeach
            @endforeach
        @endforeach
    </div>
</div>
