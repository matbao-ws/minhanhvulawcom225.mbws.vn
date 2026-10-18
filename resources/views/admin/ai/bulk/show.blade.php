@extends('admin.layouts.app')

@section('title', __('ai.bulk.review_title'))

@push('styles')
    <style>
        /* Same colour per kind as the bulk list; red is left for errors only. */
        .ai-job {
            --kind-color: #3e66fb;
            --kind-soft: #ecf2ff;
        }
        .ai-job.is-seo-fix {
            --kind-color: #b06000;
            --kind-soft: #fef7e0;
        }
        .ai-job.is-alt {
            --kind-color: #007b83;
            --kind-soft: #e4f7fb;
        }
        .ai-job-summary {
            border: 1px solid #e5eaf2;
            border-top: 3px solid var(--kind-color);
            box-shadow: none;
        }
        .ai-job-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 16px;
        }
        .ai-job-stat {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .ai-job-stat-icon {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--kind-soft);
            color: var(--kind-color);
            font-size: 21px;
        }
        .ai-job-stat-label {
            color: #46546a;
            font-size: 13px;
            font-weight: 600;
        }
        .ai-job-stat-value {
            color: #1f2633;
            font-size: 16px;
            font-weight: 700;
        }
        .ai-job-note {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            margin-bottom: 14px;
            padding: 12px 16px;
            border-radius: 10px;
            border-left: 4px solid #3e66fb;
            background: #fff;
            color: #2a3547;
            font-size: 14.5px;
            font-weight: 500;
            line-height: 1.5;
            box-shadow: 0 1px 4px rgba(42, 53, 71, 0.08);
        }
        .ai-job-note i {
            color: #3e66fb;
            font-size: 19px;
            line-height: 1.2;
        }
        .ai-job-toolbar {
            position: sticky;
            top: 80px;
            z-index: 5;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            padding: 10px 16px;
            border: 1px solid #e5eaf2;
            border-radius: 10px;
            background: #fff;
        }
        .ai-job-toolbar label {
            margin: 0;
            color: #2a3547 !important;
        }
        .ai-job-counter {
            color: #46546a;
            font-size: 14px;
            font-weight: 600;
        }
        .ai-job-item {
            border: 1px solid #e5eaf2;
            border-left: 4px solid var(--kind-color);
            box-shadow: none;
            transition: opacity 0.15s ease;
        }
        .ai-job-item.is-skipped {
            opacity: 0.55;
            border-left-color: #c9d1de;
        }
        .ai-job-item-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }
        .ai-job-type {
            padding: 2px 10px;
            border-radius: 999px;
            background: var(--kind-soft);
            color: var(--kind-color);
            font-size: 12.5px;
            font-weight: 700;
        }
        .ai-job-item-title {
            color: #1f2633;
            font-size: 15px;
            font-weight: 700;
        }
        .ai-job-field-label {
            display: block;
            margin: 8px 0 4px;
            color: #2a3547 !important;
            font-size: 13.5px !important;
            font-weight: 700 !important;
        }
        .ai-job-current {
            margin-top: 6px;
            padding: 6px 10px;
            border-radius: 6px;
            background: #f4f7fb;
            color: #3d4a5c;
            font-size: 13.5px;
            line-height: 1.5;
        }
        .ai-job-current strong {
            color: #2a3547;
        }
        .ai-job-intro {
            padding: 10px 12px;
            border: 1px dashed #c9d1de;
            border-radius: 8px;
            background: #fbfcfe;
            color: #2a3547;
            font-size: 14px;
        }
        .ai-job-notes {
            margin: 10px 0 0;
            padding: 8px 12px 8px 30px;
            border-radius: 8px;
            background: #fef7e0;
            color: #7a4500;
            font-size: 13.5px;
        }
    </style>
@endpush

@section('content')
    <x-admin.page-header :title="__('ai.bulk.review_title')">
        <x-slot:breadcrumbs>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.dashboard') }}">{{ __('admin.home') }}</a></li>
            <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="{{ route('admin.ai.bulk.index') }}">{{ __('ai.bulk.title') }}</a></li>
            <li class="breadcrumb-item active" style="color: rgba(255, 255, 255, 0.9) !important;" aria-current="page">{{ __('ai.bulk.review_title') }}</li>
        </x-slot:breadcrumbs>
    </x-admin.page-header>

    @foreach(['success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $class)
        @if(session($key))
            <div class="alert alert-{{ $class }}" role="alert">{{ session($key) }}</div>
        @endif
    @endforeach
    @if($error)
        <div class="alert alert-warning" role="alert">{{ $error }}</div>
    @endif

    @php
        $kind = $job->kind();
        $kindIcons = ['meta' => 'ti-tags', 'seo_fix' => 'ti-chart-arrows-vertical', 'alt' => 'ti-photo-check'];
        $statusBadges = ['queued' => 'warning', 'running' => 'info', 'completed' => 'success', 'stopped' => 'danger'];
    @endphp

    <div class="ai-job is-{{ str_replace('_', '-', $kind) }}">
        <div data-tour="ai-bulk-job.summary" class="card ai-job-summary">
            <div class="card-body">
                <div class="ai-job-stats">
                    <div class="ai-job-stat">
                        <span class="ai-job-stat-icon"><i class="ti {{ $kindIcons[$kind] ?? 'ti-sparkles' }}" aria-hidden="true"></i></span>
                        <div>
                            <div class="ai-job-stat-label">{{ __('ai.bulk.kind') }}</div>
                            <div class="ai-job-stat-value">{{ __('ai.bulk.kinds.'.$kind.'.title') }}</div>
                        </div>
                    </div>
                    <div class="ai-job-stat">
                        <span class="ai-job-stat-icon"><i class="ti ti-progress-check" aria-hidden="true"></i></span>
                        <div>
                            <div class="ai-job-stat-label">{{ __('ai.bulk.status') }}</div>
                            <div class="ai-job-stat-value"><span class="badge bg-{{ $statusBadges[$job->status] ?? 'info' }}">{{ __('ai.bulk.statuses.'.$job->status) }}</span></div>
                        </div>
                    </div>
                    <div class="ai-job-stat">
                        <span class="ai-job-stat-icon"><i class="ti ti-files" aria-hidden="true"></i></span>
                        <div>
                            <div class="ai-job-stat-label">{{ __('ai.bulk.items') }}</div>
                            <div class="ai-job-stat-value">{{ $job->items_total }}</div>
                        </div>
                    </div>
                    <div class="ai-job-stat">
                        <span class="ai-job-stat-icon"><i class="ti ti-bolt" aria-hidden="true"></i></span>
                        <div>
                            <div class="ai-job-stat-label">{{ __('ai.bulk.credits') }}</div>
                            <div class="ai-job-stat-value">{{ $job->credits_estimated }}</div>
                        </div>
                    </div>
                </div>
                @if($job->stop_reason)
                    <div class="alert alert-danger mt-3 mb-0" role="alert"><i class="ti ti-alert-triangle me-1" aria-hidden="true"></i>{{ __('ai.errors.'.$job->stop_reason) }}</div>
                @endif
            </div>
        </div>

        @if(! $job->isFinished())
            <div data-tour="ai-bulk-job.running" class="alert alert-info d-flex align-items-center" role="status">
                <span class="spinner-border spinner-border-sm me-2"></span>{{ __('ai.bulk.running') }}
                <a class="btn btn-sm btn-outline-info ms-auto" href="{{ url()->current() }}"><i class="ti ti-refresh me-1" aria-hidden="true"></i>{{ __('ai.bulk.reload') }}</a>
            </div>
        @elseif($suggestions->isEmpty())
            <div class="alert alert-success" role="status"><i class="ti ti-circle-check me-1" aria-hidden="true"></i>{{ __('ai.bulk.nothing_left') }}</div>
        @elsecannot('ai.apply')
            <div class="alert alert-info" role="status">{{ __('ai.bulk.waiting_review', ['count' => $suggestions->count()]) }} {{ __('ai.errors.cannot_apply') }}</div>
        @else
            <form method="POST" action="{{ route('admin.ai.bulk.apply', ['job' => $job->id]) }}">
                @csrf
                <div class="ai-job-note"><i class="ti ti-info-circle" aria-hidden="true"></i><span>{{ __('ai.bulk.review_note') }}</span></div>
                <div class="ai-job-toolbar">
                    <label data-tour="ai-bulk-job.select-all" class="form-check d-flex align-items-center gap-2"><input class="form-check-input m-0" type="checkbox" data-check-all checked>{{ __('ai.bulk.select_all') }}</label>
                    <span class="ai-job-counter" data-choice-counter></span>
                    <button data-tour="ai-bulk-job.apply" type="submit" class="btn btn-success btn-sm ms-auto"><i class="ti ti-check me-1"></i>{{ __('ai.bulk.apply') }}</button>
                </div>

                @foreach($suggestions as $suggestion)
                    @php
                        $option = $suggestion->options[0] ?? [];
                        $known = $titles->get($suggestion->id);
                        $id = $suggestion->id;
                    @endphp
                    <div data-tour="ai-bulk-job.item" class="card ai-job-item mb-2" data-choice-card>
                        <div class="card-body py-3">
                            <div class="d-flex gap-3 align-items-start">
                                <input class="form-check-input mt-1 flex-shrink-0" type="checkbox" name="chosen[]" value="{{ $id }}" checked data-choice aria-label="{{ __('ai.bulk.choose') }}">
                                <div class="flex-grow-1 min-w-0">
                                    <div class="ai-job-item-head">
                                        <span class="ai-job-type">{{ __('ai.bulk.types.'.$suggestion->entity_type) }}</span>
                                        <span class="ai-job-item-title">{{ $known['title'] ?? '#'.$suggestion->entity_id }}</span>
                                    </div>

                                    @if($kind === 'meta')
                                        <label class="ai-job-field-label" for="ai-title-{{ $id }}">{{ __('ai.meta.meta_title') }}</label>
                                        <input type="text" class="form-control form-control-sm" id="ai-title-{{ $id }}" name="title[{{ $id }}]" value="{{ $option['title'] ?? '' }}" maxlength="{{ \App\Services\AiGateway\MetaSuggestionService::TITLE_MAX }}">
                                        <label class="ai-job-field-label" for="ai-description-{{ $id }}">{{ __('ai.meta.meta_description') }}</label>
                                        <textarea class="form-control form-control-sm" id="ai-description-{{ $id }}" name="description[{{ $id }}]" rows="2" maxlength="{{ \App\Services\AiGateway\MetaSuggestionService::DESCRIPTION_MAX }}">{{ $option['description'] ?? '' }}</textarea>
                                        @if(filled($suggestion->old_values['title'] ?? null) || filled($suggestion->old_values['description'] ?? null))
                                            <div class="ai-job-current"><strong>{{ __('ai.bulk.current') }}:</strong> {{ $suggestion->old_values['title'] ?: '—' }} · {{ $suggestion->old_values['description'] ?: '—' }}</div>
                                        @endif
                                    @elseif($kind === 'seo_fix')
                                        @foreach(['seo_title' => 255, 'seo_description' => 500] as $field => $max)
                                            @if(filled($option[$field] ?? null))
                                                <label class="ai-job-field-label" for="ai-{{ $field }}-{{ $id }}">{{ __('ai.seo.'.$field) }}</label>
                                                <textarea class="form-control form-control-sm" id="ai-{{ $field }}-{{ $id }}" name="{{ $field }}[{{ $id }}]" rows="{{ $field === 'seo_title' ? 1 : 2 }}" maxlength="{{ $max }}">{{ $option[$field] }}</textarea>
                                                <div class="ai-job-current"><strong>{{ __('ai.bulk.current') }}:</strong> {{ $suggestion->old_values[$field] ?: '—' }}</div>
                                            @endif
                                        @endforeach
                                        @if(filled($option['intro_html'] ?? null))
                                            <label class="form-check d-flex align-items-center gap-2 mt-3 mb-2">
                                                <input class="form-check-input m-0" type="checkbox" name="use_intro[{{ $id }}]" value="1">{{ __('ai.bulk.use_intro') }}
                                            </label>
                                            {{-- Server-sanitized HTML (SeoFixService::proposal), shown as the reader will see it. --}}
                                            <div class="ai-job-intro">{!! $option['intro_html'] !!}</div>
                                        @endif
                                        @if(! empty($option['notes']))
                                            <ul class="ai-job-notes">
                                                @foreach($option['notes'] as $note)
                                                    <li>{{ $note['note'] ?? '' }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    @else
                                        <div class="d-flex gap-3 align-items-start">
                                            <img src="{{ $suggestion->old_values['src'] ?? '' }}" alt="" class="rounded border object-fit-cover flex-shrink-0" width="96" height="72" loading="lazy">
                                            <div class="flex-grow-1">
                                                <label class="ai-job-field-label mt-0" for="ai-alt-{{ $id }}">{{ __('ai.alt.title') }}</label>
                                                <input type="text" class="form-control form-control-sm" id="ai-alt-{{ $id }}" name="alt[{{ $id }}]" value="{{ $option['alt'] ?? '' }}" maxlength="250">
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <button type="submit" class="btn btn-success"><i class="ti ti-check me-1"></i>{{ __('ai.bulk.apply') }}</button>
            </form>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const boxes = [...document.querySelectorAll('[data-choice]')];
            const all = document.querySelector('[data-check-all]');
            const counter = document.querySelector('[data-choice-counter]');

            const sync = () => {
                const chosen = boxes.filter((box) => box.checked).length;
                boxes.forEach((box) => box.closest('[data-choice-card]')?.classList.toggle('is-skipped', !box.checked));
                if (counter) counter.textContent = `${chosen}/${boxes.length}`;
                if (all) {
                    all.checked = chosen === boxes.length;
                    all.indeterminate = chosen > 0 && chosen < boxes.length;
                }
            };

            all?.addEventListener('change', function () {
                boxes.forEach((box) => { box.checked = this.checked; });
                sync();
            });
            boxes.forEach((box) => box.addEventListener('change', sync));
            sync();
        });
    </script>
@endpush
