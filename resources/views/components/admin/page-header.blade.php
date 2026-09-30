@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->class(['row']) }}>
    <div class="col-12">
        <div class="card shadow-none position-relative overflow-hidden mb-4" style="background: linear-gradient(90deg, #10203C 0%, #193877 50%, #204DA4 100%) !important;">
            <div class="card-body px-4 py-3">
                <div class="row align-items-center g-3">
                    <div class="{{ isset($actions) && $actions->isNotEmpty() ? 'col-md' : 'col-12' }}">
                        <h4 class="fw-semibold mb-1 text-white">{{ $title }}</h4>

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
