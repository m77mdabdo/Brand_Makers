<x-layouts.app :title="__('dashboard.orders.create_heading')">
    <x-slot:header>
        <x-dashboard.page-header
            :title="__('dashboard.orders.create_heading')"
            :subtitle="__('dashboard.orders.create_hint')"
            :back="route('dashboard.orders.index')"
        />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.orders.store') }}" class="max-w-4xl space-y-6">
        @csrf
        @include('dashboard.orders._form', ['order' => null, 'packages' => $packages, 'leads' => $leads])

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.create') }}</button>
        </div>
    </form>
</x-layouts.app>
