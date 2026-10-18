{{-- Page links of an admin list, drawn even when everything fits on one page: the controls stay
     where people expect them, disabled until there is a second page. Used by admin.shared.pagination;
     the storefront keeps Laravel's own view. --}}
@php($single = ! $paginator->hasPages())
<nav aria-label="{{ __('admin.pagination.label') }}">
    <ul class="pagination pagination-sm pagination--admin mb-0">
        @if ($paginator->onFirstPage())
            <li class="page-item disabled" aria-disabled="true">
                <span class="page-link" aria-hidden="true">&lsaquo;</span>
                <span class="visually-hidden">@lang('pagination.previous')</span>
            </li>
        @else
            <li class="page-item">
                <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">&lsaquo;</a>
            </li>
        @endif

        @if ($single)
            <li class="page-item active disabled" aria-current="page"><span class="page-link">1</span></li>
        @else
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach
        @endif

        @if ($paginator->hasMorePages())
            <li class="page-item">
                <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">&rsaquo;</a>
            </li>
        @else
            <li class="page-item disabled" aria-disabled="true">
                <span class="page-link" aria-hidden="true">&rsaquo;</span>
                <span class="visually-hidden">@lang('pagination.next')</span>
            </li>
        @endif
    </ul>
</nav>
