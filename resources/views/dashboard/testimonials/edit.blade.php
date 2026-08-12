<x-layouts.app :title="__('dashboard.actions.edit')">
    <x-slot:header>
        <x-dashboard.page-header :title="$testimonial->client_name" :back="route('dashboard.testimonials.index')" />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.testimonials.update', $testimonial) }}" enctype="multipart/form-data" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')
        @include('dashboard.testimonials._form', ['testimonial' => $testimonial])

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.update') }}</button>
        </div>
    </form>
</x-layouts.app>
