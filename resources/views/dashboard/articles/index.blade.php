<x-layouts.app :title="__('dashboard.resources.articles')">
    <x-slot:header>
        <x-dashboard.page-header :title="__('dashboard.resources.articles')">
            <x-slot:actions>
                <a href="{{ route('dashboard.articles.create') }}" class="btn btn-primary !px-5 !py-2.5 !text-sm">{{ __('dashboard.actions.create') }}</a>
            </x-slot:actions>
        </x-dashboard.page-header>
    </x-slot:header>

    @if ($articles->isEmpty())
        <x-dashboard.empty-state :message="__('dashboard.common.none')" :action="route('dashboard.articles.create')" :action-label="__('dashboard.actions.create')" />
    @else
        <div class="overflow-x-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[42rem] text-sm">
                <thead class="border-b border-line bg-cream/60">
                    <tr>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.title') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.status') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.resources.comments') }}</th>
                        <th class="px-4 py-3 text-end font-extrabold">{{ __('dashboard.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($articles as $article)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="font-bold text-ink">{{ $article->title }}</span>
                                <span class="block text-xs text-muted-ink" dir="ltr">{{ $article->slug }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    // The three states are derived, not stored — see
                                    // ArticleContentService.
                                    $scheduled = $article->is_published && $article->published_at?->isFuture();
                                @endphp
                                @if (! $article->is_published)
                                    <x-dashboard.badge tone="muted">{{ __('dashboard.status.draft') }}</x-dashboard.badge>
                                @elseif ($scheduled)
                                    <x-dashboard.badge tone="warning">{{ __('dashboard.status.scheduled') }}</x-dashboard.badge>
                                @else
                                    <x-dashboard.badge tone="success">{{ __('dashboard.status.published') }}</x-dashboard.badge>
                                @endif
                                @if ($article->published_at)
                                    <span class="mt-1 block text-xs text-muted-ink">{{ $article->published_at->translatedFormat('j M Y H:i') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('dashboard.comments.index', ['status' => 'pending']) }}" class="text-petrol underline">
                                    {{ $article->comments_count }}
                                </a>
                                @if ($article->pending_comments_count)
                                    <x-dashboard.badge tone="warning">{{ $article->pending_comments_count }}</x-dashboard.badge>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('dashboard.articles.edit', $article) }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">{{ __('dashboard.actions.edit') }}</a>
                                    <x-dashboard.delete-button :action="route('dashboard.articles.destroy', $article)" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $articles->links() }}</div>
    @endif
</x-layouts.app>
