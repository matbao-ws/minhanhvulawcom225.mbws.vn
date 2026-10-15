<?php

namespace App\Services\AiGateway;

use App\Models\AiSuggestion;
use App\Models\User;
use App\Support\Seo\SeoBox;
use Illuminate\Database\Eloquent\Model;

/**
 * AI suggestions the admin asked for on a record and has not yet applied or rejected — the answer
 * came back after they left the page, or they closed the review. The credit is already spent, so
 * the form offers them again (admin.shared.ai-pending) in the same shape the suggest endpoints
 * return, and the tool's own review opens on them.
 */
class PendingSuggestions
{
    /** The tasks a form can reopen, with the button whose review shows each one. */
    public const TASKS = [
        MetaSuggestionService::TASK => '.js-ai-meta-suggest',
        ContentEditService::TASK => '.js-ai-edit',
        FaqService::TASK => '.js-ai-faq',
        InternalLinkService::TASK => '.js-ai-links',
        ProductDescriptionService::TASK => '.js-ai-product',
        CollectionIntroService::TASK => '.js-ai-collection',
        SeoFixService::TASK => '.js-ai-seo-fix',
    ];

    private const LIMIT = 10;

    public function __construct(private readonly SeoBox $seoBox) {}

    /**
     * The newest open suggestion per tool and language. One written for a body that has changed
     * since is left out: applying it would only be refused as a conflict.
     *
     * @return list<array{id: int, task: string, locale: string, created_at: string, selector: string, data: array<string, mixed>}>
     */
    public function forRecord(string $type, Model $record, User $user): array
    {
        $content = MetaSuggestionService::ENTITIES[$type]['content'] ?? CollectionIntroService::ENTITIES[$type]['content'] ?? null;

        return AiSuggestion::query()
            ->where('entity_type', $type)
            ->where('entity_id', $record->getKey())
            ->where('user_id', $user->id)
            ->where('status', AiSuggestion::PROPOSED)
            ->whereIn('task', array_keys(self::TASKS))
            ->where('created_at', '>', now()->subDays(AiSuggestion::TTL_DAYS))
            ->latest('id')
            ->limit(self::LIMIT * 3)
            ->get()
            ->unique(fn (AiSuggestion $suggestion) => $suggestion->task.'|'.$suggestion->locale)
            ->reject(fn (AiSuggestion $suggestion) => isset($suggestion->old_values['html']) && $content !== null
                && method_exists($record, 'getTranslation')
                && (string) $record->getTranslation($content, $suggestion->locale, false) !== $suggestion->old_values['html'])
            ->take(self::LIMIT)
            ->map(fn (AiSuggestion $suggestion) => [
                'id' => $suggestion->id,
                'task' => $suggestion->task,
                'locale' => $suggestion->locale,
                'created_at' => (string) $suggestion->created_at?->diffForHumans(),
                'selector' => self::TASKS[$suggestion->task],
                'data' => $this->data($suggestion, $record),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> what the tool's suggest endpoint answered, without the credits */
    private function data(AiSuggestion $suggestion, Model $record): array
    {
        $urls = fn (string $tool, string $reject = 'admin.ai.reject') => [
            'suggestion_id' => $suggestion->id,
            'apply_url' => route("admin.ai.$tool.apply", ['suggestion' => $suggestion->id]),
            'reject_url' => route($reject, ['suggestion' => $suggestion->id]),
        ];
        $first = (array) ($suggestion->options[0] ?? []);

        return match ($suggestion->task) {
            MetaSuggestionService::TASK => ['current' => $suggestion->old_values, 'options' => $suggestion->options, 'focus_keyword' => null] + $urls('meta', 'admin.ai.meta.reject'),
            ContentEditService::TASK => ['html' => $first['html'] ?? '', 'changes' => $first['changes'] ?? []] + $urls('content', 'admin.ai.content.reject'),
            FaqService::TASK => ['items' => $suggestion->options] + $urls('faq'),
            InternalLinkService::TASK => ['links' => $suggestion->options] + $urls('links'),
            ProductDescriptionService::TASK => $first + $urls('product'),
            CollectionIntroService::TASK => $first + $urls('collection'),
            SeoFixService::TASK => ['current' => $suggestion->old_values, 'proposal' => $first,
                'score' => isset(SeoBox::MODELS[$record::class]) ? $this->seoBox->analyze($record)['score'] : null] + $urls('seo'),
            default => [],
        };
    }
}
