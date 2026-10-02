{{-- "AI copy edit" beside a rich-text editor.
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
@endphp
@if($aiAllowed && $entity?->exists)
    <div class="d-flex flex-wrap align-items-center gap-2 my-2">
        <button type="button" class="btn btn-sm btn-outline-primary js-ai-edit"
                data-entity-type="{{ $entityType }}"
                data-entity-id="{{ $entity->getKey() }}"
                data-locale="{{ $locale }}"
                data-target="{{ $target }}">
            <i class="ti ti-wand me-1"></i>{{ __('ai.edit.button') }}
        </button>
        <span class="text-muted small">{{ __('ai.edit.saved_only') }}</span>
    </div>
    @include('admin.shared.ai-edit-assets')
@endif
