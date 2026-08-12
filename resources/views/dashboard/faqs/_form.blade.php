@props(['faq' => null])

<x-dashboard.card>
    <x-dashboard.translatable-field name="question" :label="__('dashboard.fields.question')" :model="$faq" />
    <x-dashboard.translatable-field name="answer" :label="__('dashboard.fields.answer')" :model="$faq" type="rich" :rows="6" />
</x-dashboard.card>

<x-dashboard.card>
    <x-dashboard.field name="sort_order" :label="__('dashboard.fields.sort_order')" type="number" :value="$faq?->sort_order ?? 0" />
    <x-dashboard.checkbox name="is_active" :label="__('dashboard.fields.is_active')" :checked="$faq?->is_active ?? true" />
</x-dashboard.card>
