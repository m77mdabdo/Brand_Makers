@props(['name' => null])

{{--
    Maps a service's `icon` string to an inline SVG.

    Inline rather than an icon font or sprite sheet: eight icons is far
    less bytes than either, they inherit currentColor for free, and there
    is no extra request on the critical path.

    An unknown or missing name falls back to a neutral mark rather than
    rendering nothing — a card with an empty icon well looks broken, and
    the icon carries no information the heading does not already give
    (the wrapper is aria-hidden).
--}}
@php
    $paths = [
        'compass' => ['<circle cx="12" cy="12" r="9"/>', '<path d="M15.5 8.5l-2 5-5 2 2-5z"/>'],
        'palette' => ['<path d="M12 3a9 9 0 100 18c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3-.3-.4-.5-.8-.5-1.2 0-1 .8-1.8 1.8-1.8H16a5 5 0 005-5c0-3.9-4-6.7-9-6.7z"/>', '<circle cx="7.5" cy="11.5" r="1"/>', '<circle cx="11" cy="7.5" r="1"/>', '<circle cx="15.5" cy="9" r="1"/>'],
        'sparkles' => ['<path d="M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9z"/>', '<path d="M18 15l.8 2L21 17.8l-2.2.8L18 21l-.8-2.4-2.2-.8 2.2-.8z"/>'],
        'document' => ['<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/>', '<path d="M14 3v5h5"/>', '<path d="M9 13h6M9 17h4"/>'],
        'cube' => ['<path d="M12 2.8l8 4.4v9.6l-8 4.4-8-4.4V7.2z"/>', '<path d="M4 7.2l8 4.4 8-4.4"/>', '<path d="M12 11.6V21"/>'],
        'megaphone' => ['<path d="M3 11v2a1 1 0 001 1h2l5 4V6L6 10H4a1 1 0 00-1 1z"/>', '<path d="M16 8.5a4.5 4.5 0 010 7"/>', '<path d="M19 6a8 8 0 010 12"/>'],
        'window' => ['<rect x="3" y="4" width="18" height="16" rx="2"/>', '<path d="M3 9h18"/>', '<circle cx="6.5" cy="6.5" r=".6" fill="currentColor"/>'],
        'film' => ['<rect x="3" y="5" width="18" height="14" rx="2"/>', '<path d="M7 5v14M17 5v14"/>', '<path d="M3 12h18"/>'],
    ];

    $shape = $paths[$name] ?? ['<circle cx="12" cy="12" r="8"/>', '<path d="M12 8v8M8 12h8"/>'];
@endphp

<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    {!! implode('', $shape) !!}
</svg>
