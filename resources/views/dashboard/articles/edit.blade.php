<x-layouts.app :title="__('dashboard.actions.edit')">
    <x-slot:header>
        <x-dashboard.page-header :title="$article->title" :back="route('dashboard.articles.index')" />
    </x-slot:header>

    <form method="POST" action="{{ route('dashboard.articles.update', $article) }}" enctype="multipart/form-data" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')
        @include('dashboard.articles._form', ['article' => $article])

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ __('dashboard.common.update') }}</button>
        </div>
    </form>
</x-layouts.app>
