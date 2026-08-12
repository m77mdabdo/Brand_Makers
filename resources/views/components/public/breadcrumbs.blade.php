@props(['items'])

{{--
    $items is [label => url|null]; the LAST entry is the current page and
    renders as text rather than a link.

    aria-current="page" on it and a nav landmark around the whole thing —
    a breadcrumb read out as a bare list of links is noise without them.
--}}
<nav aria-label="{{ __('site.nav.breadcrumb') }}" class="wrap pt-8">
    <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-ink">
        @foreach ($items as $label => $url)
            <li class="flex items-center gap-2">
                @if (! $loop->last && $url)
                    <a href="{{ $url }}" class="rounded transition hover:text-orange-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol">{{ $label }}</a>
                    <span aria-hidden="true">/</span>
                @else
                    <span aria-current="page" class="font-bold text-ink">{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
