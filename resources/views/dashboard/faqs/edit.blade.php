<x-layouts.app :title="__('dashboard.actions.edit')">
    <x-slot:header>
        <x-dashboard.page-header :title="$faq->question" :back="route('dashboard.faqs.index')" />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.faqs.update', $faq) }}" enctype="multipart/form-data" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')
        @include('dashboard.faqs._form', ['faq' => $faq])

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.update') }}</button>
        </div>
    </form>
</x-layouts.app>
