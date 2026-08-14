@props(['item' => null, 'groups' => []])

<x-dashboard.card>
    <x-dashboard.translatable-field name="label" :label="__('dashboard.estimator_items.label_field')" :model="$item" />

    <div>
        <label for="group" class="block text-sm font-bold text-ink">{{ __('dashboard.estimator_items.group') }}</label>
        {{-- A datalist, not a select: `group` is a free-form slug on
             purpose, so a studio adding "motion" does not need a
             deployment. The list speeds up the common case without
             closing the field. --}}
        <input
            id="group"
            name="group"
            type="text"
            list="estimator-groups"
            dir="ltr"
            required
            value="{{ old('group', $item?->group) }}"
            class="mt-1 block min-h-[44px] w-full rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
        >
        <datalist id="estimator-groups">
            @foreach ($groups as $group)
                <option value="{{ $group }}"></option>
            @endforeach
        </datalist>
        <p class="mt-1 text-xs text-muted-ink">{{ __('dashboard.estimator_items.group_help') }}</p>
        <x-input-error :messages="$errors->get('group')" class="mt-2" />
    </div>

    @if ($item)
        {{-- Shown but NOT editable. The key is set once and is immutable:
             the seeder matches on it to stay idempotent, and every stored
             estimate snapshot records it so a quoted line can be traced
             back here. UpdateEstimatorItemRequest has no rule for it, so
             even a hand-crafted payload cannot change it. --}}
        <div>
            <span class="block text-sm font-bold text-ink">{{ __('dashboard.fields.key') }}</span>
            <p class="mt-1 rounded-lg bg-cream px-3 py-2 text-sm text-muted-ink" dir="ltr">{{ $item->key }}</p>
            <p class="mt-1 text-xs text-muted-ink">{{ __('dashboard.estimator_items.key_immutable') }}</p>
        </div>
    @else
        <x-dashboard.field
            name="key"
            :label="__('dashboard.fields.key')"
            dir="ltr"
            :value="old('key')"
            :help="__('dashboard.estimator_items.key_help')"
        />
    @endif
</x-dashboard.card>

<x-dashboard.card>
    <div>
        <label for="price" class="block text-sm font-bold text-ink">
            {{ __('dashboard.estimator_items.price') }}
            <span class="text-muted-ink">({{ $item?->currency ?? 'SAR' }})</span>
        </label>
        <input
            id="price"
            name="price"
            type="number"
            step="0.01"
            min="0"
            dir="ltr"
            required
            value="{{ old('price', $item?->price ?? '0.00') }}"
            class="mt-1 block min-h-[44px] w-full rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
        >
        <p class="mt-1 text-xs text-muted-ink">{{ __('dashboard.estimator_items.price_help') }}</p>
        <x-input-error :messages="$errors->get('price')" class="mt-2" />
    </div>

    <x-dashboard.field name="sort_order" :label="__('dashboard.fields.sort_order')" type="number" :value="$item?->sort_order ?? 0" />

    <x-dashboard.checkbox
        name="is_active"
        :label="__('dashboard.estimator_items.is_active')"
        :checked="$item?->is_active ?? false"
        :help="__('dashboard.estimator_items.is_active_help')"
    />
</x-dashboard.card>
