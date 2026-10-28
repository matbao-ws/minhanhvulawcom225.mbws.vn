{{-- "Let the AI fix it": filled by admin-assets/js/seo-tasks.js — progress, then a review of each change, then the result. --}}
<div class="modal fade" id="seoFixModal" tabindex="-1" aria-labelledby="seoFixModalTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="min-w-0">
                    <h2 class="modal-title h5 fw-bold" id="seoFixModalTitle"><i class="ti ti-sparkles me-1 text-primary" aria-hidden="true"></i>{{ __('ai.tasks.fix.modal_title') }}</h2>
                    <div class="small seo-muted text-truncate" data-seo-fix-page></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ai.tasks.fix.close') }}"></button>
            </div>
            <div class="modal-body">
                <ul class="seo-fix__progress" data-seo-fix-progress aria-live="polite"></ul>
                <p class="small seo-muted d-none" data-seo-fix-hint><i class="ti ti-info-circle me-1" aria-hidden="true"></i>{{ __('ai.tasks.fix.review_hint') }}</p>
                <div data-seo-fix-blocks></div>
                <div class="seo-fix__done d-none" data-seo-fix-done role="status"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <p class="seo-fix__note"><i class="ti ti-coin me-1" aria-hidden="true"></i>{{ __('ai.tasks.fix.cost_note') }}</p>
                <div class="d-flex gap-2" data-seo-fix-actions>
                    <button type="button" class="btn seo-btn-ghost" data-bs-dismiss="modal">{{ __('ai.tasks.fix.cancel') }}</button>
                    <button type="button" class="btn btn-primary" data-seo-fix-apply disabled><i class="ti ti-check me-1" aria-hidden="true"></i><span>{{ __('ai.tasks.fix.apply_none') }}</span></button>
                </div>
            </div>
        </div>
    </div>
</div>
