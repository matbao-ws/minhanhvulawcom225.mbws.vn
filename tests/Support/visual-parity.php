<?php

/**
 * Pixel and layout parity of a theme before and after conversion to editable regions.
 *
 *     php tests/Support/visual-parity.php [output-dir] [--chrome=path]
 *
 * Renders tests/Fixtures/theme-conversion/{before,after}.blade.php as a visitor, serves them with
 * generated pictures, and with headless Chrome at 1440×900 and 390×844:
 *
 *   1. screenshots before and after must differ by at most 0.1% of pixels;
 *   2. every element's box must be identical before, after, and after every picture is replaced;
 *   3. after replacing, the centre of each picture shows the new one at both sizes — no <source>,
 *      srcset candidate, lazy attribute or background layer brings the theme's picture back.
 *
 * Needs Chrome and GD, so it is a verification step rather than part of CI;
 * tests/Feature/ThemeConversionParityTest checks the DOM in CI. Touches no database: the stored
 * values come from a stand-in SiteContentService.
 */

use App\Services\SiteContentService;
use App\Services\SiteListService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$arguments = array_slice($argv, 1);
$chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
$out = null;
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--chrome=')) {
        $chrome = substr($argument, 9);
    } else {
        $out = $argument;
    }
}
$out ??= sys_get_temp_dir().'/visual-parity-'.date('Ymd-His');
$docroot = $out.'/site';
@mkdir($docroot.'/img', 0777, true);
@mkdir($docroot.'/storage/media', 0777, true);

if (! is_file($chrome)) {
    fwrite(STDERR, "Chrome not found at {$chrome}; pass --chrome=path\n");
    exit(2);
}

$port = 8765 + random_int(0, 200);
$origin = "http://127.0.0.1:{$port}";

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['app.url' => $origin]);
URL::forceRootUrl($origin);

/** @var array<string, string> $stored  key => picture, for the "replaced" render */
$stored = [];
$app->instance(SiteContentService::class, Mockery::mock(SiteContentService::class, [
    'isCleared' => false,
    'type' => null,
    'format' => null,
])->shouldReceive('value')->andReturnUsing(function (string $key) use (&$stored): ?string {
    return isset($stored[$key]) ? url($stored[$key]) : null;
})->getMock()->shouldReceive('forget')->getMock());
$app->instance(SiteListService::class, Mockery::mock(SiteListService::class, ['items' => []]));

// Pictures: four coloured quadrants, so a lost background-size or -position shows in the pixels.
$picture = function (string $path, int $width, int $height, array $colours) use ($docroot): void {
    $image = imagecreatetruecolor($width, $height);
    foreach ($colours as $i => [$r, $g, $b]) {
        $x = ($i % 2) * intdiv($width, 2);
        $y = intdiv($i, 2) * intdiv($height, 2);
        imagefilledrectangle($image, $x, $y, $x + intdiv($width, 2), $y + intdiv($height, 2), imagecolorallocate($image, $r, $g, $b));
    }
    imagepng($image, $docroot.$path);
};
$picture('/img/hero.png', 1600, 900, [[30, 90, 160], [40, 140, 80], [200, 160, 40], [120, 40, 40]]);
$picture('/img/card.png', 800, 600, [[20, 120, 200], [20, 160, 120], [20, 200, 60], [20, 80, 160]]);
$picture('/img/card-mobile.png', 600, 800, [[200, 60, 20], [160, 100, 20], [120, 140, 20], [220, 30, 30]]);
$picture('/img/b.png', 800, 600, [[90, 90, 90], [140, 140, 140], [60, 60, 60], [180, 180, 180]]);
$picture('/img/b-2x.png', 1600, 1200, [[90, 90, 90], [140, 140, 140], [60, 60, 60], [180, 180, 180]]);
$picture('/img/c.png', 800, 600, [[10, 60, 30], [10, 100, 50], [10, 140, 70], [10, 180, 90]]);
$picture('/img/d.png', 1600, 400, [[70, 20, 100], [100, 30, 140], [130, 40, 180], [160, 50, 220]]);
// The replacement: one flat colour no theme picture uses, easy to recognise in a screenshot.
$picture('/storage/media/new.png', 1200, 900, array_fill(0, 4, [255, 0, 255]));
file_put_contents($docroot.'/img/blank.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));

$fixtures = $root.'/tests/Fixtures/theme-conversion';
$render = fn (string $name): string => Blade::render((string) file_get_contents("{$fixtures}/{$name}.blade.php"));

$pages = ['before' => $render('before'), 'after' => $render('after')];
$stored = [
    'parity.hero.bg' => '/storage/media/new.png',
    'parity.band.bg' => '/storage/media/new.png',
    'parity.grid.card' => '/storage/media/new.png',
    'parity.grid.b' => '/storage/media/new.png',
    'parity.grid.c' => '/storage/media/new.png',
];
$pages['replaced'] = $render('after');

/*
 * Chrome will not open a window narrower than about 500px, so each page runs in an iframe exactly
 * as wide as the viewport (media queries follow the iframe). Once the page and the theme's lazy
 * loader have run and every picture is decoded — foreground and background — the frame grows to
 * the page's full height, so pictures below the fold are compared too, and the outer page records
 * every element's box. Screenshots are taken of the same page and cropped to the frame.
 */
$host = <<<'HTML'
<!doctype html><html><body style="margin:0;background:#fff">
<iframe id="frame" src="/__PAGE__.html" style="border:0;display:block;visibility:hidden;width:__W__px;height:__H__px"></iframe>
<script>
var frame = document.getElementById('frame');
frame.addEventListener('load', function () {
    var doc = frame.contentDocument;
    // load events rather than decode(): decode() can stall in a hidden frame. Painting is then
    // covered by Chrome's real-time --timeout.
    var loaded = function (img) {
        return img.complete ? Promise.resolve() : new Promise(function (done) { img.onload = img.onerror = done; });
    };
    var waits = [].map.call(doc.images, loaded);
    [].forEach.call(doc.querySelectorAll('*'), function (el) {
        var bg = frame.contentWindow.getComputedStyle(el).backgroundImage;
        (bg.match(/url\("?([^")]+)"?\)/g) || []).forEach(function (u) {
            var img = new Image();
            img.src = u.replace(/^url\("?|"?\)$/g, '');
            waits.push(loaded(img));
        });
    });
    var limit = new Promise(function (done) { setTimeout(done, 3000); });
    Promise.race([Promise.all(waits), limit]).then(function () {
        frame.style.height = Math.max(__H__, doc.documentElement.scrollHeight) + 'px';
            frame.style.visibility = 'visible';
            var boxes = [].map.call(doc.body.querySelectorAll('*'), function (el, i) {
                var r = el.getBoundingClientRect();
                return [i, el.tagName, Math.round(r.x), Math.round(r.y), Math.round(r.width), Math.round(r.height), el.getAttribute('data-case')];
            });
            var pre = document.createElement('pre');
            pre.id = 'parity-boxes';
            pre.style.display = 'none';
            pre.textContent = JSON.stringify({ height: frame.offsetHeight, boxes: boxes });
            document.body.appendChild(pre);
    });
});
</script>
</body></html>
HTML;
foreach ($pages as $name => $html) {
    file_put_contents("{$docroot}/{$name}.html", $html);
}

$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $docroot], [1 => ['file', $out.'/server.log', 'a'], 2 => ['file', $out.'/server.log', 'a']], $pipes);
usleep(500_000);

$viewports = ['desktop' => [1440, 900], 'mobile' => [390, 844]];
$chromeRun = function (array $flags, string $url) use ($chrome, $out): string {
    $command = array_merge([$chrome, '--headless=new', '--disable-gpu', '--hide-scrollbars', '--force-device-scale-factor=1',
        '--no-first-run', '--user-data-dir='.$out.'/profile', // Real time, not --virtual-time-budget: virtual time runs out while pictures are still decoding.
        '--timeout=5000'], $flags, [$url]);
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $stdout = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    proc_close($process);

    return (string) $stdout;
};

$failures = [];
$report = [];
foreach ($viewports as $label => [$width, $height]) {
    $boxes = [];
    foreach (array_keys($pages) as $name) {
        file_put_contents("{$docroot}/{$name}.{$label}.html", strtr($host, ['__PAGE__' => $name, '__W__' => $width, '__H__' => $height]));
        $window = '--window-size='.max($width, 800).',3000';
        $dom = $chromeRun([$window, '--dump-dom'], "{$origin}/{$name}.{$label}.html");
        preg_match('~<pre id="parity-boxes"[^>]*>(.*?)</pre>~s', $dom, $match);
        $probe = json_decode(html_entity_decode($match[1] ?? 'null'), true);
        if (! is_array($probe)) {
            file_put_contents("{$out}/{$name}-{$label}.dom.html", $dom);
            $failures[] = "{$label}/{$name}: page did not load, see {$name}-{$label}.dom.html";

            continue 2;
        }
        $boxes[$name] = $probe['boxes'];
        $chromeRun([$window, "--screenshot={$out}/{$name}-{$label}.full.png"], "{$origin}/{$name}.{$label}.html");
        $full = imagecreatefrompng("{$out}/{$name}-{$label}.full.png");
        imagepng(imagecrop($full, ['x' => 0, 'y' => 0, 'width' => $width, 'height' => min((int) $probe['height'], imagesy($full))]), "{$out}/{$name}-{$label}.png");
        unlink("{$out}/{$name}-{$label}.full.png");
    }

    // 1. Pixels, before vs after.
    $a = @imagecreatefrompng("{$out}/before-{$label}.png");
    $b = @imagecreatefrompng("{$out}/after-{$label}.png");
    if (! $a || ! $b) {
        $failures[] = "{$label}: screenshot missing";

        continue;
    }
    $w = min(imagesx($a), imagesx($b));
    $h = min(imagesy($a), imagesy($b));
    $diff = imagecreatetruecolor($w, $h);
    $red = imagecolorallocate($diff, 255, 0, 0);
    $changed = 0;
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $pa = imagecolorat($a, $x, $y);
            if ($pa !== imagecolorat($b, $x, $y)) {
                $changed++;
                imagesetpixel($diff, $x, $y, $red);
            } else {
                imagesetpixel($diff, $x, $y, $pa);
            }
        }
    }
    $ratio = $changed / ($w * $h);
    $report[] = sprintf('%-7s %4dx%-4d (full page) pixels changed before→after: %d (%.4f%%)', $label, $w, $h, $changed, $ratio * 100);
    if ($ratio > 0.001) {
        imagepng($diff, "{$out}/diff-{$label}.png");
        $failures[] = sprintf('%s: %.4f%% of pixels differ (limit 0.1%%), see diff-%s.png', $label, $ratio * 100, $label);
    }

    // 2. Boxes, before vs after vs replaced.
    foreach (['after', 'replaced'] as $name) {
        if ($boxes[$name] !== $boxes['before']) {
            $failures[] = "{$label}: element boxes differ between before and {$name}";
        }
    }
    $report[] = sprintf('%-7s element boxes identical before/after/replaced: %s', $label,
        $boxes['after'] === $boxes['before'] && $boxes['replaced'] === $boxes['before'] ? 'yes ('.count($boxes['before'] ?? []).' elements)' : 'NO');

    // 3. The replaced picture is the one on screen, at this size.
    $shot = imagecreatefrompng("{$out}/replaced-{$label}.png");
    foreach ($boxes['replaced'] ?? [] as [, $tag, $x, $y, $bw, $bh, $case]) {
        if ($case === null || $tag === 'PICTURE' || $tag === 'SOURCE' || $y + $bh / 2 >= imagesy($shot)) {
            continue;
        }
        // The hero's gradient fades out to the right: sample there.
        $px = (int) ($case === 'bg-layered' ? $x + $bw - 12 : $x + $bw / 2);
        $py = (int) ($y + $bh / 2);
        $rgb = imagecolorat($shot, $px, $py);
        [$r, $g, $bl] = [($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255];
        $ok = $r > 200 && $g < 60 && $bl > 200;
        $report[] = sprintf('%-7s %-10s shows the new picture: %s (rgb %d,%d,%d)', $label, $case, $ok ? 'yes' : 'NO', $r, $g, $bl);
        if (! $ok) {
            $failures[] = "{$label}: {$case} still shows a theme picture after replacing";
        }
    }
}

// proc_terminate leaves the server running on Windows, holding the caller's pipe open.
$pid = proc_get_status($server)['pid'];
PHP_OS_FAMILY === 'Windows' ? exec("taskkill /F /T /PID {$pid} 2>NUL") : proc_terminate($server);
proc_close($server);

$summary = implode(PHP_EOL, $report).PHP_EOL.PHP_EOL.'Screenshots: '.$out.PHP_EOL
    .($failures === [] ? 'PASS' : 'FAIL'.PHP_EOL.' - '.implode(PHP_EOL.' - ', $failures)).PHP_EOL;
file_put_contents($out.'/report.txt', $summary);
echo $summary;
exit($failures === [] ? 0 : 1);
