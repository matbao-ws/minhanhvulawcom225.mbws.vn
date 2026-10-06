@extends('admin.layouts.app')

@section('title', __('ai.research.title'))

@push('styles')
    <style>
        .research-page { color: #2a3547; }
        .research-page .text-muted { color: #5a6a85 !important; }
        .research-intro { display: flex; align-items: center; gap: .75rem; margin-bottom: 1.25rem; padding: .9rem 1rem; border-left: 3px solid #c01e25; border-radius: 0 .85rem .85rem 0; color: #526174; background: linear-gradient(90deg, #fff3f4, #fff); }
        .research-card { border: 1px solid #e4ebf4; border-radius: 1rem; box-shadow: 0 .45rem 1.3rem rgba(43, 57, 82, .045); }
        .research-card .card-header { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; padding: 1rem 1.25rem; border-bottom: 1px solid #edf0f6; background: transparent; }
        .research-card__title { margin: 0; color: #2a3547; font-size: 1rem; font-weight: 800; }
        .research-card__hint { margin: .15rem 0 0; color: #5a6a85; font-size: .8rem; }
        .research-competitor { display: flex; align-items: center; gap: .75rem; padding: .75rem .9rem; border: 1px solid #edf0f6; border-radius: .75rem; background: #fbfcff; }
        .research-competitor + .research-competitor { margin-top: .5rem; }
        .research-competitor__domain { font-weight: 800; word-break: break-all; }
        .research-table th { color: #5a6a85; font-size: .75rem; font-weight: 700; white-space: nowrap; }
        .research-table td { vertical-align: middle; }
        .research-table .research-keyword { font-weight: 700; }
        .research-intent { padding: .2rem .5rem; border-radius: 999px; color: #42526b; background: #edf1f8; font-size: .72rem; font-weight: 700; white-space: nowrap; }
        .research-difficulty { display: inline-block; min-width: 2.2rem; padding: .15rem .4rem; border-radius: .4rem; font-size: .75rem; font-weight: 800; text-align: center; }
        .research-difficulty--easy { color: #146c43; background: #d8f5e4; }
        .research-difficulty--medium { color: #8a5300; background: #fff0cf; }
        .research-difficulty--hard { color: #a3131b; background: #ffe2e4; }
        .research-topic { height: 100%; padding: 1rem; border: 1px solid #e5dffc; border-radius: .9rem; background: linear-gradient(145deg, #fbfaff, #f6f9ff); }
        .research-topic__title { margin-bottom: .35rem; color: #2a3547; font-size: .95rem; font-weight: 800; }
        .research-chip { display: inline-block; margin: 0 .25rem .25rem 0; padding: .15rem .5rem; border-radius: 999px; color: #4b3a8f; background: #eee8ff; font-size: .72rem; }
        .research-position { font-size: 1.05rem; font-weight: 800; }
        [data-bs-theme="dark"] .research-page, [data-bs-theme="dark"] .research-card__title, [data-bs-theme="dark"] .research-topic__title { color: #edf2f8; }
        .research-progress { overflow: hidden; border: 1px solid #cfe0fb; border-radius: 1rem; background: linear-gradient(115deg, #f5f9ff, #eef4ff); }
        .research-progress__body { display: flex; flex-wrap: wrap; align-items: center; gap: 1.25rem; padding: 1.25rem 1.5rem; }
        .research-progress__icon { display: inline-flex; width: 3rem; height: 3rem; flex: 0 0 3rem; align-items: center; justify-content: center; border-radius: .9rem; color: #2457a6; background: #dce9ff; font-size: 1.4rem; }
        .research-progress__icon i { animation: research-spin 1.4s linear infinite; }
        .research-progress__title { margin: 0 0 .2rem; color: #1f3558; font-size: 1.05rem; font-weight: 800; }
        .research-progress__hint { margin: 0; color: #4f6182; font-size: .85rem; }
        .research-progress__clock { min-width: 8.5rem; margin-left: auto; text-align: right; }
        .research-progress__label { color: #5a6a85; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .research-progress__time { color: #1f3558; font-size: 1.9rem; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1.1; }
        .research-progress__note { color: #4f6182; font-size: .85rem; }
        .research-progress .progress { height: .4rem; border-radius: 0; background: #dce9ff; }
        .research-progress .progress-bar { background: #2f6fd0; transition: width 1s linear; }
        .research-progress__items { margin: 0; padding: 0 1.5rem 1rem 5.75rem; color: #4f6182; font-size: .8rem; list-style: none; }
        .research-progress__items li { display: inline-block; margin: 0 .35rem .35rem 0; padding: .15rem .55rem; border-radius: 999px; background: #fff; }
        @keyframes research-spin { to { transform: rotate(360deg); } }
        .research-spin { display: inline-block; animation: research-spin 1.2s linear infinite; }
        .research-steps { margin: 0; padding: 0; list-style: none; counter-reset: step; }
        .research-steps li { position: relative; padding: .55rem 0 .55rem 2.6rem; color: #42526b; counter-increment: step; }
        .research-steps li::before { content: counter(step); position: absolute; left: 0; top: .4rem; display: inline-flex; width: 1.8rem; height: 1.8rem; align-items: center; justify-content: center; border-radius: 50%; color: #fff; background: #c01e25; font-size: .85rem; font-weight: 800; }
        .research-competitor--failed { border-color: #f1c9cc; background: #fff7f7; }
        [data-bs-theme="dark"] .research-progress { border-color: #3b5378; background: linear-gradient(115deg, #1f324d, #233a5a); }
        [data-bs-theme="dark"] .research-progress__title, [data-bs-theme="dark"] .research-progress__time { color: #edf2f8; }
        [data-bs-theme="dark"] .research-progress__hint, [data-bs-theme="dark"] .research-progress__note, [data-bs-theme="dark"] .research-progress__label, [data-bs-theme="dark"] .research-progress__items { color: #c4cfde; }
        [data-bs-theme="dark"] .research-progress__items li { background: #2b3f5c; }
        [data-bs-theme="dark"] .research-steps li { color: #dce7f6; }
        [data-bs-theme="dark"] .research-competitor--failed { border-color: #70434a; background: #3a2a33; }
        [data-bs-theme="dark"] .research-card { border-color: #3b4c64; background: #203046; }
        [data-bs-theme="dark"] .research-card .card-header { border-color: #3b4c64; }
        [data-bs-theme="dark"] .research-competitor { border-color: #40536d; background: #293a51; }
        [data-bs-theme="dark"] .research-intent { color: #dce7f6; background: #2b3b51; }
        [data-bs-theme="dark"] .research-topic { border-color: #4b4770; background: linear-gradient(135deg, #2c2948, #26364c); }
        [data-bs-theme="dark"] .research-chip { color: #e2d8ff; background: #3b3260; }
        [data-bs-theme="dark"] .research-intro { color: #c4cfde; background: linear-gradient(90deg, #3a2430, #203046); }
        [data-bs-theme="dark"] .research-page .text-muted, [data-bs-theme="dark"] .research-card__hint { color: #c4cfde !important; }
    </style>
@endpush

@section('content')
    <x-admin.page-header :title="__('ai.research.title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('ai.research.title') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    @if(session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @if($error)
        <div class="alert alert-warning" role="alert">{{ $error }}</div>
    @else
        @php
            $maxCompetitors = $research['limits']['competitors'] ?? 3;
            $maxTracked = $research['limits']['tracked_keywords'] ?? 0;
            $depth = $research['limits']['serp_depth'] ?? 30;
            $date = fn (?string $iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone(config('app.timezone'))->format('d/m/Y H:i') : '';
        @endphp

        <div class="research-page">
            <p class="research-intro small mb-4">
                <span>
                    {{ __('ai.research.intro') }}
                    @if($research['domain'])
                        <span class="text-nowrap">· {{ __('ai.research.domain', ['domain' => $research['domain']]) }}</span>
                    @endif
                </span>
            </p>

            @if(! $research['domain'])
                <div class="alert alert-warning" role="alert">{{ __('ai.research.no_domain') }}</div>
            @endif

            @php
                $pending = \App\Services\AiGateway\KeywordResearchService::pending($research);
                // Rough time per item: a competitor's keyword list, a keyword's first Google check.
                $estimate = max(30, collect($pending)->sum(fn ($item) => $item['kind'] === 'competitor' ? 45 : 10));
                $serverNow = $research['now'] ?? now()->toIso8601String();
                $started = collect($pending)->pluck('since')->filter()->min() ?? $serverNow;
            @endphp
            @if($pending !== [])
                <div class="card research-progress mb-4" role="status" aria-live="polite"
                     data-research-progress="{{ route('admin.ai.research.status') }}"
                     data-now="{{ $serverNow }}" data-start="{{ $started }}" data-estimate="{{ $estimate }}">
                    <div class="research-progress__body">
                        <span class="research-progress__icon"><i class="ti ti-loader-2" aria-hidden="true"></i></span>
                        <div class="me-auto">
                            <h2 class="research-progress__title" data-research-title>{{ __('ai.research.progress_title') }}</h2>
                            <p class="research-progress__hint">{{ __('ai.research.progress_hint') }}</p>
                        </div>
                        <div class="research-progress__clock">
                            <div class="research-progress__label" data-research-label>{{ __('ai.research.remaining') }}</div>
                            <div class="research-progress__time" data-research-countdown>–:–</div>
                        </div>
                    </div>
                    <div class="research-progress__note px-4 pb-2 d-none" data-research-note></div>
                    <ul class="research-progress__items">
                        @foreach($pending as $item)
                            <li>{{ __('ai.research.pending_'.$item['kind'], ['name' => \Illuminate\Support\Str::limit($item['label'], 60)]) }}</li>
                        @endforeach
                    </ul>
                    <div class="progress" aria-hidden="true"><div class="progress-bar" data-research-bar style="width: 3%"></div></div>
                </div>
            @endif

            {{-- Competitors --}}
            <div class="card research-card mb-4">
                <div class="card-header">
                    <div class="me-auto">
                        <h2 class="research-card__title">{{ __('ai.research.competitors') }}</h2>
                        <p class="research-card__hint">{{ __('ai.research.competitors_hint', ['max' => $maxCompetitors]) }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.ai.research.competitors.suggest') }}">
                        @csrf
                        <button class="btn btn-outline-primary btn-sm" type="submit" title="{{ __('ai.research.suggest_hint') }}">
                            <i class="ti ti-users-group me-1" aria-hidden="true"></i>{{ __('ai.research.suggest') }}
                        </button>
                    </form>
                    @if($research['competitors'] !== [])
                        <form method="POST" action="{{ route('admin.ai.research.refresh') }}">
                            @csrf
                            <button class="btn btn-outline-secondary btn-sm" type="submit">
                                <i class="ti ti-refresh me-1" aria-hidden="true"></i>{{ __('ai.research.refresh') }}
                            </button>
                        </form>
                    @endif
                </div>
                <div class="card-body">
                    @forelse($research['competitors'] as $competitor)
                        <div class="research-competitor @if(($competitor['status'] ?? null) === 'failed') research-competitor--failed @endif">
                            <i class="ti ti-world fs-5 text-muted" aria-hidden="true"></i>
                            <div class="me-auto">
                                <div class="research-competitor__domain">{{ $competitor['domain'] }}</div>
                                <div class="small text-muted">
                                    @if(($competitor['status'] ?? null) === 'ok')
                                        {{ __('ai.research.status.ok', ['count' => number_format((int) ($competitor['keywords'] ?? 0)), 'date' => $date($competitor['refreshed_at'] ?? null)]) }}
                                    @elseif(($competitor['status'] ?? null) === 'failed')
                                        <span class="text-danger">{{ __('ai.research.failed_reason', ['reason' => \App\Http\Controllers\Admin\AiResearchController::reasonMessage((string) ($competitor['error'] ?? 'provider_unavailable'))]) }}</span>
                                    @else
                                        <i class="ti ti-loader-2 research-spin" aria-hidden="true"></i> {{ __('ai.research.status.queued') }}
                                    @endif
                                </div>
                            </div>
                            @if(($competitor['status'] ?? null) === 'failed')
                                <form method="POST" action="{{ route('admin.ai.research.refresh') }}">
                                    @csrf
                                    <button class="btn btn-sm btn-primary" type="submit"><i class="ti ti-refresh me-1" aria-hidden="true"></i>{{ __('ai.research.retry') }}</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.ai.research.competitors.destroy', ['domain' => $competitor['domain']]) }}" onsubmit="return confirm(@js(__('ai.research.remove_confirm')))">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('ai.research.remove') }}</button>
                            </form>
                        </div>
                    @empty
                        <h3 class="h6 fw-bold mb-2">{{ __('ai.research.how_title') }}</h3>
                        <ol class="research-steps">
                            @foreach(__('ai.research.how') as $step)
                                <li>{{ $step }}</li>
                            @endforeach
                        </ol>
                    @endforelse

                    @if(count($research['competitors']) < $maxCompetitors)
                        <form method="POST" action="{{ route('admin.ai.research.competitors.store') }}" class="d-flex flex-wrap gap-2 mt-3">
                            @csrf
                            <label class="visually-hidden" for="research-domain">{{ __('ai.research.add_competitor') }}</label>
                            <input class="form-control" style="max-width: 22rem" id="research-domain" name="domain" maxlength="255" value="{{ old('domain') }}" placeholder="{{ __('ai.research.competitor_placeholder') }}" required>
                            <button class="btn btn-primary" type="submit"><i class="ti ti-plus me-1" aria-hidden="true"></i>{{ __('ai.research.add_competitor') }}</button>
                        </form>
                    @endif

                    @if(is_array(session('researchSuggestions')))
                        <div class="mt-4">
                            <h3 class="h6 fw-bold">{{ __('ai.research.suggestions_title') }}</h3>
                            @forelse(session('researchSuggestions') as $suggestion)
                                <div class="research-competitor">
                                    <div class="me-auto">
                                        <div class="research-competitor__domain">{{ $suggestion['domain'] ?? '' }}</div>
                                        <div class="small text-muted">{{ __('ai.research.suggestion_line', ['count' => number_format((int) ($suggestion['common_keywords'] ?? 0))]) }}</div>
                                    </div>
                                    @if(count($research['competitors']) < $maxCompetitors)
                                        <form method="POST" action="{{ route('admin.ai.research.competitors.store') }}">
                                            @csrf
                                            <input type="hidden" name="domain" value="{{ $suggestion['domain'] ?? '' }}">
                                            <button class="btn btn-sm btn-outline-primary" type="submit">{{ __('ai.research.add_competitor') }}</button>
                                        </form>
                                    @endif
                                </div>
                            @empty
                                <p class="text-muted mb-0">{{ __('ai.research.no_suggestions') }}</p>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>

            {{-- Keyword gaps --}}
            <div class="card research-card mb-4">
                <div class="card-header">
                    <div class="me-auto">
                        <h2 class="research-card__title">{{ __('ai.research.gaps') }}</h2>
                        <p class="research-card__hint">{{ __('ai.research.gaps_hint') }}</p>
                    </div>
                    @if(count($research['gaps']) >= 3)
                        <form method="POST" action="{{ route('admin.ai.research.topics') }}">
                            @csrf
                            <button class="btn btn-primary btn-sm" type="submit" title="{{ __('ai.research.topics_hint') }}">
                                <i class="ti ti-sparkles me-1" aria-hidden="true"></i>{{ __('ai.research.topics') }}
                            </button>
                        </form>
                    @endif
                </div>
                <div class="card-body">
                    @if(is_array(session('researchTopics')))
                        <h3 class="h6 fw-bold mb-3">{{ __('ai.research.topics_title') }}</h3>
                        <div class="row g-3 mb-4">
                            @foreach(session('researchTopics') as $topic)
                                <div class="col-md-6 col-xl-4">
                                    <div class="research-topic">
                                        <div class="research-topic__title">{{ $topic['title'] }}</div>
                                        <p class="small text-muted mb-2">{{ $topic['reason'] }}</p>
                                        <div class="small mb-2">
                                            <span class="text-muted">{{ __('ai.research.covers') }}</span>
                                            @foreach($topic['keywords'] as $keyword)
                                                <span class="research-chip">{{ $keyword }}</span>
                                            @endforeach
                                        </div>
                                        @if($canWrite)
                                            <a class="btn btn-sm btn-primary" href="{{ route('admin.ai.post-drafts.create', ['keyword' => $topic['primary_keyword']]) }}">
                                                <i class="ti ti-pencil me-1" aria-hidden="true"></i>{{ __('ai.research.write') }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($research['gaps'] === [])
                        <p class="text-muted mb-0">{{ __('ai.research.no_gaps') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table research-table mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('ai.research.col_keyword') }}</th>
                                        <th class="text-end">{{ __('ai.research.col_volume') }}</th>
                                        <th class="text-center" title="{{ __('ai.research.difficulty_hint') }}">{{ __('ai.research.col_difficulty') }}</th>
                                        <th>{{ __('ai.research.col_intent') }}</th>
                                        <th>{{ __('ai.research.col_competitor') }}</th>
                                        <th class="text-end"><span class="visually-hidden">{{ __('ai.research.track') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($research['gaps'] as $gap)
                                        @php
                                            $difficulty = $gap['difficulty'] ?? null;
                                            $level = $difficulty === null ? null : ($difficulty < 30 ? 'easy' : ($difficulty < 60 ? 'medium' : 'hard'));
                                        @endphp
                                        <tr>
                                            <td class="research-keyword">{{ $gap['keyword'] }}</td>
                                            <td class="text-end">{{ isset($gap['search_volume']) ? number_format((int) $gap['search_volume']) : '–' }}</td>
                                            <td class="text-center">
                                                @if($level)
                                                    <span class="research-difficulty research-difficulty--{{ $level }}">{{ $difficulty }}</span>
                                                @else
                                                    –
                                                @endif
                                            </td>
                                            <td>
                                                @if(! empty($gap['intent']))
                                                    <span class="research-intent">{{ __('ai.research.intents.'.$gap['intent']) }}</span>
                                                @endif
                                            </td>
                                            <td class="small">
                                                @foreach((array) ($gap['competitors'] ?? []) as $rival)
                                                    <div>
                                                        {{ $rival['domain'] ?? '' }}
                                                        @if(isset($rival['rank']))
                                                            <strong>#{{ $rival['rank'] }}</strong>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </td>
                                            <td class="text-end">
                                                @if(! empty($gap['tracked']))
                                                    <span class="badge bg-success-subtle text-success">{{ __('ai.research.tracked') }}</span>
                                                @else
                                                    <form method="POST" action="{{ route('admin.ai.research.keywords.store') }}">
                                                        @csrf
                                                        <input type="hidden" name="keywords" value="{{ $gap['keyword'] }}">
                                                        <button class="btn btn-sm btn-outline-primary text-nowrap" type="submit">
                                                            <i class="ti ti-target-arrow me-1" aria-hidden="true"></i>{{ __('ai.research.track') }}
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Rank tracking --}}
            <div class="card research-card mb-4">
                <div class="card-header">
                    <div class="me-auto">
                        <h2 class="research-card__title">{{ __('ai.research.ranks') }}</h2>
                        <p class="research-card__hint">{{ __('ai.research.ranks_hint', ['used' => count($research['tracked']), 'max' => $maxTracked]) }}</p>
                    </div>
                </div>
                <div class="card-body">
                    @if($research['tracked'] === [])
                        <p class="text-muted">{{ __('ai.research.no_tracked') }}</p>
                    @else
                        <div class="table-responsive mb-3">
                            <table class="table research-table mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('ai.research.col_keyword') }}</th>
                                        <th class="text-center">{{ __('ai.research.col_position') }}</th>
                                        <th>{{ __('ai.research.col_change') }}</th>
                                        <th>{{ __('ai.research.col_checked') }}</th>
                                        <th class="text-end"><span class="visually-hidden">{{ __('ai.research.remove') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($research['tracked'] as $row)
                                        @php
                                            $position = $row['position'] ?? null;
                                            $previous = $row['previous_position'] ?? null;
                                            $checked = ! empty($row['checked_at']);
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="research-keyword">{{ $row['keyword'] }}</div>
                                                @if(! empty($row['url']))
                                                    <div class="small text-muted text-break">{{ $row['url'] }}</div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if(! $checked && empty($row['error']))
                                                    <span class="small text-muted">{{ __('ai.research.checking') }}</span>
                                                @elseif($position !== null)
                                                    <span class="research-position">{{ $position }}</span>
                                                @else
                                                    <span class="small text-muted">{{ __('ai.research.not_ranked', ['depth' => $depth]) }}</span>
                                                @endif
                                                @if(! empty($row['error']))
                                                    <div class="small text-warning">{{ __('ai.research.check_failed') }}</div>
                                                @endif
                                            </td>
                                            <td class="small">
                                                @if($checked && $previous === null && $position !== null && count($row['history'] ?? []) <= 1)
                                                    <span class="badge bg-primary-subtle text-primary">{{ __('ai.research.new') }}</span>
                                                @elseif($position !== null && $previous !== null && $position < $previous)
                                                    <span class="text-success fw-bold"><i class="ti ti-arrow-up" aria-hidden="true"></i> {{ __('ai.research.up', ['count' => $previous - $position]) }}</span>
                                                @elseif($position !== null && $previous !== null && $position > $previous)
                                                    <span class="text-danger fw-bold"><i class="ti ti-arrow-down" aria-hidden="true"></i> {{ __('ai.research.down', ['count' => $position - $previous]) }}</span>
                                                @elseif($position !== null && $previous !== null)
                                                    <span class="text-muted">{{ __('ai.research.same') }}</span>
                                                @endif
                                            </td>
                                            <td class="small text-muted text-nowrap">{{ $date($row['checked_at'] ?? null) }}</td>
                                            <td class="text-end">
                                                <form method="POST" action="{{ route('admin.ai.research.keywords.destroy', ['keyword' => $row['id']]) }}" onsubmit="return confirm(@js(__('ai.research.untrack_confirm')))">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('ai.research.remove') }}</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if(count($research['tracked']) < $maxTracked)
                        <form method="POST" action="{{ route('admin.ai.research.keywords.store') }}">
                            @csrf
                            <label class="form-label fw-semibold" for="research-keywords">{{ __('ai.research.keywords_label') }}</label>
                            <textarea class="form-control mb-2" id="research-keywords" name="keywords" rows="3" maxlength="5000" required>{{ old('keywords') }}</textarea>
                            <button class="btn btn-primary" type="submit"><i class="ti ti-plus me-1" aria-hidden="true"></i>{{ __('ai.research.add_keywords') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    @php
        $researchText = [
            'almost' => __('ai.research.almost'),
            'overdue' => __('ai.research.overdue'),
            'stalled' => __('ai.research.stalled'),
            'done' => __('ai.research.done'),
            'processing' => __('ai.research.processing'),
        ];
    @endphp
    <script>
        (function () {
            const text = @json($researchText);

            // A button pressed once: disabled with a spinner, so a slow answer is not sent twice.
            document.querySelectorAll('.research-page form, form[action*="/ai/research"]').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (event.defaultPrevented) return;
                    const button = form.querySelector('button[type="submit"]');
                    if (!button) return;
                    setTimeout(function () {
                        button.disabled = true;
                        button.innerHTML = '<i class="ti ti-loader-2 research-spin me-1" aria-hidden="true"></i>' + text.processing;
                    }, 0);
                });
            });

            const box = document.querySelector('[data-research-progress]');
            if (!box) return;

            // The gateway's clock, so a reload or a wrong computer clock keeps the same countdown.
            let offset = Date.parse(box.dataset.now) - Date.now();
            const start = Date.parse(box.dataset.start);
            const deadline = start + Number(box.dataset.estimate) * 1000;
            const clock = box.querySelector('[data-research-countdown]');
            const label = box.querySelector('[data-research-label]');
            const note = box.querySelector('[data-research-note]');
            const bar = box.querySelector('[data-research-bar]');
            let finished = false;

            const pad = (n) => String(n).padStart(2, '0');
            const tick = function () {
                if (finished) return;
                const now = Date.now() + offset;
                const left = Math.ceil((deadline - now) / 1000);
                const share = (now - start) / (deadline - start);
                bar.style.width = Math.max(3, Math.min(95, share * 100)) + '%';

                if (left > 0) {
                    clock.textContent = Math.floor(left / 60) + ':' + pad(left % 60);
                    note.classList.add('d-none');
                } else {
                    label.classList.add('invisible');
                    clock.textContent = text.almost;
                    clock.style.fontSize = '1.1rem';
                    // Past the estimate: say so plainly, never a technical reason.
                    note.textContent = -left > 240 ? text.stalled : (-left > 30 ? text.overdue : '');
                    note.classList.toggle('d-none', note.textContent === '');
                }
            };

            const poll = async function () {
                try {
                    const response = await fetch(box.dataset.researchProgress, {headers: {'Accept': 'application/json'}});
                    const json = await response.json();
                    if (json.success) {
                        offset = Date.parse(json.data.now) - Date.now();
                        if (json.data.pending.length === 0) {
                            finished = true;
                            bar.style.width = '100%';
                            label.classList.add('invisible');
                            clock.style.fontSize = '1.1rem';
                            clock.textContent = text.done;
                            setTimeout(() => window.location.reload(), 800);
                            return;
                        }
                    }
                } catch (e) {
                    // Offline for a moment: keep counting and ask again.
                }
                setTimeout(poll, 5000);
            };

            tick();
            setInterval(tick, 1000);
            setTimeout(poll, 5000);
        })();
    </script>
@endpush
