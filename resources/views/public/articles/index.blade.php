<x-layouts.public :seo-title="__('site.articles.label')" :seo-description="__('site.articles.lead')">
    <x-public.page-hero
        :eyebrow="__('site.articles.eyebrow')"
        :title="__('site.articles.title')"
        :lead="__('site.articles.lead')"
    />

    <section class="section">
        <div class="wrap">
            @if ($articles->isEmpty())
                <p class="text-center text-muted-ink">{{ __('site.articles.empty') }}</p>
            @else
                <ul class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($articles as $article)
                        <li>
                            <a href="{{ lroute('articles.show', $article) }}" class="card flex h-full flex-col focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol">
                                @if ($article->cover_image)
                                    <img src="{{ Storage::url($article->cover_image) }}" alt="" loading="lazy" class="mb-4 h-40 w-full rounded-2xl object-cover">
                                @endif

                                <time class="text-xs font-bold uppercase tracking-wide text-muted-ink" datetime="{{ $article->published_at?->toDateString() }}">
                                    {{ $article->published_at?->translatedFormat('j F Y') }}
                                </time>

                                <h2 class="mt-2 text-lg font-extrabold">{{ $article->title }}</h2>

                                @if ($article->excerpt)
                                    <p class="mt-2 flex-1 text-sm text-muted-ink">{{ $article->excerpt }}</p>
                                @endif

                                <span class="mt-4 text-sm font-bold text-orange-ink">{{ __('site.articles.read_more') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-10">{{ $articles->links() }}</div>
            @endif
        </div>
    </section>
</x-layouts.public>
