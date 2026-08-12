@props([
    'model' => null,
    'title' => null,
    'description' => null,
    'type' => 'website',
])

@php
    $seo = app(App\Services\Seo\SeoTagService::class);

    $resolvedTitle = $seo->title($model, $title);
    $resolvedDescription = $seo->description($model, $description);
    $canonical = $seo->canonical();
    $image = $seo->image($model);
    $siteName = app(App\Services\Cms\SettingsRepository::class)->translated('site_name') ?? __('site.brand.name');
@endphp

<title>{{ $resolvedTitle }}</title>

@if ($resolvedDescription)
    <meta name="description" content="{{ $resolvedDescription }}">
@endif

{{-- One canonical per document, query string stripped — see
     SeoTagService::canonical() for why UTM variants must not each claim
     to be canonical. --}}
<link rel="canonical" href="{{ $canonical }}">

{{-- Open Graph --}}
<meta property="og:type" content="{{ $type }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $resolvedTitle }}">
@if ($resolvedDescription)
    <meta property="og:description" content="{{ $resolvedDescription }}">
@endif
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:locale" content="{{ app()->getLocale() === 'ar' ? 'ar_SA' : 'en_US' }}">
{{-- og:locale:alternate tells a crawler the other language exists as a
     sibling of THIS document, which is the OG counterpart to hreflang. --}}
<meta property="og:locale:alternate" content="{{ app()->getLocale() === 'ar' ? 'en_US' : 'ar_SA' }}">

{{-- Twitter/X --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $resolvedTitle }}">
@if ($resolvedDescription)
    <meta name="twitter:description" content="{{ $resolvedDescription }}">
@endif
<meta name="twitter:image" content="{{ $image }}">
