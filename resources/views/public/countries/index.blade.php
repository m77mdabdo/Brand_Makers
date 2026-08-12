<x-layouts.public :seo-title="__('site.countries.label')" :seo-description="__('site.countries.lead')">
    <x-public.page-hero
        :eyebrow="__('site.countries.eyebrow')"
        :title="__('site.countries.title')"
        :lead="__('site.countries.lead')"
    />

    <section class="section">
        <div class="wrap">
            @if ($countries->isEmpty())
                <p class="text-center text-muted-ink">{{ __('site.countries.empty') }}</p>
            @else
                <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($countries as $country)
                        <li class="card flex h-full flex-col">
                            <div class="flex items-center gap-3">
                                @if ($country->flag_image)
                                    <img src="{{ Storage::url($country->flag_image) }}" alt="" class="h-8 w-12 rounded border border-line object-cover">
                                @endif
                                <h2 class="text-lg font-extrabold">{{ $country->name }}</h2>
                            </div>

                            @if ($country->notes)
                                <div class="prose-brand mt-3 flex-1 text-sm">{!! $country->notes !!}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <x-public.cta-band />
</x-layouts.public>
