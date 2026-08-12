<x-layouts.app :title="__('dashboard.resources.media')">
    <x-slot:header>
        <x-dashboard.page-header :title="__('dashboard.resources.media')" :subtitle="__('dashboard.media.intro')" />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.media.store') }}" enctype="multipart/form-data" class="mb-8 max-w-3xl">
        @csrf
        <x-dashboard.card :title="__('dashboard.actions.upload')">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-dashboard.field name="file" :label="__('dashboard.fields.file')" type="file" :required="true" :help="__('dashboard.media.upload_help')" />
                <x-dashboard.field name="alt_text" :label="__('dashboard.fields.alt_text')" />
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary">{{ __('dashboard.actions.upload') }}</button>
            </div>
        </x-dashboard.card>
    </form>

    @if ($media->isEmpty())
        <x-dashboard.empty-state :message="__('dashboard.common.none')" />
    @else
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($media as $item)
                <li class="overflow-hidden rounded-2xl border border-line bg-white">
                    <img
                        src="{{ Storage::disk($item->disk)->url($item->path) }}"
                        alt="{{ $item->alt_text }}"
                        loading="lazy"
                        class="h-40 w-full bg-cream object-cover"
                    >
                    <div class="p-4">
                        <p class="truncate text-sm font-bold text-ink">{{ $item->alt_text ?: basename($item->path) }}</p>
                        <p class="mt-1 text-xs text-muted-ink" dir="ltr">
                            {{ $item->mime_type }} · {{ number_format($item->size / 1024) }} KB
                        </p>
                        @if ($item->uploadedBy)
                            <p class="text-xs text-muted-ink">{{ __('dashboard.media.uploaded_by') }}: {{ $item->uploadedBy->name }}</p>
                        @endif

                        <div class="mt-3 flex items-center justify-between gap-2">
                            {{-- A plain link rather than a clipboard button:
                                 navigator.clipboard needs a secure context,
                                 so a copy button would silently do nothing
                                 over plain http in local development. --}}
                            <a
                                href="{{ Storage::disk($item->disk)->url($item->path) }}"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream"
                            >
                                {{ __('dashboard.media.copy_url') }}
                            </a>
                            <x-dashboard.delete-button :action="route('dashboard.media.destroy', $item)" />
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $media->links() }}</div>
    @endif
</x-layouts.app>
