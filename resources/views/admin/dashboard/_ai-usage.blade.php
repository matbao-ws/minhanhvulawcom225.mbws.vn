{{-- This month's AI credits (feature ai_seo + permission ai.use). $aiUsage comes from a view composer. --}}
@if($aiUsageVisible)
    <div class="card shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="d-flex align-items-center gap-2 me-auto">
                <i class="ti ti-sparkles fs-6 text-primary"></i>
                <div>
                    <div class="fw-semibold">{{ __('ai.usage.title') }}</div>
                    <div class="small text-muted">{{ __('ai.usage.credits') }}</div>
                </div>
            </div>
            @if($aiUsage)
                @php
                    $limit = max(1, $aiUsage['credits_limit']);
                    $percent = min(100, (int) round($aiUsage['credits_used'] * 100 / $limit));
                @endphp
                <div style="min-width: 220px">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold">{{ $aiUsage['credits_used'] }} / {{ $aiUsage['credits_limit'] }}</span>
                        <span class="text-muted">{{ __('ai.usage.remaining', ['count' => max(0, $aiUsage['credits_limit'] - $aiUsage['credits_used'])]) }}</span>
                    </div>
                    <div class="progress" style="height: 8px" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar {{ $percent >= 90 ? 'bg-danger' : ($percent >= 70 ? 'bg-warning' : 'bg-primary') }}" style="width: {{ $percent }}%"></div>
                    </div>
                </div>
            @else
                <span class="small text-muted">{{ __('ai.usage.unknown') }}</span>
            @endif
        </div>
    </div>
@endif
