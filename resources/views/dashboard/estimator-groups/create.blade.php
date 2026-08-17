<x-layouts.app :title="__('dashboard.actions.create')">
    <x-slot:header>
        <x-dashboard.page-header
            :title="__('dashboard.resources.estimator_groups')"
            :subtitle="__('dashboard.estimator_groups.intro')"
            :back="route('dashboard.estimator-groups.index')"
        />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.estimator-groups.store') }}" class="max-w-4xl space-y-6">
        @csrf
        @include('dashboard.estimator-groups._form', ['group' => null])

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.create') }}</button>
        </div>
    </form>
</x-layouts.app>
