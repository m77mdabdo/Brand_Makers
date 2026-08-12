<x-layouts.public :seo-title="__('site.faq.label')" :seo-description="__('site.faq.lead')">
    <x-public.page-hero
        :eyebrow="__('site.faq.eyebrow')"
        :title="__('site.faq.title')"
        :lead="__('site.faq.lead')"
    />

    <section class="section">
        <div class="wrap max-w-3xl">
            @if ($faqs->isEmpty())
                <p class="text-center text-muted-ink">{{ __('site.faq.empty') }}</p>
            @else
                {{-- Native <details>, not an Alpine accordion: keyboard
                     accessible, announced correctly, and fully functional
                     with JavaScript disabled, for no code. --}}
                <ul class="space-y-3">
                    @foreach ($faqs as $faq)
                        <li>
                            <details class="group rounded-2xl border border-line bg-white px-5 py-4 [&_summary::-webkit-details-marker]:hidden">
                                <summary class="flex cursor-pointer items-center justify-between gap-4 font-extrabold focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol">
                                    {{ $faq->question }}
                                    <svg class="h-5 w-5 shrink-0 text-orange-ink transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M6 9l6 6 6-6" stroke-linecap="round" />
                                    </svg>
                                </summary>
                                <div class="prose-brand mt-3 text-sm">{!! $faq->answer !!}</div>
                            </details>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <x-public.cta-band />
</x-layouts.public>
