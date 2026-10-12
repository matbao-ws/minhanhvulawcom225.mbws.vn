{{-- Google Analytics section. Filled by fetch() after the page renders, so a
     slow or failing Google never delays the dashboard itself. --}}
@php
    $analyticsMetrics = [
        'users' => ['icon' => 'ti-users', 'tone' => 'primary'],
        'sessions' => ['icon' => 'ti-activity-heartbeat', 'tone' => 'info'],
        'pageviews' => ['icon' => 'ti-file-text', 'tone' => 'purple'],
        'avg_session' => ['icon' => 'ti-clock-hour-4', 'tone' => 'warning'],
        'conversion' => ['icon' => 'ti-chart-arrows-vertical', 'tone' => 'success'],
    ];
    $analyticsSettings = app(\App\Services\Analytics\AnalyticsReport::class)->settings();
    $analyticsConnected = filled($analyticsSettings['property_id']);
@endphp

@push('styles')
    <style>
        .dashboard-analytics .analytics-metric-card {
            --metric-color: var(--bs-primary-rgb);
            position: relative;
            isolation: isolate;
            min-height: 128px;
            padding: 1.15rem;
            overflow: hidden;
            border: 1px solid rgba(var(--metric-color), .16) !important;
            border-radius: 1rem;
            background: linear-gradient(135deg, rgba(var(--metric-color), .09), #fff 52%);
            box-shadow: 0 .35rem 1.2rem rgba(33, 37, 41, .045);
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }

        .dashboard-analytics .analytics-metric-card:hover {
            transform: translateY(-3px);
            border-color: rgba(var(--metric-color), .34) !important;
            box-shadow: 0 .75rem 1.75rem rgba(33, 37, 41, .10);
        }

        .dashboard-analytics .analytics-metric-card::after {
            position: absolute;
            z-index: -1;
            top: -2.25rem;
            right: -1.5rem;
            width: 7rem;
            height: 7rem;
            content: '';
            background: rgba(var(--metric-color), .08);
            border-radius: 50%;
        }

        .dashboard-analytics .analytics-metric-card--primary { --metric-color: var(--bs-primary-rgb); }
        .dashboard-analytics .analytics-metric-card--info { --metric-color: var(--bs-info-rgb); }
        .dashboard-analytics .analytics-metric-card--purple { --metric-color: 111, 66, 193; }
        .dashboard-analytics .analytics-metric-card--warning { --metric-color: var(--bs-warning-rgb); }
        .dashboard-analytics .analytics-metric-card--success { --metric-color: var(--bs-success-rgb); }

        .dashboard-analytics .analytics-metric-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.35rem;
            height: 2.35rem;
            color: rgb(var(--metric-color));
            background: rgba(var(--metric-color), .13);
            border-radius: .75rem;
        }

        .dashboard-analytics .analytics-metric-value {
            color: #25324a;
            font-size: clamp(1.35rem, 1.1rem + .6vw, 1.8rem);
            font-variant-numeric: tabular-nums;
            letter-spacing: -.035em;
            line-height: 1.15;
        }

        .dashboard-analytics .analytics-metric-card > [data-analytics-total] {
            margin-top: .85rem;
            color: #25324a;
            font-size: clamp(1.35rem, 1.1rem + .6vw, 1.8rem);
            font-variant-numeric: tabular-nums;
            letter-spacing: -.035em;
            line-height: 1.15;
        }

        .dashboard-analytics [data-analytics-picker] .dropdown-item {
            transition: background-color .16s ease, color .16s ease;
        }

        .dashboard-analytics [data-analytics-picker] .dropdown-item:hover,
        .dashboard-analytics [data-analytics-picker] .dropdown-item.active {
            color: #fff !important;
            background-color: var(--bs-danger) !important;
        }

        .dashboard-analytics [data-analytics-picker] .dropdown-item:focus-visible {
            color: #fff !important;
            background-color: var(--bs-danger) !important;
            outline: 0;
            box-shadow: 0 0 0 .2rem rgba(var(--bs-danger-rgb), .24);
        }

        .dashboard-analytics [data-analytics-picker] > [data-bs-toggle="dropdown"] {
            transition: background-color .16s ease, border-color .16s ease, color .16s ease;
        }

        .dashboard-analytics [data-analytics-picker] > [data-bs-toggle="dropdown"]:hover,
        .dashboard-analytics [data-analytics-picker] > [data-bs-toggle="dropdown"].show,
        .dashboard-analytics [data-analytics-picker] > [data-bs-toggle="dropdown"]:focus-visible {
            color: #fff !important;
            background-color: var(--bs-danger) !important;
            border-color: var(--bs-danger) !important;
            box-shadow: 0 0 0 .2rem rgba(var(--bs-danger-rgb), .24);
        }

        .dashboard-analytics [data-analytics-picker] > [data-bs-toggle="dropdown"] .text-muted {
            color: inherit !important;
        }

        .dashboard-analytics [data-analytics-picker] .dropdown-menu {
            z-index: 8;
        }

        .dashboard-analytics [data-analytics-toggle]:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            pointer-events: none;
        }
    </style>
@endpush

<section data-tour="dashboard.analytics" class="card mb-4 dashboard-analytics" style="border-radius: 20px !important; overflow: visible !important"
    aria-label="{{ __('admin.analytics.title') }}" data-analytics-url="{{ route('admin.dashboard.analytics') }}"
    data-analytics-refresh-url="{{ route('admin.dashboard.analytics.refresh') }}">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-0"><i class="ti ti-chart-line me-1"
                        aria-hidden="true"></i>{{ __('admin.analytics.title') }}</h5>
                <div class="small text-muted" data-analytics-meta></div>
            </div>
            <div class="d-flex align-items-center gap-2" data-analytics-controls>
                <div class="d-flex align-items-center gap-2" data-analytics-options>
                    <span class="badge bg-success-subtle text-success d-none" data-analytics-online></span>
                    <div class="dropdown" data-analytics-picker>
                        <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle d-flex align-items-center gap-2"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                            aria-label="{{ __('admin.analytics.range_pick') }}">
                            <i class="ti ti-calendar" aria-hidden="true"></i>
                            <span data-analytics-range-label>{{ __('admin.analytics.range_' . \App\Services\Analytics\AnalyticsReport::DEFAULT_PRESET) }}</span>
                            <span class="text-muted d-none d-md-inline" data-analytics-range-dates></span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 280px;">
                            @foreach (\App\Services\Analytics\AnalyticsReport::PRESETS as $preset)
                                <button type="button"
                                    class="dropdown-item rounded {{ $preset === \App\Services\Analytics\AnalyticsReport::DEFAULT_PRESET ? 'active' : '' }}"
                                    data-analytics-preset="{{ $preset }}">
                                    {{ __('admin.analytics.range_' . $preset) }}
                                </button>
                            @endforeach
                            <div class="dropdown-divider"></div>
                            <div class="px-2 pb-1">
                                <div class="small fw-semibold mb-2">{{ __('admin.analytics.range_custom') }}</div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small mb-1" for="analytics-from">{{ __('admin.analytics.range_from') }}</label>
                                        <input type="date" class="form-control form-control-sm" id="analytics-from"
                                            data-analytics-from max="{{ now(config('analytics.timezone'))->toDateString() }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small mb-1" for="analytics-to">{{ __('admin.analytics.range_to') }}</label>
                                        <input type="date" class="form-control form-control-sm" id="analytics-to"
                                            data-analytics-to max="{{ now(config('analytics.timezone'))->toDateString() }}">
                                    </div>
                                </div>
                                <div class="small text-danger mt-2 d-none" data-analytics-range-error role="alert"></div>
                                <button type="button" class="btn btn-sm btn-primary w-100 mt-2" data-analytics-apply>{{ __('admin.analytics.range_apply') }}</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-light" data-analytics-refresh
                        title="{{ __('admin.analytics.refresh') }}">
                        <i class="ti ti-refresh" aria-hidden="true"></i><span
                            class="visually-hidden">{{ __('admin.analytics.refresh') }}</span>
                    </button>
                </div>
                <button type="button" class="btn btn-sm btn-light" data-analytics-toggle aria-expanded="false"
                    aria-controls="analytics-detail"
                    title="{{ ! $analyticsConnected ? __('admin.analytics.setup_title') : __('admin.analytics.expand') }}"
                    {{ ! $analyticsConnected ? 'disabled' : '' }}>
                    <i class="ti ti-chevron-down" aria-hidden="true" data-analytics-toggle-icon></i>
                    <span class="d-none d-sm-inline"
                        data-analytics-toggle-label>{{ __('admin.analytics.expand') }}</span>
                </button>
            </div>
        </div>

        <div>
            <div class="text-muted small py-4 text-center" data-analytics-loading>{{ __('admin.analytics.loading') }}
            </div>

            {{-- Shown when the shop has not connected GA or Google refuses. --}}
            <div class="d-none" data-analytics-error>
                <div class="alert alert-light border d-flex flex-column flex-md-row align-items-md-center gap-3 mb-3">
                    <i class="ti ti-chart-dots fs-6 text-primary" aria-hidden="true"></i>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-primary" data-analytics-error-title>{{ __('admin.analytics.setup_title') }}
                        </div>
                        <div class="small text-muted" data-analytics-error-message></div>
                    </div>
                    @can('settings.view')
                        <a href="{{ route('admin.settings.index') }}#analytics-pane"
                            class="btn btn-sm btn-primary text-nowrap">{{ __('admin.analytics.setup_button') }}</a>
                    @endcan
                </div>
            </div>

            <div class="d-none" data-analytics-body>
                <div class="row g-3 mb-3">
                    @foreach (array_slice($analyticsMetrics, 0, 4, true) as $metric => $style)
                        <div class="col-6 col-lg">
                            <div class="analytics-metric-card analytics-metric-card--{{ $style['tone'] }} h-100">
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <div class="small fw-semibold text-secondary">{{ __('admin.analytics.' . $metric) }}</div>
                                    <span class="analytics-metric-icon" aria-hidden="true"><i class="ti {{ $style['icon'] }} fs-5"></i></span>
                                </div>
                                <div class="fs-5 fw-bold" data-analytics-total="{{ $metric }}">—</div>
                            </div>
                        </div>
                    @endforeach
                    <div class="col-6 col-lg">
                        <div class="analytics-metric-card analytics-metric-card--{{ $analyticsMetrics['conversion']['tone'] }} h-100">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div class="small fw-semibold text-secondary">{{ __('admin.analytics.conversion') }}</div>
                                <span class="analytics-metric-icon" aria-hidden="true"><i class="ti {{ $analyticsMetrics['conversion']['icon'] }} fs-5"></i></span>
                            </div>
                            <div class="fs-5 fw-bold" data-analytics-total="conversion">—</div>
                            <div class="small text-muted" data-analytics-conversion-help></div>
                        </div>
                    </div>
                </div>

                <div id="analytics-detail" data-analytics-panel>
                    <div id="analyticsTrafficChart" class="mb-3" style="min-height: 280px;"></div>

                    <div class="row g-4">
                        <div class="col-lg-7">
                            <h6 class="fw-semibold mb-2">{{ __('admin.analytics.top_pages') }}</h6>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <tbody data-analytics-pages></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <h6 class="fw-semibold mb-2">{{ __('admin.analytics.channels') }}</h6>
                            <div data-analytics-channels></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@php
    $analyticsText = [
        'online' => __('admin.analytics.online', ['count' => '__COUNT__']),
        'conversionHelp' => __('admin.analytics.conversion_help', [
            'orders' => '__ORDERS__',
            'sessions' => '__SESSIONS__',
        ]),
        'noData' => __('admin.analytics.no_data'),
        'updatedAt' => __('admin.analytics.updated_at', ['time' => '__TIME__']),
        'users' => __('admin.analytics.users'),
        'sessions' => __('admin.analytics.sessions'),
        'pageviews' => __('admin.analytics.pageviews'),
        'setupTitle' => __('admin.analytics.setup_title'),
        'notConfigured' => __('admin.analytics.setup_body'),
        'collapse' => __('admin.analytics.collapse'),
        'custom' => __('admin.analytics.range_custom'),
        'bothDates' => __('admin.analytics.range_both_dates'),
        'invalidRange' => __('admin.analytics.invalid_range', ['days' => config('analytics.max_days')]),
        'presets' => collect(\App\Services\Analytics\AnalyticsReport::PRESETS)
            ->mapWithKeys(fn($preset) => [$preset => __('admin.analytics.range_' . $preset)])
            ->all(),
        'expand' => __('admin.analytics.expand'),
    ];
@endphp
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const section = document.querySelector('.dashboard-analytics');
            if (!section) return;

            const t = @json($analyticsText);
            const q = (selector) => section.querySelector(selector);
            const panel = q('[data-analytics-panel]');
            const toggle = q('[data-analytics-toggle]');
            const toggleIcon = q('[data-analytics-toggle-icon]');
            const toggleLabel = q('[data-analytics-toggle-label]');
            const number = new Intl.NumberFormat(@json(app()->getLocale()));
            // Remembered per browser; a blocked or full localStorage just means the default range.
            const STORAGE_KEY = 'analytics.range';
            const readSelection = () => {
                try {
                    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
                    if (saved && (t.presets[saved.preset] || (saved.from && saved.to))) return saved;
                } catch (error) { /* ignore */ }
                return {preset: @json(\App\Services\Analytics\AnalyticsReport::DEFAULT_PRESET)};
            };
            let selection = readSelection();
            let chart = null;
            // Only the newest request may paint: changing the range mid-load must not be overwritten by the older answer.
            let loadTicket = 0;

            const escape = (value) => String(value).replace(/[&<>"']/g, (ch) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [ch]));
            const duration = (seconds) => Math.floor(seconds / 60) + 'm ' + String(seconds % 60).padStart(2, '0') +
                's';

            const show = (state) => {
                q('[data-analytics-loading]').classList.toggle('d-none', state !== 'loading');
                q('[data-analytics-error]').classList.toggle('d-none', state !== 'error');
                q('[data-analytics-body]').classList.remove('d-none');

                if (state === 'ready') {
                    toggle.disabled = false;
                    toggle.title = toggle.getAttribute('aria-expanded') === 'true' ? t.collapse : t.expand;
                } else if (state === 'error') {
                    toggle.disabled = true;
                    toggle.title = t.setupTitle;
                    setExpanded(false);
                }
            };

            let chartOptions = null;

            // Drawn when there is data AND the panel is open; called again on expand.
            const drawChart = () => {
                if (!chartOptions || panel.hidden || !window.ApexCharts) return;
                if (chart) {
                    chart.updateOptions(chartOptions);
                } else {
                    chart = new ApexCharts(document.querySelector('#analyticsTrafficChart'), chartOptions);
                    chart.render();
                }
            };

            const render = (report) => {
                q('[data-analytics-total="users"]').textContent = number.format(report.totals.users);
                q('[data-analytics-total="sessions"]').textContent = number.format(report.totals.sessions);
                q('[data-analytics-total="pageviews"]').textContent = number.format(report.totals.pageviews);
                q('[data-analytics-total="avg_session"]').textContent = duration(report.totals
                    .avg_session_seconds);
                q('[data-analytics-total="conversion"]').textContent = report.conversion_rate === null ? '—' :
                    report.conversion_rate + '%';
                q('[data-analytics-conversion-help]').textContent = t.conversionHelp
                    .replace('__ORDERS__', number.format(report.orders))
                    .replace('__SESSIONS__', number.format(report.totals.sessions));

                const online = q('[data-analytics-online]');
                online.classList.toggle('d-none', report.realtime === null);
                online.textContent = t.online.replace('__COUNT__', number.format(report.realtime ?? 0));

                q('[data-analytics-meta]').textContent = t.updatedAt.replace('__TIME__', new Date(report
                    .fetched_at).toLocaleString(@json(app()->getLocale())));

                q('[data-analytics-pages]').innerHTML = report.top_pages.length ?
                    report.top_pages.map((page) =>
                        '<tr><td class="text-truncate" style="max-width: 420px;"><div class="fw-semibold text-truncate">' +
                        escape(page.title || page.path) + '</div><div class="small text-muted text-truncate">' +
                        escape(page.path) +
                        '</div></td><td class="text-end fw-semibold text-nowrap">' + number.format(page.views) +
                        '</td></tr>').join('') :
                    '<tr><td class="text-muted small">' + escape(t.noData) + '</td></tr>';

                const maxChannel = Math.max(1, ...report.channels.map((channel) => channel.sessions));
                q('[data-analytics-channels]').innerHTML = report.channels.length ?
                    report.channels.map((channel) =>
                        '<div class="mb-2"><div class="d-flex justify-content-between small mb-1"><span>' +
                        escape(channel.name) + '</span><span class="fw-semibold">' + number.format(channel
                            .sessions) +
                        '</span></div><div class="progress" style="height: 6px;"><div class="progress-bar" style="width: ' +
                        Math.round(channel.sessions / maxChannel * 100) + '%"></div></div></div>').join('') :
                    '<div class="text-muted small">' + escape(t.noData) + '</div>';

                const options = {
                    chart: {
                        type: 'area',
                        height: 280,
                        toolbar: {
                            show: false
                        },
                        fontFamily: 'inherit'
                    },
                    series: [{
                            name: t.users,
                            data: report.daily.users
                        },
                        {
                            name: t.sessions,
                            data: report.daily.sessions
                        },
                        {
                            name: t.pageviews,
                            data: report.daily.pageviews
                        },
                    ],
                    xaxis: {
                        categories: report.daily.dates
                    },
                    dataLabels: {
                        enabled: false
                    },
                    stroke: {
                        curve: 'smooth',
                        width: 2
                    },
                    fill: {
                        type: 'gradient',
                        gradient: {
                            opacityFrom: 0.25,
                            opacityTo: 0.02
                        }
                    },
                    legend: {
                        position: 'top'
                    },
                };
                chartOptions = options;
                drawChart();
            };

            const load = async (refresh = false) => {
                const ticket = ++loadTicket;
                show('loading');
                try {
                    const url = new URL(refresh ? section.dataset.analyticsRefreshUrl : section.dataset
                        .analyticsUrl);
                    if (selection.preset) {
                        url.searchParams.set('range', selection.preset);
                    } else {
                        url.searchParams.set('from', selection.from);
                        url.searchParams.set('to', selection.to);
                    }
                    const response = await fetch(url, {
                        method: refresh ? 'POST' : 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': @json(csrf_token())
                        },
                    });
                    const data = await response.json().catch(() => ({}));
                    if (ticket !== loadTicket) return;
                    if (response.status === 422 && data.reason === 'invalid_range') {
                        show('ready');
                        openPickerWithError(data.message);
                        return;
                    }
                    if (!response.ok || !data.success) {
                        q('[data-analytics-error-title]').textContent = t.setupTitle;
                        q('[data-analytics-error-message]').textContent = data.reason === 'not_configured' ?
                            t.notConfigured : (data.message || response.statusText);
                        show('error');
                        return;
                    }
                    show('ready');
                    render(data.report);
                    paintRange(data.report.range);
                } catch (error) {
                    if (ticket !== loadTicket) return;
                    q('[data-analytics-error-message]').textContent = error.message;
                    show('error');
                }
            };

            const picker = q('[data-analytics-picker]');
            const fromInput = q('[data-analytics-from]');
            const toInput = q('[data-analytics-to]');
            const rangeError = q('[data-analytics-range-error]');
            const dropdown = () => bootstrap.Dropdown.getOrCreateInstance(picker.querySelector('[data-bs-toggle="dropdown"]'));
            const dateFormat = new Intl.DateTimeFormat(@json(app()->getLocale()), {day: 'numeric', month: 'short', year: 'numeric'});
            // Dates arrive as Y-m-d in the shop's timezone; parse at local noon so the browser's zone cannot shift the day.
            const formatDay = (value) => dateFormat.format(new Date(value + 'T12:00:00'));

            const showRangeError = (message) => {
                rangeError.textContent = message || '';
                rangeError.classList.toggle('d-none', !message);
            };

            const openPickerWithError = (message) => {
                showRangeError(message);
                dropdown().show();
            };

            const paintRange = (range) => {
                q('[data-analytics-range-label]').textContent = range.preset && range.preset !== 'custom' ? t.presets[range.preset] : t.custom;
                q('[data-analytics-range-dates]').textContent = range.from === range.to
                    ? formatDay(range.from)
                    : formatDay(range.from) + ' – ' + formatDay(range.to);
                picker.querySelectorAll('[data-analytics-preset]').forEach((item) => item.classList.toggle('active', item.dataset.analyticsPreset === range.preset));
                fromInput.value = range.from;
                toInput.value = range.to;
                showRangeError('');
            };

            const choose = (next) => {
                selection = next;
                try { localStorage.setItem(STORAGE_KEY, JSON.stringify(next)); } catch (error) { /* ignore */ }
                dropdown().hide();
                load();
            };

            picker.querySelectorAll('[data-analytics-preset]').forEach((item) => {
                item.addEventListener('click', () => choose({preset: item.dataset.analyticsPreset}));
            });

            q('[data-analytics-apply]').addEventListener('click', () => {
                if (!fromInput.value || !toInput.value) return showRangeError(t.bothDates);
                if (fromInput.value > toInput.value) return showRangeError(t.invalidRange);
                choose({from: fromInput.value, to: toInput.value});
            });

            // Show the remembered choice before the first report arrives.
            if (selection.preset) {
                q('[data-analytics-range-label]').textContent = t.presets[selection.preset];
                picker.querySelectorAll('[data-analytics-preset]').forEach((item) => item.classList.toggle('active', item.dataset.analyticsPreset === selection.preset));
            } else {
                paintRange({preset: 'custom', from: selection.from, to: selection.to});
            }
            q('[data-analytics-refresh]').addEventListener('click', () => load(true));

            const setExpanded = (expanded) => {
                if (toggle.disabled && expanded) return;
                panel.hidden = !expanded;
                toggle.setAttribute('aria-expanded', String(expanded));
                toggle.title = expanded ? t.collapse : t.expand;
                toggleLabel.textContent = expanded ? t.collapse : t.expand;
                toggleIcon.classList.toggle('ti-chevron-up', expanded);
                toggleIcon.classList.toggle('ti-chevron-down', !expanded);

                if (expanded) drawChart();
            };

            toggle.addEventListener('click', () => {
                if (toggle.disabled) return;
                setExpanded(toggle.getAttribute('aria-expanded') !== 'true');
            });
            setExpanded(false);
            if (toggle.disabled) {
                toggle.title = t.setupTitle;
            }

            // The figures sit above the fold of the collapsible panel, so fetch them straight away.
            load();
        });
    </script>
@endpush
