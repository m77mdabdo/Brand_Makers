@props(['item', 'mobile' => false])

@php
    $href = $item->href();
    $children = $item->visibleChildren;
    $isActive = $item->isActive();
@endphp

{{--
    A row whose destination cannot be resolved is DROPPED, not rendered as
    a dead link.

    href() returns null in exactly two cases: a dropdown parent with no
    destination of its own (handled below — its children ARE the
    destination), or a whitelisted route that is not registered yet. The
    second is the normal state while the site is built out one section at
    a time, and a nav entry for a page that does not exist should be
    invisible rather than a link that 404s.
--}}
@if ($href === null && $children->isEmpty())
    @php return; @endphp
@endif

@if ($children->isNotEmpty())
    {{--
        Dropdown. Opens on hover AND on click/keyboard, closes on Escape or
        when focus leaves the group — so it is fully usable without a
        mouse, which a hover-only menu is not.

        focusout checks $event.relatedTarget is outside $el, because
        tabbing BETWEEN two children fires focusout on the first one and
        would otherwise snap the panel shut mid-navigation.
    --}}
    <div
        class="{{ $mobile ? '' : 'relative' }}"
        x-data="{ open: false }"
        @if (! $mobile)
            @mouseenter="open = true"
            @mouseleave="open = false"
        @endif
        @focusin="open = true"
        @focusout="if (! $el.contains($event.relatedTarget)) open = false"
        @keydown.escape.stop="open = false"
    >
        <button
            type="button"
            @click="open = ! open"
            :aria-expanded="open.toString()"
            aria-haspopup="true"
            @class([
                'flex min-h-[44px] w-full items-center justify-between gap-1.5 rounded-lg transition hover:text-orange-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol',
                'px-3 text-start' => $mobile,
                'font-bold text-orange-ink' => $isActive,
                'text-petrol' => ! $isActive,
            ])
        >
            {{ $item->label }}
            <svg class="h-4 w-4 shrink-0 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M6 9l6 6 6-6" stroke-linecap="round" />
            </svg>
        </button>

        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="{{ $mobile ? 'ps-3' : 'absolute start-0 top-full z-50 mt-2 w-64 rounded-2xl border border-line bg-white p-2 shadow-xl' }}"
        >
            @foreach ($children as $child)
                @continue ($child->href() === null)

                <a
                    href="{{ $child->href() }}"
                    target="{{ $child->target }}"
                    @if ($child->linkRel()) rel="{{ $child->linkRel() }}" @endif
                    @if ($child->isActive()) aria-current="page" @endif
                    @class([
                        'flex min-h-[44px] items-center rounded-lg px-3 text-sm transition hover:bg-cream hover:text-orange-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol',
                        'font-bold text-orange-ink' => $child->isActive(),
                        'text-petrol' => ! $child->isActive(),
                    ])
                >
                    {{ $child->label }}
                </a>
            @endforeach
        </div>
    </div>
@else
    <a
        href="{{ $href }}"
        target="{{ $item->target }}"
        {{-- rel="noopener" on every _blank link: without it the opened
             page can reach back through window.opener and navigate this
             one. linkRel() returns null for _self, so nothing is emitted
             where it would be noise. --}}
        @if ($item->linkRel()) rel="{{ $item->linkRel() }}" @endif
        @if ($isActive) aria-current="page" @endif
        @class([
            'flex min-h-[44px] items-center rounded-lg transition hover:text-orange-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol',
            'px-3' => $mobile,
            'font-bold text-orange-ink' => $isActive,
            'text-petrol' => ! $isActive,
        ])
    >
        {{ $item->label }}
    </a>
@endif
