{{-- "Fix by SEO score" in the SEO box of a post, product or policy page (SeoFixService).
     @include('admin.shared.ai-seo-fix-button', ['entityType' => 'product', 'entity' => $product])
     Needs ai_seo + ai.use + the record's update permission, on a saved record. --}}
@php
    $aiDefinition = \App\Services\AiGateway\MetaSuggestionService::ENTITIES[$entityType] ?? null;
    $aiAllowed = $aiDefinition
        && isset(\App\Services\AiGateway\SeoFixService::ENTITY_TYPES[$entityType])
        && app(\App\Support\FeatureGate::class)->availableTo(auth()->user(), 'ai_seo')
        && auth()->user()?->can('ai.use')
        && auth()->user()?->can($aiDefinition['permission']);
@endphp
@if($aiAllowed && $entity?->exists)
    <div class="text-center mb-3">
        <button type="button" class="btn btn-sm btn-outline-success js-ai-seo-fix" data-entity-type="{{ $entityType }}" data-entity-id="{{ $entity->getKey() }}">
            <i class="ti ti-sparkles me-1"></i>{{ __('ai.seo.button') }}
        </button>
        <div class="text-muted small mt-1">{{ __('ai.seo.saved_only') }}</div>
    </div>
    @include('admin.shared.ai-tools-assets')
    @include('admin.shared.ai-apply-guard')
@endif
