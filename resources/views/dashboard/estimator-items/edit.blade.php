<x-layouts.app :title="__('dashboard.actions.edit')">
    <x-slot:header>
        <x-dashboard.page-header
            :title="$item->label"
            :subtitle="__('dashboard.resources.estimator_items')"
            :back="route('dashboard.estimator-items.index')"
        />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.estimator-items.update', $item) }}" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')
        @include('dashboard.estimator-items._form', ['item' => $item, 'groups' => $groups])

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.update') }}</button>
        </div>
    </form>
</x-layouts.app>
