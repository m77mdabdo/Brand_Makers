<x-layouts.app :title="__('dashboard.actions.edit')">
    <x-slot:header>
        <x-dashboard.page-header :title="$country->name" :back="route('dashboard.countries.index')" />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.countries.update', $country) }}" enctype="multipart/form-data" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')
        @include('dashboard.countries._form', ['country' => $country])

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.update') }}</button>
        </div>
    </form>
</x-layouts.app>
