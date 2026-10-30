{{-- A link that looks like a button in every client: $label, $href, $secondary (outlined). --}}
@php($font = $look['font_stack'])
<table role="presentation" cellpadding="0" cellspacing="0" style="display:inline-table">
    <tr>
        @if($secondary ?? false)
            <td align="center" style="border-radius:10px;border:1px solid #d1d5db">
                <a href="{{ $href }}" style="display:block;padding:12px 22px;font:600 15px/1.2 {!! $font !!};color:#111827;text-decoration:none">{{ $label }}</a>
            </td>
        @else
            <td align="center" style="border-radius:10px;background:{{ $look['accent'] }}">
                <a href="{{ $href }}" style="display:block;padding:13px 26px;font:600 15px/1.2 {!! $font !!};color:{{ $look['on_accent'] }};text-decoration:none;border-radius:10px">{{ $label }}</a>
            </td>
        @endif
    </tr>
</table>
