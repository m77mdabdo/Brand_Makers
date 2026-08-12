<x-layouts.app :title="__('dashboard.resources.testimonials')">
    <x-slot:header>
        <x-dashboard.page-header :title="__('dashboard.resources.testimonials')">
            <x-slot:actions>
                <a href="{{ route('dashboard.testimonials.create') }}" class="btn btn-primary !px-5 !py-2.5 !text-sm">
                    {{ __('dashboard.actions.create') }}
                </a>
            </x-slot:actions>
        </x-dashboard.page-header>
    </x-slot:header>

    @if ($testimonials->isEmpty())
        <x-dashboard.empty-state :message="__('dashboard.common.none')" :action="route('dashboard.testimonials.create')" :action-label="__('dashboard.actions.create')" />
    @else
        {{-- overflow-x-auto on the wrapper, never on the page: a wide table
             scrolls inside its own box rather than making the whole
             dashboard scroll sideways on a phone. --}}
        <div class="overflow-x-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[36rem] text-sm">
                <thead class="border-b border-line bg-cream/60">
                    <tr>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.client_name') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.sort_order') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.status') }}</th>
                        <th class="px-4 py-3 text-end font-extrabold">{{ __('dashboard.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($testimonials as $testimonial)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="font-bold text-ink">{{ $testimonial->client_name }}</span>
                                @if ($testimonial->client_title)
                                    <span class="block text-xs text-muted-ink">{{ $testimonial->client_title }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3" dir="ltr">{{ $testimonial->sort_order }}</td>
                            <td class="px-4 py-3">
                                <x-dashboard.badge :tone="$testimonial->is_active ? 'success' : 'muted'">
                                    {{ $testimonial->is_active ? __('dashboard.status.active') : __('dashboard.status.inactive') }}
                                </x-dashboard.badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('dashboard.testimonials.edit', $testimonial) }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">
                                        {{ __('dashboard.actions.edit') }}
                                    </a>
                                    <x-dashboard.delete-button :action="route('dashboard.testimonials.destroy', $testimonial)" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $testimonials->links() }}</div>
    @endif
</x-layouts.app>
