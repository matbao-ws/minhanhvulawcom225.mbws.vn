{{-- "AI meta suggestions" next to an SEO title/description pair.
     @include('admin.shared.ai-meta-button', [
         'entityType' => 'product', 'entity' => $product, 'locale' => $code,
         'titleField' => "meta_title[$code]", 'descriptionField' => "meta_description[$code]",
     ])
     Always shown; locked (and saying why) without the plan, the feature or the permissions
     (App\Support\AiAccess). On a record not saved yet the button saves it as a draft first
     (ai-draft-launch). --}}
@php
    $aiDefinition = \App\Services\AiGateway\MetaSuggestionService::ENTITIES[$entityType] ?? null;
    $aiAccess = $aiDefinition ? app(\App\Support\AiAccess::class)->check(auth()->user(), 'ai_seo', $aiDefinition['permission']) : null;
    $aiAllowed = (bool) ($aiAccess['allowed'] ?? false);
@endphp
@if($aiDefinition)
    <div class="{{ $wrapperClass ?? 'mb-3' }}">
        @if(! $aiAllowed)
            <button type="button" class="btn btn-sm btn-outline-primary is-ai-locked"{!! \App\Support\AiAccess::lockAttributes($aiAccess) !!} title="{{ $aiAccess['message'] }}">
                <i class="ti ti-sparkles me-1"></i>{{ $label ?? __('ai.meta.button') }}<i class="ti ti-lock ai-lock-icon" aria-hidden="true"></i>
            </button>
        @elseif($entity?->exists)
            <button type="button" class="btn btn-sm btn-outline-primary js-ai-meta-suggest"
                    data-entity-type="{{ $entityType }}"
                    data-entity-id="{{ $entity->getKey() }}"
                    data-locale="{{ $locale }}"
                    data-title-field="{{ $titleField }}"
                    data-description-field="{{ $descriptionField }}">
                <i class="ti ti-sparkles me-1"></i>{{ $label ?? __('ai.meta.button') }}
            </button>
        @else
            <button type="button" class="btn btn-sm btn-outline-primary js-ai-draft-first" data-ai-tool="meta-suggest" data-locale="{{ $locale }}">
                <i class="ti ti-sparkles me-1"></i>{{ $label ?? __('ai.meta.button') }}
            </button>
            <div class="text-muted small mt-1">{{ __('ai.draft_first.hint') }}</div>
        @endif
        @if($aiAccess['bypassed'])
        <span class="ai-superadmin-note" title="{{ __('ai.locked.superadmin', ['reason' => __('ai.locked.reasons.'.$aiAccess['bypassed'])]) }}"><i class="ti ti-eye" aria-hidden="true"></i>{{ __('ai.locked.badge') }}</span>
        @endif
    </div>
    @if($aiAllowed)
        @include('admin.shared.ai-meta-assets')
        @include('admin.shared.ai-apply-guard')
        @include('admin.shared.ai-draft-launch')
    @endif
@endif
