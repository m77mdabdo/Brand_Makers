@props(['tone' => 'neutral'])

<span @class([
    'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-extrabold',
    'bg-petrol/10 text-petrol' => $tone === 'neutral',
    'bg-orange-ink/10 text-orange-ink' => $tone === 'warning',
    'bg-petrol text-white' => $tone === 'success',
    'bg-cream-2 text-muted-ink' => $tone === 'muted',
])>{{ $slot }}</span>
