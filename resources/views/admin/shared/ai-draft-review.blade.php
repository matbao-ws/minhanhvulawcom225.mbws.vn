{{-- On an unpublished AI draft: what the owner must check before publishing.
     $aiDraftReview comes from a view composer (PostDraftService::review). --}}
@if($aiDraftReview)
    <div class="alert alert-warning" role="alert">
        <div class="fw-semibold mb-2"><i class="ti ti-alert-triangle me-1"></i>{{ __('ai.draft.review_title') }}</div>
        <p class="small mb-2">{{ __('ai.draft.review_intro') }}</p>
        @if($aiDraftReview['missing_info'])
            <div class="small fw-semibold">{{ __('ai.draft.missing_info') }}</div>
            <ul class="small mb-2">
                @foreach($aiDraftReview['missing_info'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @endif
        @if($aiDraftReview['claims_to_verify'])
            <div class="small fw-semibold">{{ __('ai.draft.claims') }}</div>
            <ul class="small mb-0">
                @foreach($aiDraftReview['claims_to_verify'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
