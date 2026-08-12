<x-layouts.public :seo-model="$service">
    <x-public.page-hero :eyebrow="__('site.services.eyebrow')" :title="$service->name" :lead="$service->summary" />

    <x-public.breadcrumbs :items="[
        __('site.nav.home') => lroute('home'),
        __('site.services.label') => lroute('services.index'),
        $service->name => null,
    ]" />

    <article class="section pt-8">
        <div class="wrap grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @if ($service->cover_image)
                    <img src="{{ Storage::url($service->cover_image) }}" alt="" class="mb-8 w-full rounded-3xl border border-line object-cover">
                @endif

                {{-- Admin-authored HTML, sanitised on WRITE by
                     HtmlSanitizerService against the narrow 'article'
                     allow-list — so what reaches here has already had
                     script/style/iframe/on* removed rather than escaped. --}}
                <div class="prose-brand">{!! $service->body !!}</div>
            </div>

            <aside class="space-y-5">
                <div class="rounded-3xl border border-line bg-white p-6">
                    <h2 class="text-lg font-extrabold">{{ __('site.services.requirements') }}</h2>
                    <div class="prose-brand mt-2 text-sm">{!! $service->requirements !!}</div>
                </div>

                <div class="rounded-3xl border border-line bg-white p-6">
                    <h2 class="text-lg font-extrabold">{{ __('site.services.process') }}</h2>
                    <div class="prose-brand mt-2 text-sm">{!! $service->process !!}</div>
                </div>

                <a href="{{ lroute('contact') }}" class="btn btn-primary w-full">{{ __('site.services.enquire') }}</a>
            </aside>
        </div>
    </article>

    @if ($others->isNotEmpty())
        <section class="section pt-0">
            <div class="wrap">
                <h2 class="mb-6 text-2xl font-black">{{ __('site.services.other') }}</h2>
                <ul class="grid gap-5 sm:grid-cols-3">
                    @foreach ($others as $other)
                        <li>
                            <a href="{{ lroute('services.show', $other) }}" class="card flex h-full flex-col focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol">
                                <h3 class="font-extrabold">{{ $other->name }}</h3>
                                <p class="mt-2 text-sm text-muted-ink">{{ $other->summary }}</p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</x-layouts.public>
