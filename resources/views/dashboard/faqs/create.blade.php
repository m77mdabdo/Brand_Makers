<x-layouts.app :title="__('dashboard.actions.create')">
    <x-slot:header>
        <x-dashboard.page-header :title="__('dashboard.resources.faqs')" :back="route('dashboard.faqs.index')" />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.faqs.store') }}" enctype="multipart/form-data" class="max-w-4xl space-y-6">
        @csrf
        @include('dashboard.faqs._form', ['faq' => null])

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.create') }}</button>
        </div>
    </form>
</x-layouts.app>
