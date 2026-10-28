{{-- One recommendation, Google Ads style: the action, the page, the evidence and the impact on one row;
     "View" opens the full story, the searches, what the AI would do and the figures. --}}
@php
    $entity = $task['entity'] ?? null;
    $metrics = $task['metrics'] ?? [];
    $fix = $task['fix'] ?? null;
    $type = $task['type'];
    $path = rawurldecode((string) parse_url((string) $task['page'], PHP_URL_PATH));
    $title = $entity['title'] ?? (in_array($path, ['', '/'], true) ? __('admin.home') : $path);
    $icon = ['low_ctr' => 'ti-hand-click', 'striking_distance' => 'ti-trending-up', 'declining' => 'ti-trending-down', 'on_page' => 'ti-file-alert', 'intent_mismatch' => 'ti-arrows-shuffle'][$type] ?? 'ti-sparkles';
    $category = ['low_ctr' => 'snippet', 'striking_distance' => 'content', 'on_page' => 'basics'][$type] ?? 'decide';
    $shown = [
        'clicks' => number_format($metrics['clicks'] ?? 0),
        'previous' => number_format($metrics['previous_clicks'] ?? 0),
        'impressions' => number_format($metrics['impressions'] ?? 0),
        'ctr' => number_format(($metrics['ctr'] ?? 0) * 100, 1),
        'position' => isset($metrics['position']) ? number_format((float) $metrics['position'], 0) : '–',
        'intent' => isset($task['intent']) ? __('ai.tasks.intents.'.$task['intent']) : '',
    ];
    $evidence = $type === 'on_page'
        ? implode(' · ', array_map(fn ($issue) => __('ai.tasks.issues.'.$issue), (array) ($task['issues'] ?? [])))
        : __('ai.tasks.evidence.'.$type, $shown);
    $gain = (int) ($task['gain'] ?? 0);
    // Why the AI cannot fix it, when it cannot: the owner's call, a theme page Mat Bao has to edit, or no access.
    $blocked = match (true) {
        (bool) $fix => null,
        in_array($type, ['declining', 'intent_mismatch'], true) => 'decide',
        ! is_array($entity) => 'theme',
        (bool) ($task['record_gone'] ?? false) => 'gone',
        ! $task['edit_url'] => 'permission',
        default => 'decide',
    };
    $rowAdvice = ! ($fix && $canApply) && ! $task['edit_url'] && $blocked !== 'theme';
    $detailsId = 'seo-task-details-'.$task['id'];
    $fixData = $fix ? [
        'steps' => $fix['steps'],
        'step_url' => route('admin.ai.seo-tasks.fix.step', ['task' => $task['id'], 'step' => '__STEP__']),
        'apply_url' => route('admin.ai.seo-tasks.fix.apply', ['task' => $task['id']]),
        'title' => $title,
        'page' => $task['page'],
        'edit_url' => $task['edit_url'],
    ] : null;
@endphp
<article data-tour="seo-tasks.task" class="seo-rec seo-rec--{{ $category }}" data-seo-task="{{ $task['id'] }}" data-category="{{ $category }}" @if($fixData) data-fix="{{ json_encode($fixData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}" @endif>
    <div class="seo-rec__row">
        <span class="seo-rec__icon" aria-hidden="true"><i class="ti {{ $icon }}"></i></span>

        <div class="seo-rec__main">
            <div class="seo-rec__labels">
                <span class="seo-rec__type">{{ __('ai.tasks.types.'.$type) }}</span>
                @if(($task['priority'] ?? null) === 'critical')
                    <span class="seo-rec__urgent">{{ __('ai.tasks.urgent') }}</span>
                @endif
            </div>
            <h3 class="seo-rec__title">{{ __('ai.tasks.action.'.$type) }}</h3>
            <div class="seo-rec__page">
                @if($task['page'])
                    <a href="{{ $task['page'] }}" target="_blank" rel="noopener noreferrer">{{ $title }}<i class="ti ti-external-link ms-1" aria-hidden="true"></i></a>
                @else
                    <span>{{ $title }}</span>
                @endif
            </div>
            @if($evidence !== '')
                <div class="seo-rec__evidence">{{ $evidence }}</div>
            @endif
        </div>

        <div class="seo-rec__impact">
            @if($type !== 'on_page' && $gain > 0)
                <strong>+{{ number_format($gain) }}</strong>
                <span>{{ __('ai.tasks.impact') }}</span>
            @else
                <span class="seo-rec__impact-text">{{ __('ai.tasks.impact_basics') }}</span>
            @endif
        </div>

        <div class="seo-rec__buttons">
            <button type="button" class="btn seo-btn-ghost" data-bs-toggle="collapse" data-bs-target="#{{ $detailsId }}" aria-expanded="false" aria-controls="{{ $detailsId }}" data-seo-view>
                <span class="when-closed">{{ __('ai.tasks.view') }}</span><span class="when-open">{{ __('ai.tasks.hide') }}</span>
            </button>
            @if($fix && $canApply)
                <button type="button" class="btn btn-primary" data-seo-fix><i class="ti ti-sparkles me-1" aria-hidden="true"></i>{{ __('ai.tasks.apply') }}</button>
            @elseif($task['edit_url'])
                <a class="btn btn-primary" href="{{ $task['edit_url'] }}"><i class="ti ti-edit me-1" aria-hidden="true"></i>{{ __('ai.tasks.fix.manual_button') }}</a>
            @elseif($blocked === 'theme')
                <a class="btn btn-primary" href="{{ \App\Support\AiAccess::SUPPORT_URL }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-headset me-1" aria-hidden="true"></i>{{ __('ai.tasks.fix.ask_support') }}</a>
            @else
                <button data-tour="seo-tasks.advice" type="button" class="btn btn-primary" data-seo-advice="{{ route('admin.ai.seo-tasks.advice', ['task' => $task['id']]) }}" data-seo-advice-open="#{{ $detailsId }}" title="{{ __('ai.tasks.advice_note') }}">
                    <i class="ti ti-help-circle me-1" aria-hidden="true"></i>{{ __('ai.tasks.fix.why') }}
                </button>
            @endif
        </div>
    </div>

    <div class="collapse" id="{{ $detailsId }}">
        <div class="seo-rec__details">
            <div class="seo-task__why">
                <div class="seo-task__label"><i class="ti ti-bulb" aria-hidden="true"></i>{{ __('ai.tasks.why_title') }}</div>
                <p>{{ __('ai.tasks.type_help.'.$type) }}</p>
            </div>

            @if(! empty($task['issues']))
                <ul class="seo-task__issues">
                    @foreach($task['issues'] as $issue)
                        <li><i class="ti ti-circle-x" aria-hidden="true"></i>{{ __('ai.tasks.issues.'.$issue) }}</li>
                    @endforeach
                </ul>
            @endif

            @if(! empty($task['queries']))
                <div class="seo-task__searches">
                    <div class="seo-task__label"><i class="ti ti-search" aria-hidden="true"></i>{{ __('ai.tasks.searches') }}</div>
                    <div class="seo-task__chips">
                        @foreach($task['queries'] as $query)
                            <span class="seo-task__chip">
                                <span>{{ $query['query'] }}</span>
                                <small>{{ __('ai.tasks.searches_count', ['count' => number_format($query['impressions'] ?? 0)]) }}</small>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($fix)
                <div class="seo-task__plan">
                    <div class="seo-task__label seo-task__label--plan"><i class="ti ti-sparkles" aria-hidden="true"></i>{{ __('ai.tasks.fix.title') }}</div>
                    <ul class="seo-task__plan-list">
                        @foreach($fix['steps'] as $step)
                            <li><i class="ti ti-circle-check" aria-hidden="true"></i>{{ __('ai.tasks.fix.steps.'.$step, ['count' => \App\Services\AiGateway\SeoTaskFixer::MAX_IMAGES]) }}</li>
                        @endforeach
                        @foreach($fix['manual'] as $manual)
                            <li class="is-manual"><i class="ti ti-user-edit" aria-hidden="true"></i>{{ __('ai.tasks.fix.manual.'.$manual) }}</li>
                        @endforeach
                    </ul>
                    <p class="seo-task__plan-note">
                        <i class="ti ti-eye" aria-hidden="true"></i>{{ __('ai.tasks.fix.preview_note') }}
                        <span class="text-nowrap">· {{ __('ai.tasks.fix.credits', ['count' => $fix['credits']]) }}</span>
                    </p>
                    @unless($canApply)
                        <p class="seo-task__plan-note mt-1">{{ __('ai.tasks.fix.no_permission') }}</p>
                    @endunless
                </div>
            @else
                <div class="seo-task__plan seo-task__plan--manual">
                    <i class="ti ti-user-question" aria-hidden="true"></i>
                    <span>{{ ['decide' => __('ai.tasks.fix.not_fixable'), 'theme' => __('ai.tasks.fix.no_editor'), 'permission' => __('ai.tasks.fix.no_edit_permission'), 'gone' => __('ai.tasks.fix.record_gone')][$blocked] }}</span>
                </div>
            @endif

            <section class="seo-advice d-none" data-seo-advice-result aria-live="polite"></section>

            @if($type !== 'on_page')
                <dl class="seo-task__metrics">
                    <div><dt>{{ __('ai.tasks.clicks') }}&nbsp;@include('admin.ai.seo-tasks._help', ['text' => __($type === 'declining' ? 'ai.tasks.stat_explain.clicks_declining' : 'ai.tasks.stat_explain.clicks', ['value' => $shown['clicks'], 'previous' => $shown['previous']])])</dt><dd>{{ $shown['clicks'] }}@if($type === 'declining') <small>(← {{ $shown['previous'] }})</small>@endif</dd></div>
                    <div><dt>{{ __('ai.tasks.impressions') }}&nbsp;@include('admin.ai.seo-tasks._help', ['text' => __('ai.tasks.stat_explain.impressions', ['value' => $shown['impressions']])])</dt><dd>{{ $shown['impressions'] }}</dd></div>
                    <div><dt>{{ __('ai.tasks.ctr') }}&nbsp;@include('admin.ai.seo-tasks._help', ['text' => __('ai.tasks.stat_explain.ctr', ['value' => $shown['ctr'], 'clicks' => $shown['clicks'], 'impressions' => $shown['impressions']])])</dt><dd>{{ $shown['ctr'] }}%</dd></div>
                    <div><dt>{{ __('ai.tasks.position') }}@if(isset($metrics['position']))&nbsp;@include('admin.ai.seo-tasks._help', ['text' => __('ai.tasks.stat_explain.position', ['value' => $metrics['position'], 'where' => \App\Support\SeoPosition::where((float) $metrics['position'])])])@endif</dt><dd>{{ $metrics['position'] ?? '–' }}</dd></div>
                </dl>
            @endif

            <div class="seo-task__actions">
                @if($task['edit_url'] && $fix && $canApply)
                    <a class="btn btn-sm seo-btn-ghost" href="{{ $task['edit_url'] }}"><i class="ti ti-edit me-1" aria-hidden="true"></i>{{ __('ai.tasks.fix.manual_button') }}</a>
                @endif
                @unless($rowAdvice)
                    <button data-tour="seo-tasks.advice" type="button" class="btn btn-sm btn-link px-1" data-seo-advice="{{ route('admin.ai.seo-tasks.advice', ['task' => $task['id']]) }}" title="{{ __('ai.tasks.advice_note') }}">
                        <i class="ti ti-help-circle me-1" aria-hidden="true"></i>{{ __('ai.tasks.fix.why') }}
                    </button>
                @endunless
                <div data-tour="seo-tasks.mark" class="seo-task__status ms-auto" data-seo-mark-group>
                    <button type="button" class="btn btn-sm seo-btn-ghost" data-seo-mark="done" data-url="{{ route('admin.ai.seo-tasks.status', ['task' => $task['id']]) }}"><i class="ti ti-check me-1 text-success" aria-hidden="true"></i>{{ __('ai.tasks.fix.done_manual') }}</button>
                    <button type="button" class="btn btn-sm seo-btn-ghost" data-seo-mark="dismissed" data-url="{{ route('admin.ai.seo-tasks.status', ['task' => $task['id']]) }}"><i class="ti ti-eye-off me-1" aria-hidden="true"></i>{{ __('ai.tasks.fix.dismiss') }}</button>
                </div>
            </div>
        </div>
    </div>
</article>
