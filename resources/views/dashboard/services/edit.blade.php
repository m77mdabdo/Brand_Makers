<x-layouts.app :title="__('dashboard.actions.edit')">
    <x-slot:header>
        <x-dashboard.page-header :title="$service->name" :back="route('dashboard.services.index')" />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.services.update', $service) }}" enctype="multipart/form-data" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')
        @include('dashboard.services._form', ['service' => $service])

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.update') }}</button>
        </div>
    </form>
</x-layouts.app>
