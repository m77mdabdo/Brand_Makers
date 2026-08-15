<x-layouts.app :title="$order->reference">
    <x-slot:header>
        <x-dashboard.page-header
            :title="$order->reference"
            :subtitle="__('dashboard.resources.orders')"
            :back="route('dashboard.orders.show', $order)"
        />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.orders.update', $order) }}" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')
        @include('dashboard.orders._form', ['order' => $order, 'packages' => $packages, 'leads' => $leads])

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.update') }}</button>
        </div>
    </form>
</x-layouts.app>
