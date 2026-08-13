<x-layouts.app :title="__('dashboard.resources.contact_messages')">
    <x-slot:header>
        <x-dashboard.page-header :title="__('dashboard.resources.contact_messages')" :subtitle="__('dashboard.leads.inbox_intro')" />
    </x-slot:header>

    <nav class="mb-6 flex flex-wrap gap-2" aria-label="{{ __('dashboard.fields.status') }}">
        @foreach (['all' => __('dashboard.common.all'), 'unhandled' => __('dashboard.status.pending'), 'handled' => __('dashboard.status.approved')] as $value => $label)
            @php $isCurrent = $filter === $value; @endphp
            <a
                href="{{ route('dashboard.contact-messages.index', $value === 'all' ? [] : ['filter' => $value]) }}"
                @if ($isCurrent) aria-current="page" @endif
                @class([
                    'inline-flex min-h-[44px] items-center gap-2 rounded-lg border px-4 text-sm font-bold transition',
                    'border-petrol bg-petrol text-white' => $isCurrent,
                    'border-line bg-white text-petrol hover:bg-cream' => ! $isCurrent,
                ])
            >
                {{ $label }}
                @if ($value === 'unhandled' && $unhandledCount > 0)
                    <span class="text-xs opacity-80" dir="ltr">{{ $unhandledCount }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    @if ($messages->isEmpty())
        <x-dashboard.empty-state :message="__('dashboard.leads.empty')" />
    @else
        <ul class="space-y-3">
            @foreach ($messages as $message)
                {{-- Unread rows carry a visible marker, not just a badge:
                     the inbox is a work queue and the eye should find the
                     unread ones without reading each badge. --}}
                <li @class([
                    'rounded-2xl border bg-white p-5',
                    'border-orange-ink/40 shadow-[0_0_0_1px_rgba(184,74,21,0.15)]' => ! $message->is_handled,
                    'border-line' => $message->is_handled,
                ])>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-extrabold text-ink">{{ $message->full_name }}</p>
                            <p class="text-xs text-muted-ink" dir="ltr">
                                {{ $message->email }}@if ($message->phone) · {{ $message->phone }}@endif
                            </p>
                            <p class="mt-1 text-xs text-muted-ink">{{ $message->created_at->diffForHumans() }}</p>
                        </div>

                        <x-dashboard.badge :tone="$message->is_handled ? 'muted' : 'warning'">
                            {{ $message->is_handled ? __('dashboard.status.approved') : __('dashboard.status.pending') }}
                        </x-dashboard.badge>
                    </div>

                    @if ($message->message)
                        <p class="mt-3 whitespace-pre-line text-sm text-muted-ink">{{ $message->message }}</p>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center gap-1">
                        <form method="POST" action="{{ route('dashboard.contact-messages.handled', $message) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">
                                {{ $message->is_handled ? __('dashboard.actions.mark_unhandled') : __('dashboard.actions.mark_handled') }}
                            </button>
                        </form>

                        <a href="{{ route('dashboard.leads.show', $message) }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">
                            {{ __('dashboard.actions.view') }}
                        </a>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $messages->links() }}</div>
    @endif
</x-layouts.app>
