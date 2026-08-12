<x-layouts.app :title="__('dashboard.resources.comments')">
    <x-slot:header>
        <x-dashboard.page-header :title="__('dashboard.resources.comments')" :subtitle="__('dashboard.comments.intro')" />
    </x-slot:header>

    {{-- Filters carry the count so the queue size is visible without
         clicking through each one. --}}
    <nav class="mb-6 flex flex-wrap gap-2" aria-label="{{ __('dashboard.fields.status') }}">
        @foreach ([null => __('dashboard.comments.filter_all')] + collect(App\Models\Comment::STATUSES)->mapWithKeys(fn ($s) => [$s => __('dashboard.status.'.$s)])->all() as $value => $label)
            @php $isCurrent = $status === ($value ?: null); @endphp
            <a
                href="{{ route('dashboard.comments.index', $value ? ['status' => $value] : []) }}"
                @if ($isCurrent) aria-current="page" @endif
                @class([
                    'inline-flex min-h-[44px] items-center gap-2 rounded-lg border px-4 text-sm font-bold transition',
                    'border-petrol bg-petrol text-white' => $isCurrent,
                    'border-line bg-white text-petrol hover:bg-cream' => ! $isCurrent,
                ])
            >
                {{ $label }}
                <span class="text-xs opacity-80" dir="ltr">{{ $counts[$value ?: 'all'] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    @if ($comments->isEmpty())
        <x-dashboard.empty-state :message="__('dashboard.comments.empty')" />
    @else
        <ul class="space-y-3">
            @foreach ($comments as $comment)
                <li class="rounded-2xl border border-line bg-white p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-bold text-ink">
                                {{ $comment->name }}
                                <span class="text-xs font-normal text-muted-ink" dir="ltr">{{ $comment->email }}</span>
                            </p>
                            <p class="text-xs text-muted-ink">
                                {{ __('dashboard.comments.on_article') }}
                                @if ($comment->article)
                                    <a href="{{ route('dashboard.articles.edit', $comment->article) }}" class="underline">{{ $comment->article->title }}</a>
                                @else
                                    {{ __('dashboard.common.none') }}
                                @endif
                                · {{ $comment->created_at->diffForHumans() }}
                            </p>
                        </div>

                        <x-dashboard.badge :tone="match ($comment->status) {
                            App\Models\Comment::STATUS_APPROVED => 'success',
                            App\Models\Comment::STATUS_REJECTED => 'muted',
                            default => 'warning',
                        }">
                            {{ __('dashboard.status.'.$comment->status) }}
                        </x-dashboard.badge>
                    </div>

                    <p class="mt-3 whitespace-pre-line text-sm text-muted-ink">{{ $comment->body }}</p>

                    <div class="mt-4 flex flex-wrap items-center gap-1">
                        @if ($comment->status !== App\Models\Comment::STATUS_APPROVED)
                            <form method="POST" action="{{ route('dashboard.comments.approve', $comment) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">
                                    {{ __('dashboard.actions.approve') }}
                                </button>
                            </form>
                        @endif

                        @if ($comment->status !== App\Models\Comment::STATUS_REJECTED)
                            <form method="POST" action="{{ route('dashboard.comments.reject', $comment) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-muted-ink transition hover:bg-cream">
                                    {{ __('dashboard.actions.reject') }}
                                </button>
                            </form>
                        @endif

                        <x-dashboard.delete-button :action="route('dashboard.comments.destroy', $comment)" />
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $comments->links() }}</div>
    @endif
</x-layouts.app>
