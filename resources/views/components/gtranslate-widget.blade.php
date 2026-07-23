@php($multilingual = app(\App\Services\MultilingualSettings::class))

@if($multilingual->usesGTranslate())
    <div class="gtranslate_wrapper notranslate"></div>
    <script>window.gtranslateSettings = @json($multilingual->widgetSettings(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);</script>
    <script src="{{ $multilingual->widgetScriptUrl() }}" defer></script>
@endif
