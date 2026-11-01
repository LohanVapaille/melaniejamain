{{-- Les pages sans données SEO (administration) ne doivent pas apparaître dans Google --}}
@isset($seo)
    <meta name="description" content="{{ $seo['description'] }}" />
    <meta name="author" content="{{ $seo['author'] }}" />
    <meta name="robots" content="{{ $seo['robots'] }}" />
    <link rel="canonical" href="{{ $seo['canonical'] }}" />
    @foreach($seo['alternates'] as $hreflang => $href)
        <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}" />
    @endforeach
    @if($seo['alternates'])
        <link rel="alternate" hreflang="x-default" href="{{ $seo['alternates']['fr'] }}" />
    @endif

    <meta property="og:site_name" content="{{ $seo['author'] }}" />
    <meta property="og:type" content="{{ $seo['type'] }}" />
    <meta property="og:title" content="{{ $seo['socialTitle'] }}" />
    <meta property="og:description" content="{{ $seo['description'] }}" />
    <meta property="og:url" content="{{ $seo['canonical'] }}" />
    <meta property="og:locale" content="{{ $seo['contentLang'] === 'en' ? 'en_US' : 'fr_FR' }}" />
    <meta property="og:image" content="{{ $seo['image'] }}" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="{{ $seo['socialTitle'] }}" />
    @isset($seo['published'])
        <meta property="article:published_time" content="{{ $seo['published'] }}" />
        <meta property="article:modified_time" content="{{ $seo['modified'] }}" />
        <meta property="article:author" content="{{ $seo['author'] }}" />
    @endisset
    <meta name="twitter:card" content="summary_large_image" />

    <script type="application/ld+json">{!! json_encode($seo['jsonLd'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@else
    <meta name="robots" content="noindex,nofollow" />
@endisset
