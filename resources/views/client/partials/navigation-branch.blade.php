<ul class="client-nav-list client-nav-level-{{ $level }}">
    @foreach($items as $item)
        <li class="client-nav-item">
            @if($item['url'])
                <a href="{{ $item['url'] }}"
                   @if($item['target_blank']) target="_blank" rel="noopener noreferrer" @endif>{{ $item['label'] }}</a>
            @else
                {{-- No resolvable target yet: rendered as text rather than a link to nowhere. --}}
                <span>{{ $item['label'] }}</span>
            @endif

            @if($item['children']->isNotEmpty())
                @include('client.partials.navigation-branch', ['items' => $item['children'], 'level' => $level + 1])
            @endif
        </li>
    @endforeach
</ul>
