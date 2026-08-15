@php
    $menuService = app(\App\Services\MenuService::class);
    $fallbackLocale = app(\App\Services\LanguageRegistry::class)->fallbackLocale();
@endphp

<ul class="menu-tree menu-sortable" data-parent-id="{{ $parentId }}">
    @foreach($branch as $item)
        @php
            $label = $item->getTranslation('label', app()->getLocale(), false)
                ?: $item->getTranslation('label', $fallbackLocale, false);
            $resolvedUrl = $menuService->resolveUrl($item);
            $children = $byParent->get($item->id) ?? collect();
        @endphp
        <li data-item-id="{{ $item->id }}">
            <div class="menu-node {{ $item->is_active ? '' : 'menu-node-inactive' }}">
                @can('menus.update')
                    <span class="menu-drag-handle" title="{{ __('admin.menus.items.drag') }}">
                        <i class="ti ti-grip-vertical fs-5"></i>
                    </span>
                @endcan

                <div class="flex-grow-1 min-width-0">
                    <div class="fw-semibold text-dark text-truncate">{{ $label }}</div>
                    <div class="fs-2 text-muted text-truncate">
                        <span class="badge bg-primary-subtle text-primary me-1">{{ __('admin.menus.types.'.$item->type) }}</span>
                        @if($item->hasMissingTarget())
                            <span class="badge bg-danger-subtle text-danger">{{ __('admin.menus.items.target_missing') }}</span>
                        @elseif($resolvedUrl)
                            <span>{{ $resolvedUrl }}</span>
                        @else
                            <span class="badge bg-warning-subtle text-warning">{{ __('admin.menus.items.no_storefront_route') }}</span>
                        @endif
                    </div>
                </div>

                @unless($item->is_active)
                    <span class="badge bg-secondary-subtle text-secondary">{{ __('admin.menus.fields.inactive') }}</span>
                @endunless

                <div class="d-flex gap-2">
                    @can('menus.update')
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.menus.items.edit', [$menu, $item]) }}">
                            <i class="ti ti-edit fs-4"></i>
                        </a>
                    @endcan
                    @can('menus.delete')
                        <form method="POST" action="{{ route('admin.menus.items.destroy', [$menu, $item]) }}"
                              class="d-inline js-delete-form" data-confirm-text="{{ __('admin.menus.items.delete_confirm') }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="ti ti-trash fs-4"></i></button>
                        </form>
                    @endcan
                </div>
            </div>

            @if($children->isNotEmpty())
                @include('admin.menus.items._branch', [
                    'branch' => $children,
                    'byParent' => $byParent,
                    'menu' => $menu,
                    'parentId' => $item->id,
                ])
            @endif
        </li>
    @endforeach
</ul>
