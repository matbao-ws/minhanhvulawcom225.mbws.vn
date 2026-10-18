{{-- This month's AI credits. Always shown; locked (App\Support\AiAccess) it says why. $aiUsage and
     $aiUsageAccess come from a view composer. --}}
@if($aiUsageVisible)
    <div class="col-12">
        <div class="dashboard-package-usage__item dashboard-ai-usage">
            <span class="dashboard-ai-usage__icon"><i class="ti ti-sparkles fs-5" aria-hidden="true"></i></span>
            <div class="dashboard-ai-usage__content">
                <div class="dashboard-ai-usage__title">{{ __('ai.usage.title') }}</div>
                <div class="dashboard-ai-usage__description">{{ __('ai.usage.credits') }}</div>
            </div>
            @if(! $aiUsageAccess['allowed'])
                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto is-ai-locked"{!! \App\Support\AiAccess::lockAttributes($aiUsageAccess) !!} title="{{ $aiUsageAccess['message'] }}" data-ai-usage-locked>
                    <i class="ti ti-lock me-1" aria-hidden="true"></i>{{ $aiUsageAccess['title'] }}
                </button>
            @elseif($aiUsage)
                @php
                    $limit = max(1, $aiUsage['credits_limit']);
                    $percent = min(100, (int) round($aiUsage['credits_used'] * 100 / $limit));
                @endphp
                <div class="dashboard-ai-usage__progress">
                    <div class="dashboard-ai-usage__meta">
                        <strong>{{ $aiUsage['credits_used'] }} / {{ $aiUsage['credits_limit'] }}</strong>
                        <span>{{ __('ai.usage.remaining', ['count' => max(0, $aiUsage['credits_limit'] - $aiUsage['credits_used'])]) }}</span>
                    </div>
                    <div class="progress" style="height: 7px" role="progressbar" aria-label="{{ __('ai.usage.credits') }}" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar {{ $percent >= 90 ? 'bg-danger' : ($percent >= 70 ? 'bg-warning' : 'bg-primary') }}" style="width: {{ $percent }}%"></div>
                    </div>
                </div>
            @else
                <span class="dashboard-ai-usage__description ms-auto">{{ __('ai.usage.unknown') }}</span>
            @endif
        </div>
    </div>
@endif
