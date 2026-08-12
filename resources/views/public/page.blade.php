{{-- A seeded editorial page: about, our-process, privacy-policy,
     terms-and-conditions. Privacy and Terms deliberately share this one
     view — they are the same shape (a title and a body of prose), so a
     second template would be two files to keep in step for no gain. --}}
<x-layouts.public :seo-model="$page">
    <x-public.page-hero :title="$page->title" />

    <x-public.breadcrumbs :items="[
        __('site.nav.home') => lroute('home'),
        $page->title => null,
    ]" />

    <article class="section pt-8">
        <div class="wrap max-w-3xl">
            {{-- Admin-authored HTML, sanitised on WRITE against the narrow
                 'article' allow-list — so nothing unvetted is stored, let
                 alone rendered. --}}
            <div class="prose-brand">{!! $page->body !!}</div>
        </div>
    </article>

    @if ($page->sections->isNotEmpty())
        <section class="section pt-0">
            <div class="wrap">
                <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($page->sections as $section)
                        <li class="card flex h-full flex-col">
                            @if ($section->icon)
                                <span class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-petrol text-white" aria-hidden="true">
                                    <x-public.service-icon :name="$section->icon" />
                                </span>
                            @endif
                            <h2 class="text-lg font-extrabold">{{ $section->title }}</h2>
                            @if ($section->description)
                                <p class="mt-2 text-sm text-muted-ink">{{ $section->description }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <x-public.cta-band />
</x-layouts.public>
