<x-layouts.app :title="__('dashboard.actions.create')">
    <x-slot:header>
        <x-dashboard.page-header :title="__('dashboard.resources.services')" :back="route('dashboard.services.index')" />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.services.store') }}" enctype="multipart/form-data" class="max-w-4xl space-y-6">
        @csrf
        @include('dashboard.services._form', ['service' => null])

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.create') }}</button>
        </div>
    </form>
</x-layouts.app>
