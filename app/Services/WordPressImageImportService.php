<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WordPressImageImportService
{
    private const MAX_BYTES = 10 * 1024 * 1024;

    /** @var array<string, string|null> */
    private array $cache = [];

    /** @var list<string> */
    private array $errors = [];

    /** @var array<string, string> */
    private array $existing = [];

    private int $downloaded = 0;

    public function reset(): void
    {
        $this->cache = [];
        $this->errors = [];
        $this->downloaded = 0;
        $this->existing = [];
        foreach (Storage::disk('public')->files('posts/wordpress') as $path) {
            if (preg_match('/-([a-f0-9]{12})\.(?:jpe?g|png|webp|gif)$/i', $path, $matches)) {
                $this->existing[strtolower($matches[1])] = $path;
            }
        }
    }

    public function localize(?string $url): ?string
    {
        $url = html_entity_decode(trim((string) $url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return $url === '' ? null : $url;
        }

        if (array_key_exists($url, $this->cache)) {
            return $this->cache[$url] ?? $url;
        }

        $urlHash = substr(hash('sha256', $url), 0, 12);
        if (isset($this->existing[$urlHash])) {
            return $this->cache[$url] = '/storage/'.$this->existing[$urlHash];
        }

        try {
            $response = $this->request($url);
            $body = $this->limitedBody($response);
            $declaredMime = $this->declaredMime($response);
            $detectedMime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($body);
            $extension = match ($detectedMime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                default => throw new RuntimeException('định dạng ảnh không được hỗ trợ'),
            };
            if (str_starts_with($declaredMime, 'image/') && $declaredMime !== $detectedMime) {
                throw new RuntimeException('nội dung file không khớp MIME ảnh');
            }

            $baseName = Str::slug(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME)) ?: 'wordpress-image';
            $path = 'posts/wordpress/'.Str::limit($baseName, 80, '').'-'.$urlHash.'.'.$extension;
            if (! Storage::disk('public')->exists($path) && ! Storage::disk('public')->put($path, $body)) {
                throw new RuntimeException('không thể ghi ảnh vào bộ nhớ local');
            }

            $localUrl = '/storage/'.$path;
            $this->cache[$url] = $localUrl;
            $this->downloaded++;

            return $localUrl;
        } catch (Throwable $exception) {
            $this->cache[$url] = null;
            $this->errors[] = Str::limit($url, 180).' — '.$exception->getMessage();

            return $url;
        }
    }

    public function localizeHtml(string $html): string
    {
        $html = (string) preg_replace_callback(
            '/\b(src|poster)\s*=\s*(["\'])(.*?)\2/is',
            function (array $matches): string {
                $url = $this->localize($matches[3]) ?? $matches[3];

                return $matches[1].'='.$matches[2].$url.$matches[2];
            },
            $html,
        );

        // The browser prefers srcset over src. Keeping WordPress srcset would
        // make it continue loading remote images even after src was localized.
        return (string) preg_replace('/\s+(?:srcset|sizes)\s*=\s*(["\']).*?\1/is', '', $html);
    }

    /** @return array{downloaded: int, failed: int, errors: list<string>} */
    public function report(): array
    {
        return [
            'downloaded' => $this->downloaded,
            'failed' => count($this->errors),
            'errors' => $this->errors,
        ];
    }

    private function request(string $url): Response
    {
        for ($redirects = 0; $redirects <= 3; $redirects++) {
            $this->assertPublicUrl($url);
            $response = Http::connectTimeout(8)
                ->timeout(20)
                ->withHeaders(['User-Agent' => 'Laravel WordPress Importer/1.0'])
                ->withOptions(['allow_redirects' => false, 'stream' => true])
                ->get($url);

            if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                $location = trim($response->header('Location'));
                if ($location === '') {
                    throw new RuntimeException('redirect ảnh không có URL đích');
                }
                $url = $this->resolveRedirect($url, $location);

                continue;
            }

            if (! $response->successful()) {
                throw new RuntimeException('máy chủ ảnh trả về HTTP '.$response->status());
            }

            $length = (int) $response->header('Content-Length');
            if ($length > self::MAX_BYTES) {
                throw new RuntimeException('ảnh lớn hơn 10MB');
            }

            return $response;
        }

        throw new RuntimeException('ảnh chuyển hướng quá nhiều lần');
    }

    private function limitedBody(Response $response): string
    {
        $stream = $response->toPsrResponse()->getBody();
        $body = '';
        while (! $stream->eof()) {
            $body .= $stream->read(8192);
            if (strlen($body) > self::MAX_BYTES) {
                throw new RuntimeException('ảnh lớn hơn 10MB');
            }
        }

        if ($body === '') {
            throw new RuntimeException('file ảnh rỗng');
        }

        return $body;
    }

    private function declaredMime(Response $response): string
    {
        return strtolower(trim(explode(';', $response->header('Content-Type'))[0]));
    }

    private function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('URL ảnh không hợp lệ');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolveIps($host);
        if ($ips === [] || collect($ips)->contains(fn (string $ip): bool => ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            throw new RuntimeException('URL ảnh trỏ tới mạng nội bộ hoặc host không phân giải được');
        }
    }

    /** @return list<string> */
    private function resolveIps(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_unique(array_filter(array_map(
            fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        ))));
    }

    private function resolveRedirect(string $source, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $scheme = (string) parse_url($source, PHP_URL_SCHEME);
        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }

        $host = (string) parse_url($source, PHP_URL_HOST);
        $port = parse_url($source, PHP_URL_PORT);
        $authority = $scheme.'://'.$host.($port ? ':'.$port : '');
        if (str_starts_with($location, '/')) {
            return $authority.$location;
        }

        $directory = rtrim(str_replace('\\', '/', dirname((string) parse_url($source, PHP_URL_PATH))), '/');

        return $authority.($directory === '' ? '' : $directory).'/'.$location;
    }
}
