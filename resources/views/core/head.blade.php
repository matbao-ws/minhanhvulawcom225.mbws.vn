{{-- Core additions to a storefront <head>. Theme layouts include this one line:
     @include('core.head')
     Core may add or change what is in here in an update; a theme never lists the parts. --}}
@include('core.partials.google-analytics')
@include('core.partials.seo-head')
{{-- Undoing a theme's bold/italic/underline in an inline-edited region is stored as these
     classes (the sanitizer keeps no inline style), so they must style it for every visitor. --}}
{{-- Alignment chosen in the inline toolbar is a token (site_blocks.align), mapped here. A copy
     ("Nhân bản") of an inline region starts its own line instead of running on after the original. --}}
<style>.client-weight-normal{font-weight:400!important}.client-style-normal{font-style:normal!important}.client-decoration-none{text-decoration:none!important}.client-align-left{text-align:left!important}.client-align-center{text-align:center!important}.client-align-right{text-align:right!important}.client-align-justify{text-align:justify!important}.client-copy-line{display:block!important}</style>
