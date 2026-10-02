<?php

namespace App\Services\AiGateway;

use App\Models\AiSuggestion;
use App\Models\Post;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AiGateway\Concerns\HandlesAiSuggestions;
use App\Services\LanguageRegistry;
use App\Services\LocalizedSlugService;
use App\Services\PostSeoAnalyzer;
use App\Support\FeatureGate;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A new blog post written by the AI from the owner's brief ("draft") or notes
 * ("notes"). It is saved **unpublished** and opened in the normal post editor;
 * the claims to verify and the missing information travel with it, and only
 * the owner publishes. The AI never adds links or images: anything like that in
 * its HTML is removed, as is whatever the post sanitizer does not allow.
 */
class PostDraftService
{
    use HandlesAiSuggestions;

    public const MODES = ['draft' => 'content.draft', 'notes' => 'content.from_notes'];

    public const TONES = ['thân thiện', 'chuyên nghiệp', 'trang trọng', 'gần gũi, vui vẻ'];

    public const MIN_WORDS = 300;

    /** One synchronous request (gateway prompt timeout 50 s); longer posts need a job. */
    public const MAX_WORDS = 1500;

    /** Seconds the website waits: the gateway's 50 s plus margin. */
    public const TIMEOUT = 58;

    public function __construct(
        private readonly GatewayClient $gateway,
        private readonly FeatureGate $features,
        private readonly LanguageRegistry $languages,
        private readonly PostSeoAnalyzer $seoAnalyzer,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly LocalizedSlugService $localizedSlugs,
    ) {}

    /**
     * @param  array<string, mixed>  $brief  validated input of the chosen mode
     *
     * @throws GatewayUnavailable
     */
    public function create(string $mode, array $brief, User $user): Post
    {
        abort_unless(isset(self::MODES[$mode]), 404);
        abort_unless($this->features->availableTo($user, 'cms_page'), 404, __('ai.errors.not_found'));
        abort_unless($user->can('posts.create'), 403, __('ai.errors.forbidden'));

        // Posts are titled in the default language first; a draft starts there too.
        $locale = $this->languages->defaultLocale();
        $task = self::MODES[$mode];

        $response = $this->gateway->task($task, ['locale' => $locale] + $brief, (string) Str::uuid(), self::TIMEOUT);
        $result = (array) ($response['result'] ?? []);

        $title = Str::limit(trim((string) ($result['title'] ?? '')), 250, '');
        $html = $this->clean((string) ($result['html'] ?? ''));
        abort_if($title === '' || trim(strip_tags($html)) === '', 422, __('ai.errors.default'));

        return DB::transaction(function () use ($mode, $task, $locale, $title, $html, $result, $response, $brief, $user) {
            $post = new Post([
                'title' => [$locale => $title],
                'slug' => $this->uniqueSlug($title),
                'content' => [$locale => $html],
                'seo_title' => [$locale => Str::limit((string) ($result['meta_title'] ?? ''), 255, '')],
                'seo_description' => [$locale => Str::limit((string) ($result['meta_description'] ?? ''), 500, '')],
                'seo_keys' => $mode === 'draft' ? Str::limit((string) ($brief['keyword'] ?? ''), 255, '') : null,
                'is_active' => false,
                'published_at' => null,
                'robots_index' => true,
                'robots_follow' => true,
            ]);
            $this->refreshPostScore($post);
            $post->save();
            $this->localizedSlugs->sync($post, [], [$locale => $title]);

            $suggestion = AiSuggestion::query()->create([
                'gateway_suggestion_id' => (int) ($response['suggestion_id'] ?? 0),
                'task' => $task,
                'entity_type' => 'post',
                'entity_id' => $post->id,
                'locale' => $locale,
                'old_values' => [],
                'options' => [[
                    'claims_to_verify' => array_values(array_filter((array) ($result['claims_to_verify'] ?? []), 'is_string')),
                    'missing_info' => array_values(array_filter((array) ($result['missing_info'] ?? []), 'is_string')),
                ]],
                // Saving it as an unpublished post is the acceptance; publishing stays with the owner.
                'status' => AiSuggestion::APPLIED,
                'applied_option' => 0,
                'user_id' => $user->id,
                'applied_at' => now(),
            ]);

            ActivityLogger::log('ai_post_drafted', $post, 'Tạo bài nháp bằng AI ('.($mode === 'draft' ? 'từ thông tin doanh nghiệp' : 'từ ghi chú').')');

            DB::afterCommit(fn () => $this->report($suggestion, 'applied', ['option_index' => 0]));

            return $post;
        });
    }

    /**
     * What the owner must still check on an AI draft, while it is unpublished.
     *
     * @return array{claims_to_verify: list<string>, missing_info: list<string>}|null
     */
    public function review(Post $post): ?array
    {
        if ($post->is_active) {
            return null;
        }

        $suggestion = AiSuggestion::query()
            ->where('entity_type', 'post')
            ->where('entity_id', $post->id)
            ->whereIn('task', array_values(self::MODES))
            ->latest('id')
            ->first();

        return $suggestion ? [
            'claims_to_verify' => (array) ($suggestion->options[0]['claims_to_verify'] ?? []),
            'missing_info' => (array) ($suggestion->options[0]['missing_info'] ?? []),
        ] : null;
    }

    /** The post sanitizer, minus links and images the AI must not have added. */
    private function clean(string $html): string
    {
        $html = (string) preg_replace('/<img\b[^>]*>/i', '', $html);
        $html = (string) preg_replace('/<a\b[^>]*>(.*?)<\/a>/is', '$1', $html);

        return $this->htmlSanitizer->clean($html);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: Str::lower(Str::random(8));
        $slug = $base;

        for ($counter = 2; Post::query()->where('slug', $slug)->exists(); $counter++) {
            $slug = $base.'-'.$counter;
        }

        return $slug;
    }
}
