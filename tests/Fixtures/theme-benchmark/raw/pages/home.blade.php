@extends('client.layouts.app')

@section('content')
{{-- Theme benchmark: one of every pattern a cut theme meets. See ../../expected.json. --}}
<section class="hero">
    <div class="hero-title" data-case="hero-title-div">Giảm giá 50% toàn bộ cửa hàng</div>
    <a href="/san-pham" class="btn btn-primary" data-case="cta-link">Mua ngay</a>
    <button type="button" class="btn-outline" data-case="button-text">Đăng ký nhận tin</button>
</section>

<section class="media">
    <picture>
        <source media="(max-width: 600px)" srcset="/client-assets/images/hero-mobile.jpg">
        <img data-case="picture-img" src="/client-assets/images/hero.jpg" alt="Ảnh hero">
    </picture>
    <picture>
        <source data-case="picture-source-only" type="image/webp" srcset="/client-assets/images/only.webp">
    </picture>
    <img data-case="img-srcset" src="/client-assets/images/a.jpg" srcset="/client-assets/images/a.jpg 1x, /client-assets/images/a@2x.jpg 2x" alt="Ảnh A">
    <img data-case="img-lazy" class="lazy" src="/client-assets/images/placeholder.gif" data-src="/client-assets/images/real.jpg" alt="Ảnh lazy">
</section>

<section data-case="bg-single" class="banner" style="background-image: url('/client-assets/images/banner.jpg')"></section>
<section data-case="bg-layered" class="banner" style="background: linear-gradient(rgba(0,0,0,.5), rgba(0,0,0,.5)), url('/client-assets/images/b.jpg') center/cover no-repeat"></section>
<div data-case="bg-shorthand" class="banner" style="background: url(/client-assets/images/c.jpg) center / cover no-repeat; min-height: 300px"></div>
<div class="band" style="background: linear-gradient(90deg, #000, #fff)"></div>
<div class="masked" style="mask-image: url(/client-assets/images/mask.svg); cursor: url(/client-assets/images/hand.cur), auto"></div>
<div data-case="data-bg" class="lazy-bg" data-bg="/client-assets/images/lazy-bg.jpg"></div>

<style>
    .case-style-block { background-image: url('/client-assets/images/promo.jpg'); }
    @font-face { font-family: Brand; src: url('/client-assets/fonts/brand.woff2') format('woff2'); }
    .icon-mask { mask: url(#mask-shape); }
</style>

<section class="products">
    <h3>{{ $product->name }}</h3>
    <div class="slider" data-flickity='{"wrapAround":true,"autoPlay":5000}'>
        @foreach($slides as $slide)
            <div class="slide"><img src="{{ $slide->image }}" alt="{{ $slide->title }}"></div>
        @endforeach
    </div>
</section>

<section class="video">
    <video data-case="video-poster" poster="/client-assets/images/poster.jpg" controls>
        <source src="/client-assets/media/intro.mp4" type="video/mp4">
    </video>
    <audio src="/client-assets/media/jingle.mp3" controls></audio>
</section>

<section class="copy">
    <p data-case="trans-undefined">{{ __('Ưu đãi độc quyền chỉ trong tuần này') }}</p>
    <p>{{ __('controller.api.invalid_data') }}</p>
    <span>{{ __('Showing') }}</span>
    <h4 data-case="lang-directive-undefined">@lang('Chính sách đổi trả trong 30 ngày')</h4>
</section>
@push('styles')<style>#hero { padding-top: 30px; }</style>@endpush
@endsection
