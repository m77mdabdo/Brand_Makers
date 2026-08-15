<x-layouts.app :title="$package->name">
    <x-slot:header>
        <x-dashboard.page-header
            :title="$package->name"
            :subtitle="__('dashboard.resources.packages')"
            :back="route('dashboard.packages.index')"
        />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.packages.update', $package) }}" enctype="multipart/form-data" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')
        @include('dashboard.packages._form', ['package' => $package])

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.update') }}</button>
        </div>
    </form>
</x-layouts.app>
