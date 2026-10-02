{{-- Top of "This week's SEO work", loaded after the page so the gateway never slows the dashboard. --}}
@if($seoTasksVisible)
    <div class="card shadow-sm mb-4" data-seo-tasks-card="{{ route('admin.ai.seo-tasks.summary') }}">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-3">
                <i class="ti ti-chart-arrows-vertical fs-6 text-primary"></i>
                <div class="fw-semibold me-auto">{{ __('ai.tasks.title') }}</div>
                <a class="small" href="{{ route('admin.ai.seo-tasks.index') }}">{{ __('ai.tasks.view_all') }}</a>
            </div>
            <div class="small text-muted" data-seo-tasks-body>…</div>
        </div>
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
                        const row = el('div', 'd-flex flex-wrap gap-4 mb-3');
                        [['clicks', text.clicks], ['impressions', text.impressions]].forEach(function ([key, label]) {
                            const now = data.totals.current[key];
                            const delta = change(now, data.totals.previous[key]);
                            const stat = el('div');
                            stat.append(el('div', 'text-muted', label), el('div', 'fs-5 fw-semibold', now.toLocaleString()));
                            if (delta !== null) {
                                stat.append(el('div', delta >= 0 ? 'text-success' : 'text-danger', (delta >= 0 ? '+' : '') + delta + '% ' + text.vs));
                            }
                            row.append(stat);
                        });
                        body.append(row);
                    }

                    if (!data.tasks.length) {
                        body.append(el('span', 'text-success', text.empty));
                        return;
                    }

                    const list = el('ol', 'mb-0 ps-3');
                    data.tasks.forEach(function (task) {
                        const item = el('li', 'mb-1');
                        const title = task.edit_url ? el('a', '', task.title) : el('span', '', task.title);
                        if (task.edit_url) title.href = task.edit_url;
                        item.append(title, el('span', 'text-muted', ' · ' + task.label));
                        if (task.gain > 1) item.append(el('span', 'text-success', ' · ' + text.gain.replace('__N__', task.gain)));
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
