<x-layouts.app :title="$page->title">
    <x-slot:header>
        <x-dashboard.page-header :title="$page->title" :subtitle="'/'.$page->slug" :back="route('dashboard.pages.index')" />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.pages.update', $page) }}" enctype="multipart/form-data" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        <x-dashboard.card>
            <x-dashboard.translatable-field name="title" :label="__('dashboard.fields.title')" :model="$page" />
            <x-dashboard.translatable-field name="body" :label="__('dashboard.fields.body')" :model="$page" type="rich" :rows="16" />
            <x-dashboard.checkbox name="is_published" :label="__('dashboard.fields.is_published')" :checked="$page->is_published" />
        </x-dashboard.card>

        <x-dashboard.seo-fields :model="$page" />

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.update') }}</button>
        </div>
    </form>

    {{-- Sections live outside the page form: they are their own records
         with their own routes, and nesting a form inside another form is
         invalid HTML that browsers resolve by dropping one of them. --}}
    <section class="mt-10 max-w-4xl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-extrabold text-ink">{{ __('dashboard.resources.sections') }}</h2>
                <p class="text-sm text-muted-ink">{{ __('dashboard.pages.sections_intro') }}</p>
            </div>
            <a href="{{ route('dashboard.pages.sections.create', $page) }}" class="btn btn-primary !px-5 !py-2.5 !text-sm">
                {{ __('dashboard.actions.add_section') }}
            </a>
        </div>

        @if ($page->sections->isEmpty())
            <div class="mt-4 rounded-2xl border border-dashed border-line bg-white px-6 py-8 text-center text-sm text-muted-ink">
                {{ __('dashboard.common.none') }}
            </div>
        @else
            <ul class="mt-4 space-y-2">
                @foreach ($page->sections as $section)
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line bg-white px-4 py-3">
                        <div class="min-w-0">
                            <p class="font-bold text-ink">{{ $section->title ?? $section->key }}</p>
                            <p class="text-xs text-muted-ink" dir="ltr">{{ $section->key }} · {{ $section->sort_order }}</p>
                        </div>
                        <div class="flex items-center gap-1">
                            <x-dashboard.badge :tone="$section->is_active ? 'success' : 'muted'">
                                {{ $section->is_active ? __('dashboard.status.active') : __('dashboard.status.inactive') }}
                            </x-dashboard.badge>
                            <a href="{{ route('dashboard.pages.sections.edit', [$page, $section]) }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">{{ __('dashboard.actions.edit') }}</a>
                            <x-dashboard.delete-button :action="route('dashboard.pages.sections.destroy', [$page, $section])" />
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.app>
