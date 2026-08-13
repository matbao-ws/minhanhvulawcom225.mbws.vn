@php
    $perPageOptions = \App\Http\Controllers\Admin\Concerns\HandlesPagination::$perPageOptions;
    $currentPerPage = $paginator->perPage();
    $keptQuery = collect(request()->except(['per_page', 'page']))->filter(fn ($value) => $value !== null && $value !== '');
@endphp

{{-- An empty list already shows the illustrated empty state, so the controls stay hidden. --}}
@if($paginator->total() > 0)
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <form method="GET" class="d-flex align-items-center gap-2 mb-0">
                @foreach($keptQuery as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $item)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <label class="form-label text-muted fs-2 mb-0 text-nowrap" for="per_page">{{ __('admin.pagination.per_page') }}</label>
                <select name="per_page" id="per_page" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                    @foreach($perPageOptions as $option)
                        <option value="{{ $option }}" @selected($currentPerPage === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('admin.pagination.apply') }}</button></noscript>
            </form>
            <span class="text-muted fs-2">
                {{ __('admin.pagination.summary', [
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ]) }}
            </span>
        </div>
        <div>{{ $paginator->onEachSide(1)->links() }}</div>
    </div>
@endif
