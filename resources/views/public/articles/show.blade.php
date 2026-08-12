{{-- og:type=article, not the site-wide "website": this is a dated
     editorial piece, and the distinction is what lets a crawler treat it
     as one. --}}
<x-layouts.public :seo-model="$article" seo-type="article">
    <x-public.page-hero :eyebrow="__('site.articles.eyebrow')" :title="$article->title">
        <p class="mt-4 text-sm text-mist-dim">
            <time datetime="{{ $article->published_at?->toDateString() }}">
                {{ $article->published_at?->translatedFormat('j F Y') }}
            </time>
        </p>
    </x-public.page-hero>

    <x-public.breadcrumbs :items="[
        __('site.nav.home') => lroute('home'),
        __('site.articles.label') => lroute('articles.index'),
        $article->title => null,
    ]" />

    <article class="section pt-8">
        <div class="wrap max-w-3xl">
            @if ($article->cover_image)
                <img src="{{ Storage::url($article->cover_image) }}" alt="" class="mb-8 w-full rounded-3xl border border-line object-cover">
            @endif

            {{-- Sanitised on write, never on render — see
                 HtmlSanitizerService. --}}
            <div class="prose-brand">{!! $article->body !!}</div>
        </div>
    </article>

    {{--
        Approved comments, oldest first. READ-ONLY for now: the submission
        form and its throttling are the next build step, and a form that
        posted nowhere would look functional while silently losing what
        people wrote.
    --}}
    @if ($comments->isNotEmpty())
        <section class="section pt-0" aria-labelledby="comments-heading">
            <div class="wrap max-w-3xl">
                <h2 id="comments-heading" class="text-2xl font-black">
                    {{ trans_choice('site.comments.heading', $comments->count(), ['count' => $comments->count()]) }}
                </h2>

                <ul class="mt-6 space-y-4">
                    @foreach ($comments as $comment)
                        <li class="rounded-2xl border border-line bg-white p-5">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="font-extrabold">{{ $comment->name }}</p>
                                <time class="text-xs text-muted-ink" datetime="{{ $comment->created_at->toDateString() }}">
                                    {{ $comment->created_at->translatedFormat('j F Y') }}
                                </time>
                            </div>
                            {{-- Plain text, escaped. A comment is
                                 visitor-submitted, so it is never rendered
                                 as HTML at any privilege level. --}}
                            <p class="mt-2 whitespace-pre-line text-sm text-muted-ink">{{ $comment->body }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($related->isNotEmpty())
        <section class="section pt-0">
            <div class="wrap">
                <h2 class="mb-6 text-2xl font-black">{{ __('site.articles.related') }}</h2>
                <ul class="grid gap-5 sm:grid-cols-3">
                    @foreach ($related as $other)
                        <li>
                            <a href="{{ lroute('articles.show', $other) }}" class="card flex h-full flex-col focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol">
                                <h3 class="font-extrabold">{{ $other->title }}</h3>
                                @if ($other->excerpt)
                                    <p class="mt-2 text-sm text-muted-ink">{{ $other->excerpt }}</p>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</x-layouts.public>
