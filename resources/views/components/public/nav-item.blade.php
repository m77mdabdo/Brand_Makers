@props(['item', 'mobile' => false])

@php
    $href = $item->href();
    $isActive = $item->route_name !== null && $item->route_name === current_route_base_name();
@endphp

{{--
    A menu row whose destination cannot be resolved is DROPPED, not
    rendered as a dead link.

    MenuItem::href() returns null in exactly two cases: the item is a
    dropdown parent with no destination of its own (handled below, it
    still renders because its children are the destination), or its route
    is whitelisted but not yet registered. The second is the normal state
    while the site is being built out one section at a time — a nav entry
    for a page that does not exist yet should be invisible, not a link
    that 404s.
--}}
@if ($href === null && ! $item->hasVisibleChildren())
    @php return; @endphp
@endif

@if ($item->hasVisibleChildren())
    <div
        x-data="{ open: false }"
        @if (! $mobile)
            @mouseenter="open = true"
            @mouseleave="open = false"
        @endif
        @keydown.escape="open = false"
        class="{{ $mobile ? '' : 'relative' }}"
    >
        <button
            type="button"
            @click="open = !open"
            :aria-expanded="open ? 'true' : 'false'"
            class="flex w-full items-center justify-between gap-1.5 rounded-lg py-2.5 text-start transition hover:text-orange-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol {{ $mobile ? 'px-3' : '' }} {{ $isActive ? 'text-orange-ink' : 'text-petrol' }}"
        >
            {{ $item->label }}
            <svg class="h-4 w-4 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M6 9l6 6 6-6" stroke-linecap="round" />
            </svg>
        </button>

        <div
            x-show="open"
            x-cloak
            x-transition
            class="{{ $mobile ? 'ps-4' : 'absolute start-0 top-full z-50 min-w-[13rem] rounded-2xl border border-line bg-white p-2 shadow-xl' }}"
        >
            @foreach ($item->visibleChildren as $child)
                @if ($child->href())
                    <a
                        href="{{ $child->href() }}"
                        @if ($child->linkRel()) rel="{{ $child->linkRel() }}" @endif
                        target="{{ $child->target }}"
                        class="block rounded-lg px-3 py-2 text-sm text-petrol transition hover:bg-cream hover:text-orange-ink"
                    >
                        {{ $child->label }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>
@else
    <a
        href="{{ $href }}"
        @if ($item->linkRel()) rel="{{ $item->linkRel() }}" @endif
        target="{{ $item->target }}"
        @if ($isActive) aria-current="page" @endif
        class="block rounded-lg transition hover:text-orange-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol {{ $mobile ? 'px-3 py-2.5' : 'py-2' }} {{ $isActive ? 'font-bold text-orange-ink' : 'text-petrol' }}"
    >
        {{ $item->label }}
    </a>
@endif
