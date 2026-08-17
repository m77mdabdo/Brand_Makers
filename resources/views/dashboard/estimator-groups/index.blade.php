<x-layouts.app :title="__('dashboard.resources.estimator_groups')">
    <x-slot:header>
        <x-dashboard.page-header
            :title="__('dashboard.resources.estimator_groups')"
            :subtitle="__('dashboard.estimator_groups.intro')"
        >
            <x-slot:actions>
                <a href="{{ route('dashboard.estimator-groups.create') }}" class="btn btn-primary !px-5 !py-2.5 !text-sm">
                    {{ __('dashboard.actions.create') }}
                </a>
            </x-slot:actions>
        </x-dashboard.page-header>
    </x-slot:header>

    @if ($groups->isEmpty())
        <x-dashboard.empty-state
            :message="__('dashboard.estimator_groups.empty')"
            :action="route('dashboard.estimator-groups.create')"
            :action-label="__('dashboard.actions.create')"
        />
    @else
        <div class="overflow-x-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[44rem] text-sm">
                <thead class="border-b border-line bg-cream/60">
                    <tr>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.estimator_groups.label_ar') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.estimator_groups.selection') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.resources.estimator_items') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.sort_order') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.status') }}</th>
                        <th class="px-4 py-3 text-end font-extrabold">{{ __('dashboard.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($groups as $group)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="font-bold text-ink">{{ $group->label }}</span>
                                <span class="block text-xs text-muted-ink" dir="ltr">{{ $group->key }}</span>
                            </td>
                            <td class="px-4 py-3">
                                {{ __('dashboard.estimator_groups.selections.'.$group->selection) }}
                            </td>
                            <td class="px-4 py-3">
                                {{-- A group whose items are all inactive renders
                                     no selector at all on the public form, which
                                     is otherwise invisible from this screen. --}}
                                <x-dashboard.badge :tone="$group->active_items_count > 0 ? 'success' : 'muted'">
                                    {{ __('dashboard.estimator_groups.item_count', ['active' => $group->active_items_count, 'total' => $group->items_count]) }}
                                </x-dashboard.badge>
                            </td>
                            <td class="px-4 py-3" dir="ltr">{{ $group->sort_order }}</td>
                            <td class="px-4 py-3">
                                <x-dashboard.badge :tone="$group->is_active ? 'success' : 'muted'">
                                    {{ $group->is_active ? __('dashboard.status.active') : __('dashboard.status.inactive') }}
                                </x-dashboard.badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('dashboard.estimator-groups.edit', $group) }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">
                                        {{ __('dashboard.actions.edit') }}
                                    </a>
                                    <x-dashboard.delete-button
                                        :action="route('dashboard.estimator-groups.destroy', $group)"
                                        :confirm="__('dashboard.estimator_groups.confirm_delete')"
                                    />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $groups->links() }}</div>
    @endif
</x-layouts.app>
