<?php

namespace App\Services\AiGateway\Concerns;

use App\Models\AiSuggestion;
use App\Models\Post;
use App\Models\User;
use App\Services\AiGateway\GatewayUnavailable;
use App\Services\AiGateway\MetaSuggestionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * What every AI suggestion flow shares: finding the record the user may change,
 * rejecting, and reporting back to the gateway. Expects the using class to have
 * $gateway (GatewayClient), $features (FeatureGate), $languages (LanguageRegistry)
 * and $seoAnalyzer (PostSeoAnalyzer).
 */
trait HandlesAiSuggestions
{
    public function reject(AiSuggestion $suggestion, User $user, ?string $reason = null): void
    {
        $this->resolve($suggestion->entity_type, $suggestion->entity_id, $user);

        if ($suggestion->status !== AiSuggestion::PROPOSED) {
            return;
        }

        $suggestion->update(['status' => AiSuggestion::REJECTED, 'user_id' => $user->id]);
        $this->report($suggestion, 'rejected', ['reason_code' => $reason]);
    }

    /**
     * The record behind a suggestion, once the user may change it.
     *
     * @return array{0: array, 1: Model}
     */
    protected function resolve(string $type, int $id, User $user): array
    {
        $definition = MetaSuggestionService::ENTITIES[$type] ?? abort(404, __('ai.errors.not_found'));

        // Same rule as the feature middleware: superadmin is not held back by a switched-off feature.
        abort_unless($this->features->availableTo($user, $definition['feature']), 404, __('ai.errors.not_found'));
        abort_unless($user->can($definition['permission']), 403, __('ai.errors.forbidden'));

        $record = $definition['model']::query()->find($id) ?? abort(404, __('ai.errors.not_found'));

        return [$definition, $record];
    }

    /** The post's SEO score is computed on the default locale, as the post form does. */
    protected function refreshPostScore(Post $post): void
    {
        $locale = $this->languages->defaultLocale();
        $analysis = $this->seoAnalyzer->analyze([
            'title' => (string) $post->getTranslation('title', $locale, false),
            'slug' => (string) $post->slug,
            'content' => (string) $post->getTranslation('content', $locale, false),
            'seo_title' => (string) $post->getTranslation('seo_title', $locale, false),
            'seo_description' => (string) $post->getTranslation('seo_description', $locale, false),
            'focus_keyword' => $post->seo_keys,
        ]);

        $post->seo_score = $analysis['score'];
        $post->seo_analysis = $analysis;
    }

    /**
     * Write a record's body through the path its own form uses. Needs $htmlSanitizer,
     * $pageSanitizer and $pages (PageContentService) on the using class.
     */
    protected function writeBody(Model $record, array $definition, string $locale, string $html, User $user): void
    {
        if ($record instanceof \App\Models\Page) {
            // Same path as the inline editor: sanitizer + revision checkpoint.
            $this->pages->updateLocale($record, $locale, $html, $user->id);

            return;
        }

        $record->setTranslation($definition['content'], $locale, $this->htmlSanitizer->clean($html));
        if ($record instanceof Post) {
            $this->refreshPostScore($record);
        }
        $record->save();
    }

    protected function sanitizeBody(Model $record, string $html): string
    {
        return $record instanceof \App\Models\Page ? $this->pageSanitizer->clean($html) : $this->htmlSanitizer->clean($html);
    }

    /** Every href/src in order: an AI edit must leave this list exactly as it was. */
    protected function bodyReferences(string $html): array
    {
        preg_match_all('/\s(?:href|src)\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $html, $matches);

        return array_map(fn ($double, $single) => html_entity_decode($double !== '' ? $double : $single), $matches[2], $matches[3]);
    }

    /** Text the AI wrote from scratch carries no links or images of its own. */
    protected function withoutLinksAndImages(string $html): string
    {
        $html = (string) preg_replace('/<img\b[^>]*>/i', '', $html);

        return (string) preg_replace('/<a\b[^>]*>(.*?)<\/a>/is', '$1', $html);
    }

    protected function body(array $definition, Model $record, string $locale): string
    {
        return (string) $record->getTranslation($definition['content'], $locale, false);
    }

    /** Feedback for the gateway's metrics. Never blocks or undoes what the admin did. */
    protected function report(AiSuggestion $suggestion, string $event, array $data = []): void
    {
        try {
            $this->gateway->post("v1/suggestions/{$suggestion->gateway_suggestion_id}/events", [], array_filter(
                ['event' => $event] + $data,
                fn ($value) => $value !== null,
            ));
        } catch (GatewayUnavailable $e) {
            Log::info('AI suggestion event not delivered.', ['event' => $event, 'reason' => $e->reason]);
        }
    }
}
