@props(['name' => null])

{{--
    Maps a service's `icon` string to an inline SVG.

    Inline rather than an icon font or sprite sheet: a dozen icons is far
    less bytes than either, they inherit currentColor for free, and there
    is no extra request on the critical path.

    An unknown or missing name falls back to a neutral mark rather than
    rendering nothing — a card with an empty icon well looks broken, and
    the icon carries no information the heading does not already give
    (the wrapper is aria-hidden). That fallback is also what makes this
    set safe to change: an icon name that disappears degrades to the
    neutral mark instead of breaking the card.

    THE SET IS THE BUSINESS. These name company formation and investment
    work — a licence, a registration, a bank, a ministry counter. The
    previous set (palette, film, megaphone, sparkles) named a design
    studio's deliverables and described nothing this company does.
--}}
@php
    $paths = [
        // Formation and legal entity
        'building' => ['<path d="M4 21V6a1 1 0 011-1h8a1 1 0 011 1v15"/>', '<path d="M14 10h5a1 1 0 011 1v10"/>', '<path d="M7 9h4M7 13h4M7 17h4"/>', '<path d="M3 21h18"/>'],
        'document' => ['<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/>', '<path d="M14 3v5h5"/>', '<path d="M9 13h6M9 17h4"/>'],
        'stamp' => ['<circle cx="12" cy="8.5" r="4.5"/>', '<path d="M8.5 13L7.5 21h9l-1-8"/>', '<path d="M5 21h14"/>'],
        // Investment and cross-border
        'globe' => ['<circle cx="12" cy="12" r="9"/>', '<path d="M3 12h18"/>', '<path d="M12 3c2.5 2.4 3.8 5.5 3.8 9s-1.3 6.6-3.8 9c-2.5-2.4-3.8-5.5-3.8-9S9.5 5.4 12 3z"/>'],
        'exchange' => ['<path d="M4 8h13"/>', '<path d="M14 5l3 3-3 3"/>', '<path d="M20 16H7"/>', '<path d="M10 13l-3 3 3 3"/>'],
        'bank' => ['<path d="M3 10l9-5 9 5"/>', '<path d="M5.5 10.5v7.5M10 10.5v7.5M14 10.5v7.5M18.5 10.5v7.5"/>', '<path d="M3 21h18"/>'],
        // Post-formation and advisory
        'calculator' => ['<rect x="5" y="3" width="14" height="18" rx="2"/>', '<path d="M9 7h6"/>', '<path d="M9 12h.01M12 12h.01M15 12h.01M9 16h.01M12 16h.01M15 16h.01"/>'],
        'chart' => ['<path d="M4 4v16h16"/>', '<path d="M8 16v-4M12 16V8M16 16v-6"/>'],
        'compass' => ['<circle cx="12" cy="12" r="9"/>', '<path d="M15.5 8.5l-2 5-5 2 2-5z"/>'],
        'shield' => ['<path d="M12 3l7 3v6c0 4-3 7.4-7 9-4-1.6-7-5-7-9V6z"/>', '<path d="M9.5 12l1.8 1.8 3.4-3.6"/>'],
        // Activity categories
        'factory' => ['<path d="M3 21V11l5 3V11l5 3V8l6 3.5V21z"/>', '<path d="M3 21h18"/>', '<path d="M8 17h.01M13 17h.01M17 17h.01"/>'],
        'leaf' => ['<path d="M20 4C10 4 4 9 4 16c0 2 .7 3.4 1.5 4.2"/>', '<path d="M20 4c0 9-5 13-11 13"/>', '<path d="M5.5 20.2C9 17 13.5 14 20 4"/>'],
        'cart' => ['<circle cx="9.5" cy="19" r="1.4"/>', '<circle cx="17" cy="19" r="1.4"/>', '<path d="M3 4h2l2.4 10.2a1.5 1.5 0 001.5 1.2h7.6a1.5 1.5 0 001.5-1.2L20 8H6"/>'],
        'concierge' => ['<path d="M4 17h16"/>', '<path d="M5 17a7 7 0 0114 0"/>', '<path d="M12 6.5V5"/>', '<path d="M3 20.5h18"/>'],
    ];

    $shape = $paths[$name] ?? ['<circle cx="12" cy="12" r="8"/>', '<path d="M12 8v8M8 12h8"/>'];
@endphp

<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    {!! implode('', $shape) !!}
</svg>
