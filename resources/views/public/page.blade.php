{{-- A seeded editorial page (about, our-process, privacy, terms). --}}
<x-layouts.public :seo-model="$page" seo-type="article">
    <article class="section">
        <div class="wrap max-w-3xl">
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl lg:text-5xl">{{ $page->title }}</h1>

            {{-- Page bodies are admin-authored HTML from the dashboard, so
                 they are rendered unescaped by necessity. The sanitising
                 pass belongs on the WRITE side (the editor), which is the
                 next build step — flagged here so it is not forgotten. --}}
            <div class="prose-brand mt-8">
                {!! $page->body !!}
            </div>

            @if ($page->sections->isNotEmpty())
                <ul class="mt-12 grid gap-5 sm:grid-cols-2">
                    @foreach ($page->sections as $section)
                        <li class="card">
                            @if ($section->icon)
                                <span class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-petrol text-white" aria-hidden="true">
                                    <x-public.service-icon :name="$section->icon" />
                                </span>
                            @endif
                            <h2 class="text-lg font-extrabold">{{ $section->title }}</h2>
                            <p class="mt-2 text-sm text-muted-ink">{{ $section->description }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </article>
</x-layouts.public>
