<?php

namespace App\Services\AiGateway;

use App\Models\AiSuggestion;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AiGateway\Concerns\HandlesAiSuggestions;
use App\Services\LanguageRegistry;
use App\Services\PageContentService;
use App\Services\PostSeoAnalyzer;
use App\Support\FeatureGate;
use App\Support\HtmlSanitizer;
use App\Support\PageHtmlSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "FAQ from this content": 3–6 questions the page itself answers, edited by
 * the owner in the modal, then appended to the body as an FAQ section.
 */
class FaqService
{
    use HandlesAiSuggestions;

    public const TASK = 'content.faq';

    public const ENTITY_TYPES = ['post', 'product', 'page'];

    public function __construct(
        private readonly GatewayClient $gateway,
        private readonly FeatureGate $features,
        private readonly LanguageRegistry $languages,
        private readonly PostSeoAnalyzer $seoAnalyzer,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly PageHtmlSanitizer $pageSanitizer,
        private readonly PageContentService $pages,
    ) {}

    /**
     * @return array{0: AiSuggestion, 1: array<string, int>}
     *
     * @throws GatewayUnavailable
     */
    public function suggest(string $type, int $id, string $locale, User $user): array
    {
        abort_unless(in_array($type, self::ENTITY_TYPES, true), 404, __('ai.errors.not_found'));
        [$definition, $record] = $this->resolve($type, $id, $user);
        abort_unless($this->languages->supports($locale), 422, __('ai.errors.locale'));

        $html = $this->body($definition, $record, $locale);
        abort_if(trim(strip_tags($html)) === '', 422, __('ai.edit.empty'));

        $response = $this->gateway->task(self::TASK, [
            'locale' => $locale,
            'entity_type' => $type,
            'entity_id' => $record->getKey(),
            'title' => Str::limit((string) $record->getTranslation($definition['title'], $locale, false), 290, ''),
            'html' => $html,
        ], (string) Str::uuid());

        $suggestion = AiSuggestion::query()->create([
            'gateway_suggestion_id' => (int) $response['suggestion_id'],
            'task' => self::TASK,
            'entity_type' => $type,
            'entity_id' => $record->getKey(),
            'locale' => $locale,
            'old_values' => ['html' => $html],
            'options' => array_values(array_filter((array) ($response['result']['items'] ?? []), 'is_array')),
            'status' => AiSuggestion::PROPOSED,
            'user_id' => $user->id,
        ]);

        return [$suggestion, (array) ($response['usage'] ?? [])];
    }

    /**
     * Append the chosen (possibly edited) questions as an FAQ section.
     *
     * @param  list<array{question: string, answer: string}>  $items
     */
    public function apply(AiSuggestion $suggestion, array $items, User $user): Model
    {
        abort_unless($suggestion->task === self::TASK, 404, __('ai.errors.not_found'));
        [$definition, $record] = $this->resolve($suggestion->entity_type, $suggestion->entity_id, $user);
        abort_unless($suggestion->isOpen(), 410, __('ai.errors.closed'));
        abort_if($items === [], 422, __('ai.errors.option'));

        DB::transaction(function () use ($suggestion, $definition, $record, $items, $user) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());
            $current = $this->body($definition, $record, $suggestion->locale);

            if ($current !== $suggestion->old_values['html']) {
                abort(409, __('ai.errors.conflict_content'));
            }

            $this->writeBody($record, $definition, $suggestion->locale, $current.$this->section($items), $user);
            $suggestion->update(['status' => AiSuggestion::APPLIED, 'applied_option' => 0, 'user_id' => $user->id, 'applied_at' => now()]);

            ActivityLogger::log('ai_faq_added', $record, 'Thêm FAQ do AI gợi ý ('.$suggestion->locale.', '.count($items).' câu)');
        });

        $this->report($suggestion, $this->edited($suggestion->options, $items) ? 'applied_after_edit' : 'applied', ['option_index' => 0]);

        return $record;
    }

    /** @param  list<array{question: string, answer: string}>  $items */
    private function section(array $items): string
    {
        $html = '<h2>'.e(__('ai.faq.heading')).'</h2>';

        foreach ($items as $item) {
            $html .= '<h3>'.e($item['question']).'</h3><p>'.e($item['answer']).'</p>';
        }

        return $html;
    }

    private function edited(array $proposed, array $final): bool
    {
        $normalise = fn (array $items) => array_map(fn ($item) => [trim((string) ($item['question'] ?? '')), trim((string) ($item['answer'] ?? ''))], $items);

        return $normalise($proposed) !== $normalise($final);
    }
}
