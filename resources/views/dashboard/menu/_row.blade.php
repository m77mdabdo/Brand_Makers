@props(['item', 'index', 'child' => false, 'parentId' => null])

@php
    // The reorder form posts a flat list; parent_id travels with each row
    // so one submission can restructure the tree as well as reorder it.
    $position = $loop->parent->index ?? $index;
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 p-4">
    <div class="flex min-w-0 items-center gap-3">
        <input type="hidden" name="items[{{ $item->id }}][id]" value="{{ $item->id }}">
        <input type="hidden" name="items[{{ $item->id }}][parent_id]" value="{{ $parentId ?? '' }}">

        <label class="sr-only" for="order_{{ $item->id }}">{{ __('dashboard.fields.sort_order') }}</label>
        <input
            id="order_{{ $item->id }}"
            type="number"
            name="items[{{ $item->id }}][sort_order]"
            value="{{ $item->sort_order }}"
            min="0"
            dir="ltr"
            class="w-20 min-h-[44px] rounded-lg border-line bg-white text-center text-sm shadow-sm focus:border-petrol focus:ring-petrol"
        >

        <div class="min-w-0">
            <p class="truncate font-bold text-ink">
                @if ($child)<span class="text-muted-ink" aria-hidden="true">↳</span>@endif
                {{ $item->label }}
            </p>
            <p class="truncate text-xs text-muted-ink" dir="ltr">
                {{ $item->href() ?? __('dashboard.link_types.'.$item->link_type) }}
            </p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-1">
        @if ($item->is_system)
            <x-dashboard.badge tone="neutral">{{ __('dashboard.status.system') }}</x-dashboard.badge>
        @endif

        <x-dashboard.badge :tone="$item->is_visible ? 'success' : 'muted'">
            {{ $item->is_visible ? __('dashboard.status.visible') : __('dashboard.status.hidden') }}
        </x-dashboard.badge>

        {{-- formaction, so this button submits to the visibility endpoint
             from INSIDE the reorder form — nesting a second <form> here
             would be invalid HTML that browsers resolve by dropping one. --}}
        <button
            type="submit"
            form="visibility_{{ $item->id }}"
            class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream"
        >
            {{ $item->is_visible ? __('dashboard.actions.hide') : __('dashboard.actions.show') }}
        </button>

        <a href="{{ route('dashboard.menu.edit', $item) }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">
            {{ __('dashboard.actions.edit') }}
        </a>

        @if ($item->is_system)
            <span class="inline-flex min-h-[44px] items-center px-3 text-xs text-muted-ink">
                {{ __('dashboard.menu.system_protected') }}
            </span>
        @else
            <button
                type="submit"
                form="delete_{{ $item->id }}"
                onclick="return confirm({{ Js::from(__('dashboard.common.confirm_delete')) }})"
                class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-orange-ink transition hover:bg-orange-ink hover:text-white"
            >
                {{ __('dashboard.common.delete') }}
            </button>
        @endif
    </div>
</div>
