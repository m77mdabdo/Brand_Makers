@props(['name', 'label', 'type' => 'text', 'required' => false, 'dir' => null, 'rows' => 4, 'value' => null])

<div>
    <label for="{{ $name }}" class="block text-sm font-bold text-ink">
        {{ $label }}
        @if ($required)
            <span class="text-orange-ink" aria-hidden="true">*</span>
            <span class="sr-only">({{ __('site.forms.required') }})</span>
        @endif
    </label>

    @if ($type === 'textarea')
        <textarea
            id="{{ $name }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            @if ($dir) dir="{{ $dir }}" @endif
            @if ($required) required @endif
            @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
            class="mt-1 block w-full rounded-xl border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
        >{{ old($name, $value) }}</textarea>
    @else
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($name, $value) }}"
            @if ($dir) dir="{{ $dir }}" @endif
            @if ($required) required @endif
            @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
            class="mt-1 block min-h-[44px] w-full rounded-xl border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
        >
    @endif

    @error($name)
        {{-- Referenced by aria-describedby above, so the error is
             announced with the field rather than as loose text. --}}
        <p id="{{ $name }}-error" class="mt-1 text-sm font-bold text-orange-ink">{{ $message }}</p>
    @enderror
</div>
