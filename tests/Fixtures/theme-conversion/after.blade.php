<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Theme conversion parity</title>
<style>
    body { margin: 0; font-family: Arial, sans-serif; background: #f4f4f4; }
    .hero { min-height: 420px; padding: 48px; box-sizing: border-box; color: #fff; }
    .hero h1 { margin: 0 0 16px; font-size: 40px; }
    .hero .btn { display: inline-block; padding: 12px 24px; background: #e4572e; color: #fff; text-decoration: none; }
    .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; padding: 16px; }
    .grid img { display: block; width: 100%; height: 220px; object-fit: cover; }
    picture > img { outline: 4px solid #222; outline-offset: -4px; }
    .lazy-bg { height: 200px; margin: 0 16px 16px; background-size: cover; background-position: center; }
    @media (max-width: 600px) {
        .hero { min-height: 260px; padding: 24px; }
        .hero h1 { font-size: 24px; }
        .grid { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>
<section class="hero" data-case="bg-layered" @clientBackground('parity.hero.bg', "background: linear-gradient(90deg, rgba(0,0,0,.55), rgba(0,0,0,0)), url('/img/hero.png') center / cover no-repeat")>
    <x-client::editable key="parity.hero.title" tag="h1" class="hero-title">Bộ sưu tập mùa thu</x-client::editable>
    <x-client::editable key="parity.hero.cta" tag="a" href="/san-pham" class="btn">Mua ngay</x-client::editable>
</section>
<div class="grid">
    <x-client::editable-image key="parity.grid.card" src="/img/card.png" alt="Thẻ" class="card-img" data-case="picture"
        picture-class="card-picture" :sources="[['media' => '(max-width: 600px)', 'srcset' => '/img/card-mobile.png']]" />
    <x-client::editable-image key="parity.grid.b" src="/img/b.png" srcset="/img/b.png 1x, /img/b-2x.png 2x" alt="B" class="b-img" data-case="srcset" />
    <x-client::editable-image key="parity.grid.c" src="/img/c.png" lazy="data-src" placeholder="/img/blank.gif" alt="C" class="lazy" data-case="lazy" />
</div>
<div class="lazy-bg" @clientBackground('parity.band.bg', '', ['lazy' => 'data-bg', 'src' => '/img/d.png']) data-case="data-bg"></div>
<script>
    // The theme's own lazy loader: it must keep working, unchanged, after the conversion.
    document.querySelectorAll('img[data-src]').forEach(function (img) { img.src = img.dataset.src; });
    document.querySelectorAll('[data-bg]').forEach(function (el) { el.style.backgroundImage = 'url(' + el.dataset.bg + ')'; });
</script>
</body>
</html>
