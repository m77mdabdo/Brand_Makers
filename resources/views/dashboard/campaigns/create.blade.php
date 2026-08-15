<x-layouts.app :title="__('dashboard.resources.campaigns')">
    <x-slot:header>
        <x-dashboard.page-header
            :title="__('dashboard.resources.campaigns')"
            :subtitle="__('dashboard.campaigns.intro')"
            :back="route('dashboard.campaigns.index')"
        />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.campaigns.store') }}" class="max-w-4xl space-y-6">
        @csrf
        @include('dashboard.campaigns._form', ['campaign' => null, 'platforms' => $platforms])

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.create') }}</button>
        </div>
    </form>
</x-layouts.app>
