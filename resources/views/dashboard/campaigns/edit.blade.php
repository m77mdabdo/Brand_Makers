<x-layouts.app :title="$campaign->name">
    <x-slot:header>
        <x-dashboard.page-header
            :title="$campaign->name"
            :subtitle="__('dashboard.resources.campaigns')"
            :back="route('dashboard.campaigns.index')"
        />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.campaigns.update', $campaign) }}" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')
        @include('dashboard.campaigns._form', ['campaign' => $campaign, 'platforms' => $platforms])

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.update') }}</button>
        </div>
    </form>
</x-layouts.app>
