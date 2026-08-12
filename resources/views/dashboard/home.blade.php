<x-layouts.app :title="__('dashboard.home.heading')">
    <x-slot:header>
        <h1 class="text-2xl font-black tracking-tight text-ink">{{ __('dashboard.home.heading') }}</h1>
        <p class="mt-1 text-sm text-muted-ink">{{ __('dashboard.home.intro') }}</p>
    </x-slot:header>

    @if ($unhandledLeads > 0)
        <div class="mb-6 flex items-center gap-3 rounded-xl border border-orange-ink/30 bg-white px-4 py-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-orange-ink text-sm font-black text-white">
                {{ $unhandledLeads }}
            </span>
            <span class="text-sm font-bold text-ink">{{ __('dashboard.home.unhandled_leads') }}</span>
        </div>
    @endif

    {{-- Real counts, never illustrative figures — an empty install shows
         zeros rather than sample numbers. --}}
    <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($stats as $key => $count)
            <li class="rounded-2xl border border-line bg-white p-5">
                <p class="text-sm font-bold text-muted-ink">{{ __('dashboard.home.stats.'.$key) }}</p>
                <p class="mt-1 text-3xl font-black text-petrol" dir="ltr">{{ number_format($count) }}</p>
            </li>
        @endforeach
    </ul>
</x-layouts.app>
