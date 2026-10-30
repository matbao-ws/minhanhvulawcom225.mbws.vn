{{-- The shop's logo, or its initials and name while it has none of its own. --}}
@if($logoUrl)
    <img src="{{ $logoUrl }}" alt="{{ $look['shop'] }}" height="44" style="display:inline-block;height:44px;width:auto;max-width:200px;border:0;outline:none">
@else
    <table role="presentation" cellpadding="0" cellspacing="0" style="{{ $align === 'center' ? 'margin:0 auto' : '' }}">
        <tr>
            <td style="width:34px;height:34px;border-radius:9px;background:{{ $accent }};color:{{ $look['on_accent'] }};font:600 15px/34px {!! $font !!};text-align:center">{{ $initials }}</td>
            <td style="padding-left:10px;font:600 17px/1.2 {!! $font !!};color:#111827">{{ $look['shop'] }}</td>
        </tr>
    </table>
@endif
