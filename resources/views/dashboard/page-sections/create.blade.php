<x-layouts.app :title="__('dashboard.actions.add_section')">
    <x-slot:header>
        <x-dashboard.page-header :title="__('dashboard.actions.add_section')" :subtitle="$page->title" :back="route('dashboard.pages.edit', $page)" />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.pages.sections.store', $page) }}" class="max-w-3xl space-y-6">
        @csrf
        @php($section = null)
<x-dashboard.card>
    {{-- Flat fields; PageSectionService repacks them into the nested JSON
         blob the PageSection accessors read back. --}}
    <div class="grid gap-5 lg:grid-cols-2">
        <x-dashboard.field name="title_ar" :label="__('dashboard.fields.title').' ('.__('dashboard.common.arabic').')'" dir="rtl" :value="$section?->content['title']['ar'] ?? null" :required="true" />
        <x-dashboard.field name="title_en" :label="__('dashboard.fields.title').' ('.__('dashboard.common.english').')'" dir="ltr" :value="$section?->content['title']['en'] ?? null" />
        <x-dashboard.field name="description_ar" :label="__('dashboard.fields.description').' ('.__('dashboard.common.arabic').')'" type="textarea" dir="rtl" :value="$section?->content['description']['ar'] ?? null" />
        <x-dashboard.field name="description_en" :label="__('dashboard.fields.description').' ('.__('dashboard.common.english').')'" type="textarea" dir="ltr" :value="$section?->content['description']['en'] ?? null" />
    </div>

    <div class="grid gap-5 sm:grid-cols-3">
        <x-dashboard.field name="key" :label="__('dashboard.fields.key')" dir="ltr" :value="$section?->key" :required="true" />
        <x-dashboard.field name="icon" :label="__('dashboard.fields.icon')" dir="ltr" :value="$section?->content['icon'] ?? null" />
        <x-dashboard.field name="sort_order" :label="__('dashboard.fields.sort_order')" type="number" :value="$section?->sort_order ?? 0" />
    </div>

    <x-dashboard.checkbox name="is_active" :label="__('dashboard.fields.is_active')" :checked="$section?->is_active ?? true" />
</x-dashboard.card>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.create') }}</button>
        </div>
    </form>
</x-layouts.app>
