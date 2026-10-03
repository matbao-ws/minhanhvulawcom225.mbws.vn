{{-- "Fix by SEO score" inside the post SEO checker card. Needs ai_seo + ai.use + posts.update. --}}
@if(app(\App\Support\FeatureGate::class)->availableTo(auth()->user(), 'ai_seo')
    && auth()->user()?->can('ai.use') && auth()->user()?->can('posts.update') && $post?->exists)
    <div class="text-center mb-3">
        <button type="button" class="btn btn-sm btn-outline-success js-ai-seo-fix" data-entity-id="{{ $post->getKey() }}">
            <i class="ti ti-sparkles me-1"></i>{{ __('ai.seo.button') }}
        </button>
        <div class="text-muted small mt-1">{{ __('ai.seo.saved_only') }}</div>
    </div>
    @include('admin.shared.ai-tools-assets')
    @include('admin.shared.ai-apply-guard')
@endif
