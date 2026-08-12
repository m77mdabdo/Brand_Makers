<x-layouts.app :title="__('dashboard.resources.pages')">
    <x-slot:header>
        <x-dashboard.page-header :title="__('dashboard.resources.pages')" :subtitle="__('dashboard.pages.intro')" />
    </x-slot:header>

    {{-- No "add" button and no delete: these pages are seeded and
         referenced by NAME from the footer, the navigation whitelist and
         the sitemap. --}}
    <div class="overflow-x-auto rounded-2xl border border-line bg-white">
        <table class="w-full min-w-[36rem] text-sm">
            <thead class="border-b border-line bg-cream/60">
                <tr>
                    <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.title') }}</th>
                    <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.resources.sections') }}</th>
                    <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.status') }}</th>
                    <th class="px-4 py-3 text-end font-extrabold">{{ __('dashboard.common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($pages as $page)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-bold text-ink">{{ $page->title }}</span>
                            <span class="block text-xs text-muted-ink" dir="ltr">/{{ $page->slug }}</span>
                        </td>
                        <td class="px-4 py-3" dir="ltr">{{ $page->sections_count }}</td>
                        <td class="px-4 py-3">
                            <x-dashboard.badge :tone="$page->is_published ? 'success' : 'muted'">
                                {{ $page->is_published ? __('dashboard.status.published') : __('dashboard.status.draft') }}
                            </x-dashboard.badge>
                        </td>
                        <td class="px-4 py-3 text-end">
                            <a href="{{ route('dashboard.pages.edit', $page) }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">
                                {{ __('dashboard.actions.edit') }}
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
