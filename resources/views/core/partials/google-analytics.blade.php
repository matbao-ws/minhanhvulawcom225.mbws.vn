{{-- GA4 tag. The composer in AppServiceProvider leaves $gaMeasurementId null for
     staff, the responsive preview and shops with the feature off, so admin
     browsing never counts as traffic. --}}
@if($gaMeasurementId)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaMeasurementId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json($gaMeasurementId));
    </script>
@endif
