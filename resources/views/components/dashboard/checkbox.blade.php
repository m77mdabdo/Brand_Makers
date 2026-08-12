@props(['name', 'label', 'checked' => false, 'help' => null])

<div>
    {{-- The hidden field is what makes an UNTICKED box submit a value.
         Without it the key is absent entirely, and "absent" would be
         indistinguishable from "field not on this form" — so unticking
         would silently leave the old value in place. --}}
    <input type="hidden" name="{{ $name }}" value="0">

    <label for="{{ $name }}" class="inline-flex min-h-[44px] cursor-pointer items-center gap-2.5">
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="checkbox"
            value="1"
            @checked(old($name, $checked))
            class="h-5 w-5 rounded border-line text-orange-ink shadow-sm focus:ring-petrol"
        >
        <span class="text-sm font-bold text-ink">{{ $label }}</span>
    </label>

    @if ($help)
        <p class="text-xs text-muted-ink">{{ $help }}</p>
    @endif
</div>
