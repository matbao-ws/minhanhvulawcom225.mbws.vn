{{-- "AI meta suggestions" next to an SEO title/description pair.
     @include('admin.shared.ai-meta-button', [
         'entityType' => 'product', 'entity' => $product, 'locale' => $code,
         'titleField' => "meta_title[$code]", 'descriptionField' => "meta_description[$code]",
     ])
     Renders nothing without feature ai_seo + permission ai.use + the record's update permission. --}}
@php
    $aiDefinition = \App\Services\AiGateway\MetaSuggestionService::ENTITIES[$entityType] ?? null;
    $aiAllowed = $aiDefinition
        && app(\App\Support\FeatureGate::class)->availableTo(auth()->user(), 'ai_seo')
        && auth()->user()?->can('ai.use')
        && auth()->user()?->can($aiDefinition['permission']);
@endphp
@if($aiAllowed)
    <div class="mb-3">
        @if($entity?->exists)
            <button type="button" class="btn btn-sm btn-outline-primary js-ai-meta-suggest"
                    data-entity-type="{{ $entityType }}"
                    data-entity-id="{{ $entity->getKey() }}"
                    data-locale="{{ $locale }}"
                    data-title-field="{{ $titleField }}"
                    data-description-field="{{ $descriptionField }}">
                <i class="ti ti-sparkles me-1"></i>{{ __('ai.meta.button') }}
            </button>
        @else
            <span class="text-muted small"><i class="ti ti-sparkles me-1"></i>{{ __('ai.meta.save_first') }}</span>
        @endif
    </div>
    @include('admin.shared.ai-meta-assets')
    @include('admin.shared.ai-apply-guard')
@endif
