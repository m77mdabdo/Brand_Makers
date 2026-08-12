@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'help' => null,
    'required' => false,
    'dir' => null,
    'options' => null,
])

@php
    // Dotted names (seo.canonical_url) become bracketed inputs
    // (seo[canonical_url]) but keep the dotted form for old()/errors,
    // which is what Laravel's error bag is keyed on.
    $bracketed = preg_replace('/\.([^.]+)/', '[$1]', $name);
    $id = str_replace(['.', '[', ']'], ['_', '_', ''], $name);
    $current = old($name, $value);
@endphp

<div>
    <label for="{{ $id }}" class="block text-sm font-bold text-ink">
        {{ $label }}
        @if ($required)
            <span class="text-orange-ink" aria-hidden="true">*</span>
        @endif
    </label>

    @if ($options !== null)
        <select
            id="{{ $id }}"
            name="{{ $bracketed }}"
            @if ($required) required @endif
            class="mt-1 block w-full min-h-[44px] rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
        >
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea
            id="{{ $id }}"
            name="{{ $bracketed }}"
            rows="4"
            @if ($dir) dir="{{ $dir }}" @endif
            @if ($required) required @endif
            class="mt-1 block w-full rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
        >{{ $current }}</textarea>
    @elseif ($type === 'file')
        {{-- No SVG in `accept`, matching the validation rule: an SVG is an
             executable document served same-origin. --}}
        <input
            id="{{ $id }}"
            name="{{ $bracketed }}"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="mt-1 block w-full min-h-[44px] rounded-lg border border-line bg-white text-sm text-ink file:me-3 file:min-h-[44px] file:cursor-pointer file:border-0 file:bg-cream-2 file:px-4 file:py-2 file:text-sm file:font-bold file:text-petrol"
        >
    @else
        <input
            id="{{ $id }}"
            name="{{ $bracketed }}"
            type="{{ $type }}"
            value="{{ $current }}"
            @if ($dir) dir="{{ $dir }}" @endif
            @if ($required) required @endif
            class="mt-1 block w-full min-h-[44px] rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
        >
    @endif

    @if ($help)
        <p class="mt-1 text-xs text-muted-ink">{{ $help }}</p>
    @endif

    <x-input-error :messages="$errors->get($name)" class="mt-1" />
</div>
