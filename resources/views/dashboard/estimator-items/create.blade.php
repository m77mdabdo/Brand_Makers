<x-layouts.app :title="__('dashboard.actions.create')">
    <x-slot:header>
        <x-dashboard.page-header
            :title="__('dashboard.resources.estimator_items')"
            :subtitle="__('dashboard.estimator_items.intro')"
            :back="route('dashboard.estimator-items.index')"
        />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.estimator-items.store') }}" class="max-w-4xl space-y-6">
        @csrf
        @include('dashboard.estimator-items._form', ['item' => null, 'groups' => $groups])

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.create') }}</button>
        </div>
    </form>
</x-layouts.app>
