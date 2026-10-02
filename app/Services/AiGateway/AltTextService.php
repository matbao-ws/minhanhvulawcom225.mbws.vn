<?php

namespace App\Services\AiGateway;

use App\Models\AiSuggestion;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AiGateway\Concerns\HandlesAiSuggestions;
use App\Services\CloudinaryCredentials;
use App\Services\LanguageRegistry;
use App\Services\PageContentService;
use App\Services\PostSeoAnalyzer;
use App\Support\FeatureGate;
use App\Support\HtmlSanitizer;
use App\Support\MediaUrl;
use App\Support\PageHtmlSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Alt text for images in a record's body that have none.
 *
 * The website reads the image itself and sends the bytes: only from its own
 * public disk or its own Cloudinary account. Anything else (a hot-linked image
 * on another host) is refused rather than fetched, so neither the website nor
 * the gateway can be steered into requesting an arbitrary URL.
 */
class AltTextService
{
    use HandlesAiSuggestions;

    public const TASK = 'media.alt_text';

    public const ENTITY_TYPES = ['post', 'product', 'page'];

    public const MAX_BYTES = 5 * 1024 * 1024;

    private const MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(
        private readonly GatewayClient $gateway,
        private readonly FeatureGate $features,
        private readonly LanguageRegistry $languages,
        private readonly PostSeoAnalyzer $seoAnalyzer,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly PageHtmlSanitizer $pageSanitizer,
        private readonly PageContentService $pages,
        private readonly CloudinaryCredentials $cloudinary,
    ) {}

    /**
     * Images of the body that have no (or an empty) alt, by src.
     *
     * @return list<string>
     */
    public function missing(string $type, int $id, string $locale, User $user): array
    {
        abort_unless(in_array($type, self::ENTITY_TYPES, true), 404, __('ai.errors.not_found'));
        [$definition, $record] = $this->resolve($type, $id, $user);

        return $this->imagesWithoutAlt($this->body($definition, $record, $locale));
    }

    /**
     * @return array{0: AiSuggestion, 1: array<string, int>}
     *
     * @throws GatewayUnavailable
     */
    public function suggest(string $type, int $id, string $locale, string $src, User $user): array
    {
        abort_unless(in_array($type, self::ENTITY_TYPES, true), 404, __('ai.errors.not_found'));
        [$definition, $record] = $this->resolve($type, $id, $user);
        abort_unless($this->languages->supports($locale), 422, __('ai.errors.locale'));

        $html = $this->body($definition, $record, $locale);
        abort_unless(in_array($src, $this->imagesWithoutAlt($html), true), 422, __('ai.alt.not_missing'));

        [$mime, $bytes] = $this->read($src);

        $response = $this->gateway->task(self::TASK, [
            'locale' => $locale,
            'entity_type' => $type,
            'entity_id' => $record->getKey(),
            'src' => Str::limit($src, 2000, ''),
            'page_title' => Str::limit((string) $record->getTranslation($definition['title'], $locale, false), 290, ''),
            'context' => Str::limit($this->context($html, $src), 1400, ''),
            'image' => ['mime_type' => $mime, 'data' => base64_encode($bytes)],
        ], (string) Str::uuid());

        $suggestion = AiSuggestion::query()->create([
            'gateway_suggestion_id' => (int) $response['suggestion_id'],
            'task' => self::TASK,
            'entity_type' => $type,
            'entity_id' => $record->getKey(),
            'locale' => $locale,
            'old_values' => ['src' => $src],
            'options' => [['alt' => (string) ($response['result']['alt'] ?? '')]],
            'status' => AiSuggestion::PROPOSED,
            'user_id' => $user->id,
        ]);

        return [$suggestion, (array) ($response['usage'] ?? [])];
    }

    /** Set the (possibly edited) alt on every alt-less <img> with that src. */
    public function apply(AiSuggestion $suggestion, string $alt, User $user): Model
    {
        abort_unless($suggestion->task === self::TASK, 404, __('ai.errors.not_found'));
        [$definition, $record] = $this->resolve($suggestion->entity_type, $suggestion->entity_id, $user);
        abort_unless($suggestion->isOpen(), 410, __('ai.errors.closed'));

        $src = (string) $suggestion->old_values['src'];

        DB::transaction(function () use ($suggestion, $definition, $record, $src, $alt, $user) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());
            $html = $this->body($definition, $record, $suggestion->locale);

            // Per image, not per body: several images are captioned one after another.
            if (! in_array($src, $this->imagesWithoutAlt($html), true)) {
                abort(409, __('ai.alt.conflict'));
            }

            $updated = (string) preg_replace_callback('/<img\b[^>]*>/i', function (array $match) use ($src, $alt) {
                $tag = $match[0];
                if ($this->srcOf($tag) !== $src || $this->hasAlt($tag)) {
                    return $tag;
                }
                $tag = (string) preg_replace('/\salt\s*=\s*(["\'])\s*\1/i', '', $tag);

                return (string) preg_replace('/^<img\b/i', '<img alt="'.e($alt).'"', $tag);
            }, $html);

            $this->writeBody($record, $definition, $suggestion->locale, $updated, $user);
            $suggestion->update(['status' => AiSuggestion::APPLIED, 'applied_option' => 0, 'user_id' => $user->id, 'applied_at' => now()]);

            ActivityLogger::log('ai_alt_added', $record, 'Thêm alt ảnh do AI gợi ý ('.$suggestion->locale.')', ['src' => $src]);
        });

        $proposed = (string) ($suggestion->options[0]['alt'] ?? '');
        $this->report($suggestion, trim($proposed) === trim($alt) ? 'applied' : 'applied_after_edit', ['option_index' => 0]);

        return $record;
    }

    /** @return list<string> */
    private function imagesWithoutAlt(string $html): array
    {
        preg_match_all('/<img\b[^>]*>/i', $html, $tags);

        return array_values(array_unique(array_filter(array_map(
            fn (string $tag) => $this->hasAlt($tag) ? null : $this->srcOf($tag),
            $tags[0],
        ))));
    }

    private function srcOf(string $tag): ?string
    {
        return preg_match('/\ssrc\s*=\s*(["\'])(.*?)\1/i', $tag, $m) ? html_entity_decode($m[2]) : null;
    }

    private function hasAlt(string $tag): bool
    {
        return preg_match('/\salt\s*=\s*(["\'])\s*\S.*?\1/i', $tag) === 1;
    }

    /** Text around the image, so the model knows what it illustrates. */
    private function context(string $html, string $src): string
    {
        $position = strpos($html, $src);
        $around = $position === false ? $html : substr($html, max(0, $position - 1500), 3000);

        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($around), ENT_QUOTES | ENT_HTML5)));
    }

    /**
     * The image bytes, from this site's own storage only.
     *
     * @return array{0: string, 1: string} mime type and bytes
     *
     * @throws GatewayUnavailable image_unavailable
     */
    private function read(string $src): array
    {
        $bytes = null;

        if (($path = MediaUrl::publicDiskPath($src)) !== null && Storage::disk('public')->exists($path)) {
            $bytes = Storage::disk('public')->size($path) <= self::MAX_BYTES ? Storage::disk('public')->get($path) : null;
        } elseif ($this->isOwnCloudinary($src)) {
            $response = rescue(fn () => Http::timeout(10)->withOptions(['allow_redirects' => false])->get($src), null, false);
            if ($response?->successful() && strlen($response->body()) <= self::MAX_BYTES) {
                $bytes = $response->body();
            }
        }

        $mime = $bytes !== null ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) : null;

        if ($bytes === null || ! in_array($mime, self::MIME_TYPES, true)) {
            throw new GatewayUnavailable('image_unavailable');
        }

        return [$mime, $bytes];
    }

    private function isOwnCloudinary(string $src): bool
    {
        $cloud = (string) ($this->cloudinary->current()['cloud_name'] ?? '');
        $parts = parse_url($src);

        return $cloud !== ''
            && ($parts['scheme'] ?? '') === 'https'
            && strtolower($parts['host'] ?? '') === 'res.cloudinary.com'
            && str_starts_with((string) ($parts['path'] ?? ''), '/'.$cloud.'/');
    }
}
