{{-- AI tools beside a rich-text editor: copy edit, FAQ, image alts, and (products) a description.
     @include('admin.shared.ai-edit-button', [
         'entityType' => 'post', 'entity' => $post, 'locale' => $code, 'target' => 'content_input',
     ])
     `target` is the id of the textarea the editor writes to. Renders nothing without feature
     ai_seo + permission ai.use + the record's update permission. --}}
@php
    $aiDefinition = \App\Services\AiGateway\MetaSuggestionService::ENTITIES[$entityType] ?? null;
    $aiAllowed = $aiDefinition
        && app(\App\Support\FeatureGate::class)->availableTo(auth()->user(), 'ai_seo')
        && auth()->user()?->can('ai.use')
        && auth()->user()?->can($aiDefinition['permission']);
    $aiData = 'data-entity-type="'.e($entityType).'" data-entity-id="'.e($entity?->getKey()).'" data-locale="'.e($locale).'" data-target="'.e($target).'"';
@endphp
@if($aiAllowed && $entity?->exists)
    <div class="d-flex flex-wrap align-items-center gap-2 my-2">
        <button type="button" class="btn btn-sm btn-outline-primary js-ai-edit" {!! $aiData !!}>
            <i class="ti ti-wand me-1"></i>{{ __('ai.edit.button') }}
        </button>
        @if(in_array($entityType, \App\Services\AiGateway\FaqService::ENTITY_TYPES, true))
            <button type="button" class="btn btn-sm btn-outline-primary js-ai-faq" {!! $aiData !!}>
                <i class="ti ti-help-circle me-1"></i>{{ __('ai.faq.button') }}
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary js-ai-alt" {!! $aiData !!}>
                <i class="ti ti-photo-check me-1"></i>{{ __('ai.alt.button') }}
            </button>
        @endif
        @if($entityType === 'product')
            <button type="button" class="btn btn-sm btn-outline-primary js-ai-product" {!! $aiData !!}>
                <i class="ti ti-sparkles me-1"></i>{{ __('ai.product.button') }}
            </button>
        @endif
        <span class="text-muted small">{{ __('ai.edit.saved_only') }}</span>
    </div>
    @include('admin.shared.ai-edit-assets')
    @include('admin.shared.ai-tools-assets')
@endif
