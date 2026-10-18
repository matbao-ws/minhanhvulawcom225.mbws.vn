@props([
    'title',
    'subtitle' => null,
])
{{-- Optional slots: breadcrumbs, actions, and help (what the page is for, behind a "?" beside the title;
     fixed positioning lets it leave the header card, which clips its overflow). --}}

<div {{ $attributes->class(['row']) }}>
    <div class="col-12">
        <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
            <div class="card-body px-4 py-3">
                <div class="row align-items-center g-3">
                    <div class="{{ isset($actions) && $actions->isNotEmpty() ? 'col-md' : 'col-12' }}">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h4 class="fw-semibold mb-0 text-white">{{ $title }}</h4>
                            @if (isset($help) && $help->isNotEmpty())
                                <div class="dropdown">
                                    <button type="button" class="admin-page-help__toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-popper-config='{"strategy":"fixed","modifiers":[{"name":"offset","options":{"offset":[0,8]}},{"name":"preventOverflow","options":{"padding":16}}]}' aria-expanded="false" aria-label="{{ __('admin.page_help') }}" title="{{ __('admin.page_help') }}">
                                        <i class="ti ti-help" aria-hidden="true"></i>
                                    </button>
                                    <div class="dropdown-menu admin-page-help shadow p-3">{{ $help }}</div>
                                </div>
                                @once
                                    @push('styles')
                                        <style>
                                            /* The "?" beside a page title (x-admin.page-header, help slot) and what it folds out. */
                                            .admin-page-help__toggle {
                                                display: inline-flex;
                                                align-items: center;
                                                justify-content: center;
                                                width: 26px;
                                                height: 26px;
                                                padding: 0;
                                                border: 1px solid rgba(255, 255, 255, 0.45);
                                                border-radius: 50%;
                                                background: rgba(255, 255, 255, 0.12);
                                                color: #fff;
                                                font-size: 15px;
                                                line-height: 1;
                                            }

                                            .admin-page-help__toggle:hover,
                                            .admin-page-help__toggle[aria-expanded="true"] {
                                                background: #fff;
                                                color: #193877;
                                            }

                                            .admin-page-help {
                                                z-index: 1060;
                                                width: min(380px, calc(100vw - 32px));
                                                color: #334155;
                                                font-size: 13px;
                                                line-height: 1.5;
                                            }

                                            .admin-page-help h6 {
                                                margin-bottom: 4px;
                                                color: #1f2937;
                                                font-size: 13px;
                                                font-weight: 700;
                                            }

                                            .admin-page-help ul {
                                                margin: 0 0 10px;
                                                padding-left: 18px;
                                                list-style: disc;
                                            }

                                            .admin-page-help ul:last-child,
                                            .admin-page-help p:last-child {
                                                margin-bottom: 0;
                                            }

                                            .admin-page-help code {
                                                color: #c01e25;
                                                font-weight: 700;
                                            }
                                        </style>
                                    @endpush
                                    @push('scripts')
                                        <script>
                                            // The header card clips what overflows it: while open, the help lives in <body>.
                                            document.addEventListener('show.bs.dropdown', function (event) {
                                                const menu = event.target.closest('.dropdown')?.querySelector('.admin-page-help');
                                                if (!menu) return;
                                                menu.__home = menu.parentElement;
                                                document.body.appendChild(menu);
                                                event.target.addEventListener('hidden.bs.dropdown', function () { menu.__home.appendChild(menu); }, { once: true });
                                            });
                                        </script>
                                    @endpush
                                @endonce
                            @endif
                        </div>

                        @if ($subtitle)
                            <p class="mb-1 text-white-50">{{ $subtitle }}</p>
                        @endif

                        @if (isset($breadcrumbs) && $breadcrumbs->isNotEmpty())
                            <nav class="py-0" style="--bs-breadcrumb-divider: '&gt;'; --bs-breadcrumb-divider-color: rgba(255, 255, 255, 0.6);" aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    {{ $breadcrumbs }}
                                </ol>
                            </nav>
                        @endif
                    </div>

                    @if (isset($actions) && $actions->isNotEmpty())
                        <div class="col-md-auto d-none d-md-block">
                            <div class="d-flex flex-wrap gap-2 justify-content-end">
                                {{ $actions }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Below md the actions leave the gradient card so they don't crowd the title. --}}
        @if (isset($actions) && $actions->isNotEmpty())
            <div class="admin-page-header-actions-mobile d-flex d-md-none flex-wrap gap-2 mt-n2 mb-4">
                {{ $actions }}
            </div>
        @endif
    </div>
</div>
