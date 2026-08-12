<x-layouts.app :title="__('dashboard.resources.faqs')">
    <x-slot:header>
        <x-dashboard.page-header :title="__('dashboard.resources.faqs')">
            <x-slot:actions>
                <a href="{{ route('dashboard.faqs.create') }}" class="btn btn-primary !px-5 !py-2.5 !text-sm">
                    {{ __('dashboard.actions.create') }}
                </a>
            </x-slot:actions>
        </x-dashboard.page-header>
    </x-slot:header>

    @if ($faqs->isEmpty())
        <x-dashboard.empty-state :message="__('dashboard.common.none')" :action="route('dashboard.faqs.create')" :action-label="__('dashboard.actions.create')" />
    @else
        {{-- overflow-x-auto on the wrapper, never on the page: a wide table
             scrolls inside its own box rather than making the whole
             dashboard scroll sideways on a phone. --}}
        <div class="overflow-x-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[36rem] text-sm">
                <thead class="border-b border-line bg-cream/60">
                    <tr>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.question') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.sort_order') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.status') }}</th>
                        <th class="px-4 py-3 text-end font-extrabold">{{ __('dashboard.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($faqs as $faq)
                        <tr>
                            <td class="px-4 py-3 font-bold text-ink">{{ $faq->question }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ $faq->sort_order }}</td>
                            <td class="px-4 py-3">
                                <x-dashboard.badge :tone="$faq->is_active ? 'success' : 'muted'">
                                    {{ $faq->is_active ? __('dashboard.status.active') : __('dashboard.status.inactive') }}
                                </x-dashboard.badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('dashboard.faqs.edit', $faq) }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">
                                        {{ __('dashboard.actions.edit') }}
                                    </a>
                                    <x-dashboard.delete-button :action="route('dashboard.faqs.destroy', $faq)" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $faqs->links() }}</div>
    @endif
</x-layouts.app>
