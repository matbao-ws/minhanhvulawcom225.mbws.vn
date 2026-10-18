{{-- AI tools beside a rich-text editor: copy edit, FAQ, image alts, and a description written from
     scratch (products; categories, blog categories and brands - brands have only that one).
     @include('admin.shared.ai-edit-button', [
         'entityType' => 'post', 'entity' => $post, 'locale' => $code, 'target' => 'content_input',
     ])
     `target` is the id of the textarea the editor writes to. Always shown: without the plan, the
     feature or the permissions (App\Support\AiAccess) the buttons are locked and explain why
     instead of opening their tool. On a record not saved yet each button saves it as a draft first,
     then opens on the edit page (ai-draft-launch). --}}
@php
    $aiCopyEdit = isset(\App\Services\AiGateway\MetaSuggestionService::ENTITIES[$entityType]);
    $aiCollection = isset(\App\Services\AiGateway\CollectionIntroService::ENTITIES[$entityType]);
    $aiDefinition = \App\Services\AiGateway\MetaSuggestionService::ENTITIES[$entityType]
        ?? \App\Services\AiGateway\CollectionIntroService::ENTITIES[$entityType] ?? null;
    $aiAccess = $aiDefinition ? app(\App\Support\AiAccess::class)->check(auth()->user(), 'ai_seo', $aiDefinition['permission']) : null;
    $aiAllowed = (bool) ($aiAccess['allowed'] ?? false);
    $aiData = 'data-entity-type="'.e($entityType).'" data-entity-id="'.e($entity?->getKey()).'" data-locale="'.e($locale).'" data-target="'.e($target).'"';
    $aiSaved = (bool) $entity?->exists;
    // The tool's own class on a saved record; on a new one, the draft-first launcher; locked: the
    // dialog that says why (no tool class, so no tool handler).
    $aiClass = fn (string $tool): string => ! $aiAllowed ? 'is-ai-locked' : ($aiSaved ? 'js-ai-'.$tool : 'js-ai-draft-first');
    $aiLock = $aiAllowed ? '' : \App\Support\AiAccess::lockAttributes($aiAccess).' title="'.e($aiAccess['message']).'"';
    $aiLockIcon = $aiAllowed ? '' : '<i class="ti ti-lock ai-lock-icon" aria-hidden="true"></i>';
@endphp
@if($aiDefinition)
    <div class="d-flex flex-wrap align-items-center gap-2 my-2">
        @if($aiCopyEdit)
            <button type="button" class="btn btn-sm btn-outline-primary {{ $aiClass('edit') }}" data-ai-tool="edit" {!! $aiData !!}{!! $aiLock !!}>
                <i class="ti ti-wand me-1"></i>{{ __('ai.edit.button') }}{!! $aiLockIcon !!}
            </button>
        @endif
        @if(in_array($entityType, \App\Services\AiGateway\FaqService::ENTITY_TYPES, true))
            <button type="button" class="btn btn-sm btn-outline-primary {{ $aiClass('faq') }}" data-ai-tool="faq" {!! $aiData !!}{!! $aiLock !!}>
                <i class="ti ti-help-circle me-1"></i>{{ __('ai.faq.button') }}{!! $aiLockIcon !!}
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary {{ $aiClass('alt') }}" data-ai-tool="alt" {!! $aiData !!}{!! $aiLock !!}>
                <i class="ti ti-photo-check me-1"></i>{{ __('ai.alt.button') }}{!! $aiLockIcon !!}
            </button>
        @endif
        @if(in_array($entityType, \App\Services\AiGateway\InternalLinkService::ENTITY_TYPES, true))
            <button type="button" class="btn btn-sm btn-outline-primary {{ $aiClass('links') }}" data-ai-tool="links" {!! $aiData !!}{!! $aiLock !!}>
                <i class="ti ti-link me-1"></i>{{ __('ai.links.button') }}{!! $aiLockIcon !!}
            </button>
        @endif
        @if($entityType === 'product')
            <button type="button" class="btn btn-sm btn-outline-primary {{ $aiClass('product') }}" data-ai-tool="product" {!! $aiData !!}{!! $aiLock !!}>
                <i class="ti ti-sparkles me-1"></i>{{ __('ai.product.button') }}{!! $aiLockIcon !!}
            </button>
        @endif
        @if($aiCollection)
            <button type="button" class="btn btn-sm btn-outline-primary {{ $aiClass('collection') }}" data-ai-tool="collection" {!! $aiData !!}{!! $aiLock !!}>
                <i class="ti ti-sparkles me-1"></i>{{ __('ai.collection.button') }}{!! $aiLockIcon !!}
            </button>
        @endif
        @if($aiAllowed)
            <span class="text-muted small">{{ $aiSaved ? __('ai.edit.saved_only') : __('ai.draft_first.hint') }}</span>
        @endif
        @if($aiAccess['bypassed'])
        <span class="ai-superadmin-note" title="{{ __('ai.locked.superadmin', ['reason' => __('ai.locked.reasons.'.$aiAccess['bypassed'])]) }}"><i class="ti ti-eye" aria-hidden="true"></i>{{ __('ai.locked.badge') }}</span>
        @endif
    </div>
    @if($aiAllowed)
        @include('admin.shared.ai-edit-assets')
        @include('admin.shared.ai-tools-assets')
        @include('admin.shared.ai-apply-guard')
        @include('admin.shared.ai-draft-launch')
        @if($aiSaved)
            @include('admin.shared.ai-pending', ['entityType' => $entityType, 'entity' => $entity])
        @endif
    @endif
@endif
