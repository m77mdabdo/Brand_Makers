<x-layouts.app :title="__('dashboard.resources.menu')">
    <x-slot:header>
        <x-dashboard.page-header :title="__('dashboard.resources.menu')" :subtitle="__('dashboard.menu.intro')">
            <x-slot:actions>
                <a href="{{ route('dashboard.menu.create') }}" class="btn btn-primary !px-5 !py-2.5 !text-sm">{{ __('dashboard.actions.create') }}</a>
            </x-slot:actions>
        </x-dashboard.page-header>
    </x-slot:header>

    @if ($items->isEmpty())
        {{-- The empty-menu fallback. The public navbar already guards this
             at render time, but an empty editor with no way forward is its
             own dead end. --}}
        <x-dashboard.empty-state :message="__('dashboard.common.none')" :action="route('dashboard.menu.create')" :action-label="__('dashboard.actions.create')" />
    @else
        {{-- Reorder is a plain form of number inputs, not a drag-and-drop
             widget: it works with a keyboard, on a touch screen, and with
             JavaScript disabled, and the whole tree is written in one
             transaction. --}}
        <form method="POST" action="{{ route('dashboard.menu.reorder') }}">
            @csrf

            <p class="mb-4 text-sm text-muted-ink">{{ __('dashboard.menu.reorder_help') }}</p>

            <ul class="space-y-3">
                @foreach ($items as $index => $item)
                    <li class="rounded-2xl border border-line bg-white">
                        @include('dashboard.menu._row', ['item' => $item, 'index' => $index, 'child' => false])

                        @if ($item->children->isNotEmpty())
                            <ul class="border-t border-line bg-cream/40 px-4 py-2">
                                @foreach ($item->children as $child)
                                    <li class="border-b border-line/60 last:border-0">
                                        @include('dashboard.menu._row', ['item' => $child, 'index' => $loop->index, 'child' => true, 'parentId' => $item->id])
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="btn btn-primary">{{ __('dashboard.actions.save_order') }}</button>
            </div>
        </form>

        {{--
            The per-item visibility and delete forms live OUTSIDE the
            reorder form and are reached by their id via the buttons'
            `form` attribute.

            Nesting them inside would be invalid HTML — browsers resolve a
            nested <form> by dropping one of them, which silently breaks
            whichever loses. This keeps three independent actions on one
            screen with no JavaScript at all.
        --}}
        @foreach ($items->flatMap(fn ($item) => [$item, ...$item->children]) as $item)
            <form id="visibility_{{ $item->id }}" method="POST" action="{{ route('dashboard.menu.visibility', $item) }}" class="hidden">
                @csrf
                @method('PATCH')
            </form>

            @unless ($item->is_system)
                <form id="delete_{{ $item->id }}" method="POST" action="{{ route('dashboard.menu.destroy', $item) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endunless
        @endforeach
    @endif
</x-layouts.app>
