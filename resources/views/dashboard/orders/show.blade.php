<x-layouts.app :title="$order->reference">
    <x-slot:header>
        <x-dashboard.page-header
            :title="$order->reference"
            :subtitle="$order->client_name"
            :back="route('dashboard.orders.index')"
        >
            <x-slot:actions>
                <a href="{{ route('dashboard.orders.edit', $order) }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-line px-4 text-sm font-bold text-petrol transition hover:bg-cream">
                    {{ __('dashboard.actions.edit') }}
                </a>
            </x-slot:actions>
        </x-dashboard.page-header>
    </x-slot:header>

    @if ($issuedCode)
        {{--
            THE ONE TIME THIS CODE IS READABLE.

            Only its SHA-256 is stored, so there is no screen anywhere that
            can show it again — which is the point, and why this banner
            says so rather than looking like an ordinary confirmation.
            Flashed, so a refresh does not leave a working credential
            sitting on screen.
        --}}
        <div role="status" class="mb-6 rounded-2xl border-2 border-orange-ink bg-white p-6">
            <h2 class="text-lg font-black text-ink">{{ __('dashboard.orders.code_heading') }}</h2>
            <p class="mt-2 text-sm text-muted-ink">{{ __('dashboard.orders.code_shown_once') }}</p>

            {{-- Selectable in one gesture, and wrapping rather than
                 overflowing: this is 64 characters somebody has to copy. --}}
            <p class="mt-4 select-all break-all rounded-xl bg-cream p-4 font-mono text-sm text-ink" dir="ltr">{{ $issuedCode }}</p>
        </div>
    @endif

    <div class="grid max-w-5xl gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- ---------------------------------------------------------
                 Status
            --------------------------------------------------------- --}}
            <x-dashboard.card :title="__('dashboard.fields.status')">
                <div class="flex flex-wrap items-center gap-3">
                    <x-dashboard.badge :tone="match ($order->status) {
                        App\Models\Order::STATUS_COMPLETED => 'success',
                        App\Models\Order::STATUS_CANCELLED => 'muted',
                        App\Models\Order::STATUS_PENDING => 'warning',
                        default => 'neutral',
                    }">
                        {{ __('dashboard.order_statuses.'.$order->status) }}
                    </x-dashboard.badge>
                    <span class="text-sm tabular-nums text-muted-ink" dir="ltr">{{ $order->progress }}%</span>
                </div>

                {{-- One form per allowed move, each carrying an optional
                     note that lands on the client's timeline with the
                     change. Only the moves this order can make are
                     offered. --}}
                <div class="space-y-3">
                    @foreach ($allowedTransitions as $status)
                        <form method="POST" action="{{ route('dashboard.orders.status', $order) }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-line p-4">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $status }}">

                            <div class="min-w-0 flex-1">
                                <label for="note-{{ $status }}" class="block text-xs font-bold text-muted-ink">
                                    {{ __('dashboard.orders.transition_note') }}
                                </label>
                                <input
                                    id="note-{{ $status }}"
                                    name="note[ar]"
                                    type="text"
                                    class="mt-1 block min-h-[44px] w-full rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
                                >
                            </div>

                            <button type="submit" @class([
                                'inline-flex min-h-[44px] shrink-0 items-center rounded-lg border px-4 text-sm font-bold transition',
                                'border-petrol bg-petrol text-white hover:bg-petrol-deep' => $status !== App\Models\Order::STATUS_CANCELLED,
                                'border-line text-petrol hover:bg-cream' => $status === App\Models\Order::STATUS_CANCELLED,
                            ])>
                                {{ __('dashboard.orders.move_to', ['status' => __('dashboard.order_statuses.'.$status)]) }}
                            </button>
                        </form>
                    @endforeach
                </div>

                <p class="text-xs text-muted-ink">{{ __('dashboard.orders.terminal_note') }}</p>

                <form method="POST" action="{{ route('dashboard.orders.progress', $order) }}" class="flex flex-wrap items-end gap-3 border-t border-line pt-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="progress" class="block text-xs font-bold text-muted-ink">{{ __('dashboard.orders.progress') }}</label>
                        <input
                            id="progress"
                            name="progress"
                            type="number" min="0" max="100"
                            dir="ltr"
                            value="{{ $order->progress }}"
                            class="mt-1 block min-h-[44px] w-28 rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
                        >
                    </div>
                    <button type="submit" class="inline-flex min-h-[44px] items-center rounded-lg border border-line px-4 text-sm font-bold text-petrol transition hover:bg-cream">
                        {{ __('dashboard.orders.update_progress') }}
                    </button>
                </form>
            </x-dashboard.card>

            {{-- ---------------------------------------------------------
                 Timeline
            --------------------------------------------------------- --}}
            <x-dashboard.card :title="__('dashboard.orders.timeline')" :subtitle="__('dashboard.orders.timeline_help')">
                <form method="POST" action="{{ route('dashboard.orders.events.store', $order) }}" class="grid gap-4 rounded-xl border border-line p-4 sm:grid-cols-2">
                    @csrf
                    <x-dashboard.field name="note.ar" :label="__('dashboard.orders.note').' ('.__('dashboard.common.arabic').')'" required />
                    <x-dashboard.field name="note.en" :label="__('dashboard.orders.note').' ('.__('dashboard.common.english').')'" />
                    <x-dashboard.field name="occurred_at" type="date" dir="ltr" :label="__('dashboard.orders.occurred_at')" />

                    <div class="sm:col-span-2">
                        <button type="submit" class="btn btn-primary !px-5 !py-2.5 !text-sm">{{ __('dashboard.orders.add_event') }}</button>
                    </div>
                </form>

                @if ($order->events->isEmpty())
                    <p class="text-sm text-muted-ink">{{ __('dashboard.orders.no_events') }}</p>
                @else
                    <ol class="space-y-3">
                        @foreach ($order->events as $event)
                            <li class="flex flex-wrap items-start gap-3 border-s-2 border-line ps-4">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold uppercase tracking-wide text-muted-ink">
                                        <time datetime="{{ $event->occurred_at->toIso8601String() }}">{{ $event->occurred_at->translatedFormat('j M Y') }}</time>
                                        · {{ __('dashboard.order_statuses.'.$event->status) }}
                                    </p>
                                    {{-- Escaped. Staff-written, but stored
                                         as plain text and rendered to a
                                         client on the portal. --}}
                                    <p class="mt-1 whitespace-pre-line text-sm text-ink">{{ $event->note ?: __('dashboard.common.none') }}</p>
                                </div>

                                <x-dashboard.delete-button :action="route('dashboard.orders.events.destroy', [$order, $event])" />
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-dashboard.card>
        </div>

        <div class="space-y-6">
            <x-dashboard.card :title="__('dashboard.orders.client')">
                <dl class="space-y-3 text-sm">
                    @foreach ([
                        'client_name' => $order->client_name,
                        'client_email' => $order->client_email,
                        'client_phone' => $order->client_phone,
                    ] as $key => $value)
                        @if ($value)
                            <div class="flex flex-wrap justify-between gap-2">
                                <dt class="text-muted-ink">{{ __('dashboard.orders.'.$key) }}</dt>
                                <dd class="min-w-0 break-all text-end font-bold text-ink" @if ($key !== 'client_name') dir="ltr" @endif>{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach

                    <div class="flex flex-wrap justify-between gap-2 border-t border-line pt-3">
                        <dt class="text-muted-ink">{{ __('dashboard.resources.packages') }}</dt>
                        <dd class="text-end font-bold text-ink">{{ $order->package?->name ?? __('dashboard.common.none') }}</dd>
                    </div>

                    @if ($order->total !== null)
                        <div class="flex flex-wrap justify-between gap-2">
                            <dt class="text-muted-ink">{{ __('dashboard.orders.total') }}</dt>
                            <dd class="text-end font-bold tabular-nums text-ink" dir="ltr">{{ number_format((float) $order->total, 2) }} {{ $order->currency }}</dd>
                        </div>
                    @endif

                    <div class="flex flex-wrap justify-between gap-2">
                        <dt class="text-muted-ink">{{ __('dashboard.orders.placed_at') }}</dt>
                        <dd class="text-end text-ink">{{ $order->placed_at->translatedFormat('j F Y') }}</dd>
                    </div>
                </dl>
            </x-dashboard.card>

            @if ($order->lead)
                <x-dashboard.card :title="__('dashboard.orders.linked_lead')">
                    <p><x-dashboard.badge tone="neutral">{{ __('dashboard.lead_types.'.$order->lead->type) }}</x-dashboard.badge></p>
                    <a href="{{ route('dashboard.leads.show', $order->lead) }}" class="inline-flex min-h-[44px] items-center text-sm font-bold text-petrol underline underline-offset-2 hover:text-orange-ink">
                        {{ __('dashboard.orders.view_lead') }}
                    </a>
                </x-dashboard.card>
            @endif

            <x-dashboard.card :title="__('dashboard.orders.code_heading')">
                {{-- No code is displayed here, ever. Only the digest is
                     stored, so there is nothing to show — and saying that
                     plainly is what stops somebody looking for a screen
                     that reveals it. --}}
                <p class="text-sm text-muted-ink">{{ __('dashboard.orders.code_stored_hint') }}</p>

                <form method="POST" action="{{ route('dashboard.orders.code', $order) }}"
                    onsubmit="return confirm('{{ __('dashboard.orders.code_reissue_confirm') }}')">
                    @csrf
                    <button type="submit" class="inline-flex min-h-[44px] items-center rounded-lg border border-line px-4 text-sm font-bold text-petrol transition hover:bg-cream">
                        {{ __('dashboard.orders.code_reissue') }}
                    </button>
                </form>
            </x-dashboard.card>
        </div>
    </div>
</x-layouts.app>
