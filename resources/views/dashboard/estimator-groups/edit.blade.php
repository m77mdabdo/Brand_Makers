<x-layouts.app :title="__('dashboard.actions.edit')">
    <x-slot:header>
        <x-dashboard.page-header
            :title="$group->label"
            :subtitle="__('dashboard.resources.estimator_groups')"
            :back="route('dashboard.estimator-groups.index')"
        />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.estimator-groups.update', $group) }}" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')
        @include('dashboard.estimator-groups._form', ['group' => $group])

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.update') }}</button>
        </div>
    </form>
</x-layouts.app>
