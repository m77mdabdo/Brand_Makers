<x-layouts.app :title="__('dashboard.resources.tracking')">
    <x-slot:header>
        <x-dashboard.page-header :title="__('dashboard.resources.tracking')" :subtitle="__('dashboard.tracking.intro')" />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.tracking.update') }}" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        {{--
            Rendered by looping TrackingSetting::FORMATS, so a platform
            added there appears here, gets a validation rule and gets
            persisted with no other edit — and nothing outside that list
            can be rendered, submitted or written.
        --}}
        <x-dashboard.card>
            @foreach ($formats as $key => $pattern)
                <div class="border-b border-line pb-5 last:border-0 last:pb-0">
                    <x-dashboard.field
                        :name="$key"
                        :label="__('dashboard.tracking.fields.'.$key)"
                        dir="ltr"
                        :value="$settings[$key] ?? null"
                        :help="__('dashboard.tracking.examples.'.$key)"
                    />

                    <div class="mt-2">
                        <x-dashboard.checkbox
                            :name="'active['.$key.']'"
                            :label="__('dashboard.fields.enabled')"
                            :checked="(bool) ($active[$key] ?? false)"
                        />
                    </div>
                </div>
            @endforeach
        </x-dashboard.card>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.save') }}</button>
        </div>
    </form>
</x-layouts.app>
