@props(['article' => null])

<x-dashboard.card>
    <x-dashboard.translatable-field name="title" :label="__('dashboard.fields.title')" :model="$article" />
    <x-dashboard.translatable-field name="excerpt" :label="__('dashboard.fields.excerpt')" :model="$article" type="textarea" :rows="2" :required="false" />
    <x-dashboard.translatable-field name="body" :label="__('dashboard.fields.body')" :model="$article" type="rich" :rows="14" />
</x-dashboard.card>

<x-dashboard.card>
    <div class="grid gap-5 sm:grid-cols-2">
        <x-dashboard.field name="slug" :label="__('dashboard.fields.slug')" dir="ltr" :value="$article?->slug" :help="__('dashboard.fields.slug_help')" />
        {{-- datetime-local, so scheduling a post is the same control as
             publishing one — see ArticleContentService for how the three
             states are derived from this plus the checkbox. --}}
        <x-dashboard.field
            name="published_at"
            :label="__('dashboard.fields.published_at')"
            type="datetime-local"
            dir="ltr"
            :value="$article?->published_at?->format('Y-m-d\\TH:i')"
            :help="__('dashboard.fields.published_at_help')"
        />
    </div>

    <div>
        <x-dashboard.field name="cover_image" :label="__('dashboard.fields.cover_image')" type="file" />
        @if ($article?->cover_image)
            <img src="{{ Storage::url($article->cover_image) }}" alt="" class="mt-2 h-20 w-auto rounded-lg border border-line">
        @endif
    </div>

    <x-dashboard.checkbox name="is_published" :label="__('dashboard.fields.is_published')" :checked="$article?->is_published ?? false" />
</x-dashboard.card>

<x-dashboard.seo-fields :model="$article" />
