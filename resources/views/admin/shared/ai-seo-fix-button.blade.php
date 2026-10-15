{{-- "Fix by SEO score" in the SEO box of a post, product or policy page (SeoFixService).
     @include('admin.shared.ai-seo-fix-button', ['entityType' => 'product', 'entity' => $product])
     Always shown; locked (and saying why) without the plan, the feature or the permissions
     (App\Support\AiAccess). On a record not saved yet it saves a draft first (ai-draft-launch). --}}
@php
    $aiDefinition = \App\Services\AiGateway\MetaSuggestionService::ENTITIES[$entityType] ?? null;
    $aiApplies = $aiDefinition && isset(\App\Services\AiGateway\SeoFixService::ENTITY_TYPES[$entityType]);
    $aiAccess = $aiApplies ? app(\App\Support\AiAccess::class)->check(auth()->user(), 'ai_seo', $aiDefinition['permission']) : null;
    $aiAllowed = (bool) ($aiAccess['allowed'] ?? false);
@endphp
@if($aiApplies)
    <div class="text-center mb-3">
        <button type="button" class="btn btn-sm btn-outline-success {{ ! $aiAllowed ? 'is-ai-locked' : ($entity?->exists ? 'js-ai-seo-fix' : 'js-ai-draft-first') }}" data-ai-tool="seo-fix" data-entity-type="{{ $entityType }}" data-entity-id="{{ $entity?->getKey() }}" data-locale="{{ app(\App\Services\LanguageRegistry::class)->defaultLocale() }}"@unless($aiAllowed){!! \App\Support\AiAccess::lockAttributes($aiAccess) !!} title="{{ $aiAccess['message'] }}"@endunless>
            <i class="ti ti-sparkles me-1"></i>{{ __('ai.seo.button') }}@unless($aiAllowed)<i class="ti ti-lock ai-lock-icon" aria-hidden="true"></i>@endunless
        </button>
        @if($aiAllowed)
            <div class="text-muted small mt-1">{{ $entity?->exists ? __('ai.seo.saved_only') : __('ai.draft_first.hint') }}</div>
        @endif
        @if($aiAccess['bypassed'])
        <span class="ai-superadmin-note" title="{{ __('ai.locked.superadmin', ['reason' => __('ai.locked.reasons.'.$aiAccess['bypassed'])]) }}"><i class="ti ti-eye" aria-hidden="true"></i>{{ __('ai.locked.badge') }}</span>
        @endif
    </div>
    @if($aiAllowed)
        @include('admin.shared.ai-tools-assets')
        @include('admin.shared.ai-apply-guard')
        @include('admin.shared.ai-draft-launch')
    @endif
@endif
