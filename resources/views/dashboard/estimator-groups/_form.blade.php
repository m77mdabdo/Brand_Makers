@php $group = $group ?? null; @endphp

<div class="grid gap-5 sm:grid-cols-2">
    <x-dashboard.field
        name="label[ar]"
        :label="__('dashboard.estimator_groups.label_ar')"
        :value="old('label.ar', $group?->getTranslation('label', 'ar', false))"
        required
    />

    {{-- Optional: content:translate-to-english fills the English side from
         its dictionary, so an admin who leaves this blank gets a group
         that falls back to Arabic rather than an empty heading. --}}
    <x-dashboard.field
        name="label[en]"
        dir="ltr"
        :label="__('dashboard.estimator_groups.label_en')"
        :value="old('label.en', $group?->getTranslation('label', 'en', false))"
    />
</div>

<div class="mt-5 grid gap-5 sm:grid-cols-2">
    <div>
        <label for="selection" class="block text-sm font-bold text-ink">{{ __('dashboard.estimator_groups.selection') }}</label>

        <select
            id="selection"
            name="selection"
            class="mt-1 block min-h-[44px] w-full rounded-lg border-line shadow-sm focus:border-petrol focus:ring-petrol"
        >
            @foreach (\App\Models\EstimatorGroup::SELECTIONS as $selection)
                <option
                    value="{{ $selection }}"
                    @selected(old('selection', $group?->selection ?? \App\Models\EstimatorGroup::SELECTION_MULTI) === $selection)
                >
                    {{ __('dashboard.estimator_groups.selections.'.$selection) }}
                </option>
            @endforeach
        </select>

        <p class="mt-1 text-xs text-muted-ink">{{ __('dashboard.estimator_groups.selection_help') }}</p>
        <x-input-error :messages="$errors->get('selection')" class="mt-2" />
    </div>

    <x-dashboard.field
        name="sort_order"
        type="number"
        dir="ltr"
        :label="__('dashboard.fields.sort_order')"
        :value="old('sort_order', $group?->sort_order ?? 0)"
    />
</div>

@unless ($group)
    {{-- Only on create: the key is what estimator_items.group points at,
         so it is immutable afterwards — renaming it would orphan every
         item in the group from its heading in one write. --}}
    <div class="mt-5">
        <x-dashboard.field
            name="key"
            dir="ltr"
            :label="__('dashboard.fields.key')"
            :value="old('key')"
            :help="__('dashboard.estimator_groups.key_help')"
        />
    </div>
@endunless

<label class="mt-5 flex min-h-[44px] items-center gap-3">
    <input type="hidden" name="is_active" value="0">
    <input
        type="checkbox"
        name="is_active"
        value="1"
        @checked(old('is_active', $group?->is_active ?? true))
        class="h-5 w-5 rounded border-line text-orange-ink focus:ring-petrol"
    >
    <span class="text-sm font-bold text-ink">{{ __('dashboard.fields.is_active') }}</span>
</label>
