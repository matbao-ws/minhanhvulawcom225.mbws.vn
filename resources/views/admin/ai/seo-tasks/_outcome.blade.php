{{-- A fixed task: waiting for its 28 days, or measured against the rest of the website. --}}
@php
    $entity = $outcome['entity'] ?? null;
    $path = rawurldecode((string) parse_url((string) ($outcome['page'] ?? ''), PHP_URL_PATH));
    $title = $entity['title'] ?? (in_array($path, ['', '/'], true) ? __('admin.home') : $path);
    $tools = array_map(fn ($tool) => __('ai.tasks.tools.'.str_replace('.', '_', $tool)), (array) ($outcome['tools'] ?? []));
    $byAi = ($outcome['applied_by'] ?? 'owner') === 'ai';
    $percent = fn ($value) => $value === null ? '–' : (($value >= 0 ? '+' : '').number_format($value * 100, 0).'%');
    $verdict = $outcome['verdict'] ?? null;
    $tone = ['better' => 'success', 'flat' => 'secondary', 'worse' => 'danger', 'too_little_data' => 'secondary'][$verdict] ?? 'secondary';
    $daysLeft = (int) ($outcome['days_left'] ?? 0);
@endphp
<article class="card seo-outcome seo-outcome--{{ $outcome['status'] === 'measured' ? $tone : 'waiting' }}">
    <div class="card-body seo-outcome__body">
        <div class="seo-outcome__head">
            <div class="seo-outcome__head-text">
                <h3 class="seo-outcome__title">{{ $title }}</h3>
                <div class="seo-outcome__meta">
                    <span class="seo-outcome__by"><i class="ti {{ $byAi ? 'ti-sparkles' : 'ti-user' }}" aria-hidden="true"></i>{{ $byAi ? __('ai.tasks.results.by_ai') : __('ai.tasks.results.by_owner') }}</span>
                    <span>{{ __('ai.tasks.types.'.($outcome['task_type'] ?? 'on_page')) }}</span>
                    <span>{{ __('ai.tasks.results.fixed_on', ['date' => \Illuminate\Support\Carbon::parse($outcome['fixed_on'])->format('d/m/Y')]) }}</span>
                </div>
                @if($tools !== [])
                    <div class="seo-outcome__did">{{ __('ai.tasks.did', ['list' => implode(', ', $tools)]) }}</div>
                @endif
            </div>
            @if($outcome['status'] === 'measured')
                <span class="seo-outcome__verdict text-bg-{{ $tone }}">{{ __('ai.tasks.results.verdict.'.$verdict) }}</span>
            @endif
        </div>

        @if($outcome['status'] === 'waiting')
            <div class="seo-outcome__wait">
                <div class="progress" role="progressbar" aria-label="{{ __('ai.tasks.results.days_left', ['count' => $daysLeft]) }}" aria-valuemin="0" aria-valuemax="28" aria-valuenow="{{ 28 - min(28, $daysLeft) }}">
                    <div class="progress-bar" style="width: {{ round((28 - min(28, $daysLeft)) / 28 * 100) }}%"></div>
                </div>
                <span><i class="ti ti-clock-hour-4" aria-hidden="true"></i>{{ $daysLeft > 0 ? __('ai.tasks.results.days_left', ['count' => $daysLeft]) : __('ai.tasks.results.ready_soon') }}</span>
            </div>
        @elseif($outcome['after'] ?? null)
            <p class="seo-outcome__line">{{ __('ai.tasks.results.before_after', ['before' => number_format($outcome['before']['clicks'] ?? 0), 'after' => number_format($outcome['after']['clicks'] ?? 0)]) }}</p>
            @php
                $extra = $outcome['extra_clicks'] ?? null;
                $extraText = $extra === null ? null : __('ai.tasks.results.extra', ['count' => ($extra >= 0 ? '+' : '').number_format($extra)]);
                // From a handful of clicks a percentage means nothing (2 → 6 is "+200%"): show clicks only.
                $showPercent = ($outcome['before']['clicks'] ?? 0) >= 5;
            @endphp
            <dl class="seo-outcome__figures">
                @if($showPercent)
                    <div><dt>{{ __('ai.tasks.results.page_change') }}</dt><dd>{{ $percent($outcome['page_change'] ?? null) }}</dd></div>
                @endif
                <div><dt>{{ __('ai.tasks.results.site_change') }}</dt><dd>{{ $percent($outcome['site_change'] ?? null) }}</dd></div>
                <div class="is-net text-{{ $tone }}"><dt>{{ __('ai.tasks.results.net') }}</dt><dd>@if($showPercent){{ $percent($outcome['net_change'] ?? null) }}@if($extraText) <small>({{ $extraText }})</small>@endif @else{{ $extraText ?? '–' }}@endif</dd></div>
            </dl>
        @endif
    </div>
</article>
