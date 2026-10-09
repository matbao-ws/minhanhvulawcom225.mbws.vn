{{-- Core additions to a storefront <head>. Theme layouts include this one line:
     @include('core.head')
     Core may add or change what is in here in an update; a theme never lists the parts. --}}
@include('core.partials.google-analytics')
@include('core.partials.seo-head')
{{-- Undoing a theme's bold/italic/underline in an inline-edited region is stored as these
     classes (the sanitizer keeps no inline style), so they must style it for every visitor. --}}
<style>.client-weight-normal{font-weight:400!important}.client-style-normal{font-style:normal!important}.client-decoration-none{text-decoration:none!important}</style>
