{{-- Top of "This week's SEO work", loaded after the page so the gateway never slows the dashboard. --}}
@if($seoTasksVisible)
    @push('styles')
        <style>
            .dashboard-seo-tasks {
                overflow: hidden;
                border: 1px solid #e8edf7;
                border-radius: 16px;
                box-shadow: 0 7px 22px rgba(44, 58, 87, .06) !important;
            }

            .dashboard-seo-tasks__header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding: 1.1rem 1.25rem;
                border-bottom: 1px solid #edf1f7;
                background: linear-gradient(110deg, #f8f5ff, #fff 62%);
            }

            .dashboard-seo-tasks__heading { display: flex; min-width: 0; align-items: center; gap: .75rem; }
            .dashboard-seo-tasks__icon {
                display: inline-flex;
                width: 2.5rem;
                height: 2.5rem;
                flex: 0 0 2.5rem;
                align-items: center;
                justify-content: center;
                border-radius: .75rem;
                color: #713df2;
                background: #eee8ff;
            }
            .dashboard-seo-tasks__title { color: #2a3547; font-size: 1rem; font-weight: 800; }
            .dashboard-seo-tasks__all { font-weight: 700; white-space: nowrap; }
            .dashboard-seo-tasks__body { padding: 1.1rem 1.25rem 1.25rem; }
            .dashboard-seo-tasks__stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; margin-bottom: 1rem; }
            .dashboard-seo-tasks__stat { padding: .8rem .9rem; border: 1px solid #edf0f6; border-radius: .75rem; background: #fbfcff; }
            .dashboard-seo-tasks__label { color: #8190a8; font-size: .75rem; font-weight: 700; }
            .dashboard-seo-tasks__value { margin-top: .25rem; color: #2a3547; font-size: 1.25rem; font-weight: 800; line-height: 1.15; }
            .dashboard-seo-tasks__delta { margin-top: .35rem; font-size: .75rem; font-weight: 700; }
            .dashboard-seo-tasks__list { display: grid; gap: .55rem; padding: 0; margin: 0; list-style: none; }
            .dashboard-seo-tasks__item { display: flex; align-items: center; gap: .65rem; padding: .7rem .75rem; border-radius: .7rem; background: #fbfcff; }
            .dashboard-seo-tasks__item-icon { color: #713df2; font-size: 1rem; }
            .dashboard-seo-tasks__task { min-width: 0; margin-right: auto; color: #35425b; font-size: .875rem; font-weight: 700; }
            .dashboard-seo-tasks__task:hover { color: #713df2; }
            .dashboard-seo-tasks__tag { padding: .2rem .45rem; border-radius: 999px; color: #627391; background: #edf1f8; font-size: .7rem; font-weight: 700; white-space: nowrap; }
            .dashboard-seo-tasks__gain { color: #198754; font-size: .75rem; font-weight: 800; white-space: nowrap; }
            .dashboard-seo-tasks__empty { display: flex; align-items: center; gap: .5rem; color: #198754; font-size: .875rem; font-weight: 700; }
            @media (max-width: 575.98px) {
                .dashboard-seo-tasks__header, .dashboard-seo-tasks__body { padding-right: 1rem; padding-left: 1rem; }
                .dashboard-seo-tasks__item { align-items: flex-start; flex-wrap: wrap; }
                .dashboard-seo-tasks__tag { margin-left: 1.65rem; }
            }
        </style>
    @endpush

    <div class="card shadow-sm mb-4 dashboard-seo-tasks" data-seo-tasks-card="{{ route('admin.ai.seo-tasks.summary') }}">
        <div class="dashboard-seo-tasks__header">
            <div class="dashboard-seo-tasks__heading">
                <span class="dashboard-seo-tasks__icon" aria-hidden="true"><i class="ti ti-chart-arrows-vertical fs-5"></i></span>
                <div class="dashboard-seo-tasks__title">{{ __('ai.tasks.title') }}</div>
            </div>
            <a class="btn btn-sm btn-outline-primary dashboard-seo-tasks__all" href="{{ route('admin.ai.seo-tasks.index') }}">{{ __('ai.tasks.view_all') }}</a>
        </div>
        <div class="dashboard-seo-tasks__body small text-muted" data-seo-tasks-body aria-live="polite">…</div>
    </div>

    @php
        $seoTasksText = [
            'clicks' => __('ai.tasks.clicks'),
            'impressions' => __('ai.tasks.impressions'),
            'vs' => __('ai.tasks.vs_previous'),
            'pending' => __('ai.tasks.pending'),
            'empty' => __('ai.tasks.empty'),
            'gain' => __('ai.tasks.gain', ['count' => '__N__']),
            'error' => __('ai.errors.default'),
        ];
    @endphp
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', async function () {
                const card = document.querySelector('[data-seo-tasks-card]');
                const body = card?.querySelector('[data-seo-tasks-body]');
                if (!body) return;

                const text = @json($seoTasksText);
                const el = (tag, className, content) => {
                    const node = document.createElement(tag);
                    if (className) node.className = className;
                    if (content !== undefined) node.textContent = content;
                    return node;
                };
                const change = (now, before) => before ? Math.round((now - before) / before * 100) : null;

                try {
                    const response = await fetch(card.dataset.seoTasksCard, {headers: {'Accept': 'application/json'}});
                    const json = await response.json();
                    if (!json.success) {
                        body.textContent = json.message || text.error;
                        return;
                    }

                    const data = json.data;
                    body.textContent = '';
                    body.classList.remove('text-muted');
                    if (!data.synced_through) {
                        body.append(el('span', 'text-muted', text.pending));
                        return;
                    }

                    if (data.totals) {
                        const row = el('div', 'dashboard-seo-tasks__stats');
                        [['clicks', text.clicks], ['impressions', text.impressions]].forEach(function ([key, label]) {
                            const now = data.totals.current[key];
                            const delta = change(now, data.totals.previous[key]);
                            const stat = el('div', 'dashboard-seo-tasks__stat');
                            stat.append(
                                el('div', 'dashboard-seo-tasks__label', label),
                                el('div', 'dashboard-seo-tasks__value', now.toLocaleString())
                            );
                            if (delta !== null) {
                                stat.append(el('div', 'dashboard-seo-tasks__delta ' + (delta >= 0 ? 'text-success' : 'text-danger'), (delta >= 0 ? '+' : '') + delta + '% ' + text.vs));
                            }
                            row.append(stat);
                        });
                        body.append(row);
                    }

                    if (!data.tasks.length) {
                        const empty = el('div', 'dashboard-seo-tasks__empty');
                        empty.append(el('i', 'ti ti-circle-check', undefined), el('span', '', text.empty));
                        body.append(empty);
                        return;
                    }

                    const list = el('ul', 'dashboard-seo-tasks__list');
                    data.tasks.forEach(function (task) {
                        const item = el('li', 'dashboard-seo-tasks__item');
                        const title = task.edit_url ? el('a', 'dashboard-seo-tasks__task text-truncate', task.title) : el('span', 'dashboard-seo-tasks__task text-truncate', task.title);
                        if (task.edit_url) title.href = task.edit_url;
                        item.append(title, el('span', 'text-muted', ' · ' + task.label));
                        if (task.gain > 1) item.append(el('span', 'text-success', ' · ' + text.gain.replace('__N__', task.gain)));
                        const detail = item.querySelector('.text-muted');
                        if (detail) {
                            detail.className = 'dashboard-seo-tasks__tag';
                            detail.textContent = task.label;
                        }
                        const gain = item.querySelector('.text-success');
                        if (gain) {
                            gain.className = 'dashboard-seo-tasks__gain';
                            gain.textContent = '+' + text.gain.replace('__N__', task.gain);
                        }
                        item.prepend(el('i', 'ti ti-arrow-up-right-circle dashboard-seo-tasks__item-icon', undefined));
                        list.append(item);
                    });
                    body.append(list);
                } catch (error) {
                    body.textContent = text.error;
                }
            });
        </script>
    @endpush
@endif
