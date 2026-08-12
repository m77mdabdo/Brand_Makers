<x-layouts.app :title="__('dashboard.resources.services')">
    <x-slot:header>
        <x-dashboard.page-header :title="__('dashboard.resources.services')">
            <x-slot:actions>
                <a href="{{ route('dashboard.services.create') }}" class="btn btn-primary !px-5 !py-2.5 !text-sm">
                    {{ __('dashboard.actions.create') }}
                </a>
            </x-slot:actions>
        </x-dashboard.page-header>
    </x-slot:header>

    @if ($services->isEmpty())
        <x-dashboard.empty-state :message="__('dashboard.common.none')" :action="route('dashboard.services.create')" :action-label="__('dashboard.actions.create')" />
    @else
        {{-- overflow-x-auto on the wrapper, never on the page: a wide table
             must scroll inside its own box rather than making the whole
             dashboard scroll sideways on a phone. --}}
        <div class="overflow-x-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[40rem] text-sm">
                <thead class="border-b border-line bg-cream/60 text-start">
                    <tr>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.name') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.sort_order') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.status') }}</th>
                        <th class="px-4 py-3 text-end font-extrabold">{{ __('dashboard.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($services as $service)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="font-bold text-ink">{{ $service->name }}</span>
                                <span class="block text-xs text-muted-ink" dir="ltr">{{ $service->slug }}</span>
                            </td>
                            <td class="px-4 py-3" dir="ltr">{{ $service->sort_order }}</td>
                            <td class="px-4 py-3">
                                <x-dashboard.badge :tone="$service->is_active ? 'success' : 'muted'">
                                    {{ $service->is_active ? __('dashboard.status.active') : __('dashboard.status.inactive') }}
                                </x-dashboard.badge>
                                @if ($service->is_flagship)
                                    <x-dashboard.badge tone="warning">{{ __('dashboard.fields.is_flagship') }}</x-dashboard.badge>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('dashboard.services.edit', $service) }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">
                                        {{ __('dashboard.actions.edit') }}
                                    </a>
                                    <x-dashboard.delete-button :action="route('dashboard.services.destroy', $service)" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $services->links() }}</div>
    @endif
</x-layouts.app>
