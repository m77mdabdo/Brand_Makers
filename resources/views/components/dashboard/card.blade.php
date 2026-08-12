@props(['title' => null, 'subtitle' => null])

<section class="rounded-2xl border border-line bg-white p-6">
    @if ($title)
        <h2 class="text-lg font-extrabold text-ink">{{ $title }}</h2>
    @endif
    @if ($subtitle)
        <p class="mt-1 text-sm text-muted-ink">{{ $subtitle }}</p>
    @endif

    <div @class(['space-y-5', 'mt-5' => $title])>
        {{ $slot }}
    </div>
</section>
