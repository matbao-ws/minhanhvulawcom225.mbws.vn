@php
  $currentPath = '/'.trim(request()->path(), '/');
  $activeKey = $activeNav ?? '';
@endphp
@if(($level ?? 0) === 0)
  <ul class="flex items-center justify-between text-[14px] uppercase tracking-wide py-1 font-semibold client-nav-list client-nav-level-0">
    @foreach($items as $item)
      @php
        $itemUrl = $item['url'] ?? null;
        $itemSlug = $itemUrl ? trim((string) parse_url($itemUrl, PHP_URL_PATH), '/') : '';
        $isActive = ($activeKey !== '' && $itemSlug !== '' && $activeKey === $itemSlug)
            || ($itemSlug !== '' && str_starts_with(trim($currentPath, '/'), $itemSlug));
        $hasChildren = $item['children']->isNotEmpty();
        $linkCls = $isActive
            ? 'text-brand-red font-bold'
            : 'text-white hover:text-brand-red font-semibold transition';
      @endphp
      <li class="relative client-nav-item {{ $hasChildren ? 'nav-item-dropdown group' : '' }}">
        @if($itemUrl)
          <a href="{{ $itemUrl }}"
             class="px-3.5 py-3.5 {{ $hasChildren ? 'flex items-center gap-1.5' : 'block' }} {{ $linkCls }}"
             @if($item['target_blank']) target="_blank" rel="noopener noreferrer" @endif>
            <span>{{ $item['label'] }}</span>
            @if($hasChildren)
              <i class="fa-solid fa-angle-down text-xs opacity-75 group-hover:rotate-180 transition-transform duration-200"></i>
            @endif
          </a>
        @else
          <span class="px-3.5 py-3.5 block {{ $linkCls }}"><span>{{ $item['label'] }}</span></span>
        @endif

        @if($hasChildren)
          @include('client.partials.navigation-branch', ['items' => $item['children'], 'level' => 1])
        @endif
      </li>
      @if(! $loop->last)
        <li class="text-slate-600 select-none font-normal" aria-hidden="true" data-editable-ignore="dấu phân cách menu">|</li>
      @endif
    @endforeach
  </ul>
@elseif($items->count() > 6)
  @php
    $col1 = $items->slice(0, 5);
    $col2 = $items->slice(5, 6);
    $col3 = $items->slice(11);
    $megaHeadings = [
      'col1' => 'Doanh Nghiệp & Đầu Tư',
      'col2' => 'Giấy Phép & Di Trú',
      'col3' => 'Thuế & Hành Chính',
    ];
  @endphp
  <div class="dropdown-menu hidden absolute top-full left-1/2 -translate-x-1/2 pt-2 z-50 animate-fadeIn">
    <div class="w-[780px] bg-white rounded-2xl shadow-2xl border border-slate-200 border-t-4 border-t-brand-red p-6 text-sm normal-case font-medium ring-1 ring-black/5 grid grid-cols-3 gap-6">
      <div>
        <h4 class="font-bold text-navy-900 text-xs uppercase tracking-wider text-brand-red pb-2 border-b border-slate-100 mb-2.5 flex items-center gap-1.5">
          <i class="fa-solid fa-briefcase"></i> {{ $megaHeadings['col1'] }}
        </h4>
        <ul class="space-y-1.5 client-nav-list client-nav-level-{{ $level }}">
          @foreach($col1 as $child)
            <li class="client-nav-item">
              @if($child['url'])
                <a href="{{ $child['url'] }}" class="block py-1 px-2 rounded hover:bg-red-50 hover:text-brand-red transition">{{ $child['label'] }}</a>
              @else
                <span class="block py-1 px-2">{{ $child['label'] }}</span>
              @endif
            </li>
          @endforeach
        </ul>
      </div>
      <div>
        <h4 class="font-bold text-navy-900 text-xs uppercase tracking-wider text-brand-red pb-2 border-b border-slate-100 mb-2.5 flex items-center gap-1.5">
          <i class="fa-solid fa-passport"></i> {{ $megaHeadings['col2'] }}
        </h4>
        <ul class="space-y-1.5 client-nav-list client-nav-level-{{ $level }}">
          @foreach($col2 as $child)
            <li class="client-nav-item">
              @if($child['url'])
                <a href="{{ $child['url'] }}" class="block py-1 px-2 rounded hover:bg-red-50 hover:text-brand-red transition">{{ $child['label'] }}</a>
              @else
                <span class="block py-1 px-2">{{ $child['label'] }}</span>
              @endif
            </li>
          @endforeach
        </ul>
      </div>
      <div>
        <h4 class="font-bold text-navy-900 text-xs uppercase tracking-wider text-brand-red pb-2 border-b border-slate-100 mb-2.5 flex items-center gap-1.5">
          <i class="fa-solid fa-file-invoice-dollar"></i> {{ $megaHeadings['col3'] }}
        </h4>
        <ul class="space-y-1.5 client-nav-list client-nav-level-{{ $level }}">
          @foreach($col3 as $child)
            <li class="client-nav-item">
              @if($child['url'])
                <a href="{{ $child['url'] }}" class="block py-1 px-2 rounded hover:bg-red-50 hover:text-brand-red transition">{{ $child['label'] }}</a>
              @else
                <span class="block py-1 px-2">{{ $child['label'] }}</span>
              @endif
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>
@else
  <div class="dropdown-menu hidden absolute top-full left-0 pt-2 z-50 animate-fadeIn">
    <ul class="{{ $items->count() > 3 ? 'w-88' : 'w-72' }} bg-white rounded-xl shadow-2xl border border-slate-200 border-t-4 border-t-brand-red py-2 text-base normal-case font-medium ring-1 ring-black/5 divide-y divide-slate-100 client-nav-list client-nav-level-{{ $level }}">
      @foreach($items as $child)
        <li class="client-nav-item">
          @if($child['url'])
            <a href="{{ $child['url'] }}"
               class="flex items-center justify-between px-5 py-3.5 text-slate-800 hover:text-brand-red hover:bg-slate-50 transition duration-150 group/link"
               @if($child['target_blank']) target="_blank" rel="noopener noreferrer" @endif>
              <span class="flex items-center gap-2 group-hover/link:translate-x-1.5 transition-transform duration-200 whitespace-nowrap">
                <span class="w-1.5 h-1.5 rounded-full bg-brand-red opacity-0 group-hover/link:opacity-100 transition-opacity"></span>
                {{ $child['label'] }}
              </span>
              <i class="fa-solid fa-angle-right text-xs text-slate-300 group-hover/link:text-brand-red transition duration-150"></i>
            </a>
          @else
            <span class="block px-5 py-3.5 text-slate-800">{{ $child['label'] }}</span>
          @endif
        </li>
      @endforeach
    </ul>
  </div>
@endif
