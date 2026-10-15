{{-- AI tools beside a rich-text editor: copy edit, FAQ, image alts, and a description written from
     scratch (products; categories, blog categories and brands - brands have only that one).
     @include('admin.shared.ai-edit-button', [
         'entityType' => 'post', 'entity' => $post, 'locale' => $code, 'target' => 'content_input',
     ])
     `target` is the id of the textarea the editor writes to. Renders nothing without feature
     ai_seo + permission ai.use + the record's update permission. On a record not saved yet each
     button saves it as a draft first, then opens on the edit page (ai-draft-launch). --}}
@php
    $aiCopyEdit = isset(\App\Services\AiGateway\MetaSuggestionService::ENTITIES[$entityType]);
    $aiCollection = isset(\App\Services\AiGateway\CollectionIntroService::ENTITIES[$entityType]);
    $aiDefinition = \App\Services\AiGateway\MetaSuggestionService::ENTITIES[$entityType]
        ?? \App\Services\AiGateway\CollectionIntroService::ENTITIES[$entityType] ?? null;
    $aiAllowed = $aiDefinition
        && app(\App\Support\FeatureGate::class)->availableTo(auth()->user(), 'ai_seo')
        && auth()->user()?->can('ai.use')
        && auth()->user()?->can($aiDefinition['permission']);
    $aiData = 'data-entity-type="'.e($entityType).'" data-entity-id="'.e($entity?->getKey()).'" data-locale="'.e($locale).'" data-target="'.e($target).'"';
    $aiSaved = (bool) $entity?->exists;
    // The tool's own class on a saved record; on a new one, the draft-first launcher.
    $aiClass = fn (string $tool): string => $aiSaved ? 'js-ai-'.$tool : 'js-ai-draft-first';
@endphp
@if($aiAllowed)
    <div class="d-flex flex-wrap align-items-center gap-2 my-2">
        @if($aiCopyEdit)
            <button type="button" class="btn btn-sm btn-outline-primary {{ $aiClass('edit') }}" data-ai-tool="edit" {!! $aiData !!}>
                <i class="ti ti-wand me-1"></i>{{ __('ai.edit.button') }}
            </button>
        @endif
        @if(in_array($entityType, \App\Services\AiGateway\FaqService::ENTITY_TYPES, true))
            <button type="button" class="btn btn-sm btn-outline-primary {{ $aiClass('faq') }}" data-ai-tool="faq" {!! $aiData !!}>
                <i class="ti ti-help-circle me-1"></i>{{ __('ai.faq.button') }}
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary {{ $aiClass('alt') }}" data-ai-tool="alt" {!! $aiData !!}>
                <i class="ti ti-photo-check me-1"></i>{{ __('ai.alt.button') }}
            </button>
        @endif
        @if(in_array($entityType, \App\Services\AiGateway\InternalLinkService::ENTITY_TYPES, true))
            <button type="button" class="btn btn-sm btn-outline-primary {{ $aiClass('links') }}" data-ai-tool="links" {!! $aiData !!}>
                <i class="ti ti-link me-1"></i>{{ __('ai.links.button') }}
            </button>
        @endif
        @if($entityType === 'product')
            <button type="button" class="btn btn-sm btn-outline-primary {{ $aiClass('product') }}" data-ai-tool="product" {!! $aiData !!}>
                <i class="ti ti-sparkles me-1"></i>{{ __('ai.product.button') }}
            </button>
        @endif
        @if($aiCollection)
            <button type="button" class="btn btn-sm btn-outline-primary {{ $aiClass('collection') }}" data-ai-tool="collection" {!! $aiData !!}>
                <i class="ti ti-sparkles me-1"></i>{{ __('ai.collection.button') }}
            </button>
        @endif
        <span class="text-muted small">{{ $aiSaved ? __('ai.edit.saved_only') : __('ai.draft_first.hint') }}</span>
    </div>
    @include('admin.shared.ai-edit-assets')
    @include('admin.shared.ai-tools-assets')
    @include('admin.shared.ai-apply-guard')
    @include('admin.shared.ai-draft-launch')
    @if($aiSaved)
        @include('admin.shared.ai-pending', ['entityType' => $entityType, 'entity' => $entity])
    @endif
@endif
