@props(['title', 'subtitle' => null, 'back' => null])

<div class="flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <h1 class="text-2xl font-black tracking-tight text-ink">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 max-w-2xl text-sm text-muted-ink">{{ $subtitle }}</p>
        @endif
    </div>

    <div class="flex shrink-0 flex-wrap items-center gap-2">
        {{ $actions ?? '' }}

        @if ($back)
            <a href="{{ $back }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-line px-4 text-sm font-bold text-petrol transition hover:bg-cream focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol">
                {{ __('dashboard.common.back') }}
            </a>
        @endif
    </div>
</div>
