<x-layouts.app :title="__('dashboard.actions.edit')">
    <x-slot:header>
        <x-dashboard.page-header :title="$item->label" :back="route('dashboard.menu.index')" />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.menu.update', $item) }}" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')
        @php($item = $item)
<x-dashboard.card>
    <x-dashboard.translatable-field name="label" :label="__('dashboard.fields.label')" :model="$item" />

    {{-- The three link types, and the fields each one uses. Both
         destination fields are always rendered; MenuService clears
         whichever the chosen type does not use, so a stale value cannot
         resurface if the type is switched back. --}}
    <x-dashboard.field
        name="link_type"
        :label="__('dashboard.fields.link_type')"
        :value="$item?->link_type ?? 'route'"
        :options="collect(App\Models\MenuItem::LINK_TYPES)->mapWithKeys(fn ($t) => [$t => __('dashboard.link_types.'.$t)])->all()"
        :required="true"
    />

    <x-dashboard.field
        name="route_name"
        :label="__('dashboard.fields.route_name')"
        :value="$item?->route_name"
        :options="['' => __('dashboard.common.none')] + $routes"
        :help="__('dashboard.menu.route_help')"
    />

    <x-dashboard.field name="url" :label="__('dashboard.fields.url')" dir="ltr" :value="$item?->url" :help="__('dashboard.menu.url_help')" />

    <div class="grid gap-5 sm:grid-cols-2">
        <x-dashboard.field
            name="parent_id"
            :label="__('dashboard.fields.parent')"
            :value="$item?->parent_id"
            :options="['' => __('dashboard.menu.no_parent')] + $parents->mapWithKeys(fn ($p) => [$p->id => $p->label])->all()"
        />
        <x-dashboard.field
            name="target"
            :label="__('dashboard.fields.target')"
            :value="$item?->target ?? '_self'"
            :options="collect(App\Models\MenuItem::TARGETS)->mapWithKeys(fn ($t) => [$t => __('dashboard.targets.'.$t)])->all()"
        />
    </div>

    <x-dashboard.checkbox name="is_visible" :label="__('dashboard.fields.is_visible')" :checked="$item?->is_visible ?? true" />
</x-dashboard.card>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.update') }}</button>
        </div>
    </form>
</x-layouts.app>
