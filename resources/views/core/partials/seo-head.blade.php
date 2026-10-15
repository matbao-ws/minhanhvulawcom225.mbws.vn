{{-- Search, social preview and verification tags for every storefront page, reached through
     @include('core.head'). $seo comes from the page's controller (SeoMetaFactory) or, for pages
     that pass none, from the view composer's fallback built on the page's title section.
     <title> and the meta description stay in the theme layout: a theme cut before this partial
     existed already prints them, and two <title> tags would be worse than none. --}}
<meta name="robots" content="{{ $seo->robots() }}">
<link rel="canonical" href="{{ $seo->canonical }}">
@foreach($seo->alternates as $hreflang => $href)
    <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
@endforeach
@if($seo->xDefault)
    <link rel="alternate" hreflang="x-default" href="{{ $seo->xDefault }}">
@endif
<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:title" content="{{ $seo->ogTitle() }}">
@if($seo->ogDescription())
    <meta property="og:description" content="{{ $seo->ogDescription() }}">
@endif
<meta property="og:url" content="{{ $seo->canonical }}">
<meta property="og:site_name" content="{{ $seoSiteName }}">
@if($seo->image)
    <meta property="og:image" content="{{ $seo->image }}">
@endif
<meta name="twitter:card" content="{{ $seo->twitterCard() }}">
<meta name="twitter:title" content="{{ $seo->ogTitle() }}">
@if($seo->ogDescription())
    <meta name="twitter:description" content="{{ $seo->ogDescription() }}">
@endif
@if($seo->image)
    <meta name="twitter:image" content="{{ $seo->image }}">
@endif
{{ $seoOrganization }}
@foreach($seo->structuredData as $node)
    {{ \App\Support\Seo\StructuredData::script($node) }}
@endforeach
{!! $siteEmbeds['header'] !!}
