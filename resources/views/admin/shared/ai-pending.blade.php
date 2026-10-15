{{-- AI suggestions the admin asked for on this record and never reviewed (PendingSuggestions):
     the credit is spent, so a corner card offers them again in the tool's own review.
     @include('admin.shared.ai-pending', ['entityType' => 'post', 'entity' => $post])
     Included by ai-edit-button on a saved record; renders once per page. --}}
@once
    @php $aiPending = app(\App\Services\AiGateway\PendingSuggestions::class)->forRecord($entityType, $entity, auth()->user()); @endphp
    @if($aiPending !== [])
        @push('scripts')
            {{-- Above the sticky Save/Cancel bar of the admin forms. --}}
            <div class="card shadow position-fixed end-0 me-3 js-ai-pending" style="bottom: 5.5rem; z-index: 1040; max-width: 360px;" role="status">
                <div class="card-body p-3">
                    <div class="d-flex align-items-start gap-2 mb-1">
                        <strong class="me-auto"><i class="ti ti-sparkles me-1" aria-hidden="true"></i>{{ __('ai.pending.title', ['count' => count($aiPending)]) }}</strong>
                        <button type="button" class="btn-close btn-sm js-ai-pending-close" aria-label="{{ __('ai.pending.close') }}"></button>
                    </div>
                    <p class="small text-muted mb-2">{{ __('ai.pending.note') }}</p>
                    <ul class="list-unstyled mb-0">
                        @foreach($aiPending as $index => $item)
                            <li class="d-flex align-items-center gap-2 py-1 border-top js-ai-pending-item" data-index="{{ $index }}">
                                <span class="small me-auto">
                                    {{ __('ai.pending.tasks.'.str_replace('.', '_', $item['task'])) }} · {{ strtoupper($item['locale']) }}
                                    <span class="d-block text-muted">{{ $item['created_at'] }}</span>
                                </span>
                                <button type="button" class="btn btn-sm btn-outline-primary js-ai-pending-open">{{ __('ai.pending.open') }}</button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <script>
                (function () {
                    const items = @json($aiPending);
                    // After every tool has registered its review (they do so on DOMContentLoaded).
                    window.addEventListener('load', function () {
                        const panel = document.querySelector('.js-ai-pending');
                        if (!panel) return;
                        const tidy = () => { if (!panel.querySelector('.js-ai-pending-item')) panel.remove(); };
                        panel.querySelector('.js-ai-pending-close').addEventListener('click', () => panel.remove());
                        panel.querySelectorAll('.js-ai-pending-item').forEach(function (row) {
                            const item = items[Number(row.dataset.index)];
                            const button = document.querySelector(item.selector + '[data-locale="' + CSS.escape(item.locale) + '"]') || document.querySelector(item.selector);
                            const open = (window.AdminAiResume || {})[item.task];
                            if (!button || !open) { row.remove(); return; }
                            row.querySelector('.js-ai-pending-open').addEventListener('click', function () {
                                open(button, item.data);
                                row.remove();
                                tidy();
                            });
                        });
                        tidy();
                    });
                })();
            </script>
        @endpush
    @endif
@endonce
