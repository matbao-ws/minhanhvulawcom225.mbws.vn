<?php

namespace App\Services\AiGateway;

use App\Models\AiSuggestion;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AiGateway\Concerns\HandlesAiSuggestions;
use App\Services\LanguageRegistry;
use App\Services\PageContentService;
use App\Services\PostSeoAnalyzer;
use App\Services\SiteBranding;
use App\Support\FeatureGate;
use App\Support\HtmlSanitizer;
use App\Support\PageHtmlSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Answer what people search": one new section (heading, paragraphs, bullets) covering the
 * Google searches that show a page but that its body does not answer yet. The model writes
 * plain text; the website builds the HTML, so the section carries no links, images or markup
 * of the model's own. It goes in before the page's FAQ section, or at the end.
 */
class QueryGapService
{
    use HandlesAiSuggestions;

    public const TASK = 'content.query_gap';

    public const ENTITY_TYPES = ['post', 'product', 'page', 'category'];

    public function __construct(
        private readonly GatewayClient $gateway,
        private readonly FeatureGate $features,
        private readonly LanguageRegistry $languages,
        private readonly PostSeoAnalyzer $seoAnalyzer,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly PageHtmlSanitizer $pageSanitizer,
        private readonly PageContentService $pages,
        private readonly SiteBranding $branding,
    ) {}

    /**
     * @param  list<string>  $queries  the searches to answer, most seen first
     * @return array{0: AiSuggestion, 1: array<string, int>}
     *
     * @throws GatewayUnavailable
     */
    public function suggest(string $type, int $id, string $locale, array $queries, User $user): array
    {
        abort_unless(in_array($type, self::ENTITY_TYPES, true), 404, __('ai.errors.not_found'));
        [$definition, $record] = $this->resolve($type, $id, $user);
        abort_unless($this->languages->supports($locale), 422, __('ai.errors.locale'));

        $queries = array_slice(array_filter(array_map(
            fn ($query) => Str::limit(trim((string) $query), 290, ''), $queries), fn (string $query) => $query !== ''), 0, 10);
        abort_if($queries === [], 422, __('ai.tasks.fix.no_queries'));

        $html = $this->body($definition, $record, $locale);
        $response = $this->gateway->task(self::TASK, [
            'locale' => $locale,
            'entity_type' => $type,
            'entity_id' => $record->getKey(),
            'title' => Str::limit($this->text($record, $definition['title'], $locale), 290, ''),
            'html' => $html,
            'queries' => $queries,
            'focus_keyword' => filled($record->getAttribute('seo_keys'))
                ? Str::limit(PostSeoAnalyzer::primaryKeyword((string) $record->getAttribute('seo_keys')), 120, '')
                : null,
            'site_name' => Str::limit((string) ($this->branding->current()['name'] ?? ''), 120, '') ?: null,
        ], (string) Str::uuid());

        $result = (array) ($response['result'] ?? []);
        $section = [
            'heading' => trim((string) ($result['heading'] ?? '')),
            'paragraphs' => array_values(array_filter(array_map(fn ($text) => trim((string) $text), (array) ($result['paragraphs'] ?? [])))),
            'bullets' => array_values(array_filter(array_map(fn ($text) => trim((string) $text), (array) ($result['bullets'] ?? [])))),
            'covers' => array_values(array_intersect(array_map('strval', (array) ($result['covers'] ?? [])), $queries)),
        ];
        abort_if($section['heading'] === '' || $section['paragraphs'] === [], 502, __('ai.errors.default'));

        $suggestion = AiSuggestion::query()->create([
            'gateway_suggestion_id' => (int) $response['suggestion_id'],
            'task' => self::TASK,
            'entity_type' => $type,
            'entity_id' => $record->getKey(),
            'locale' => $locale,
            'old_values' => ['html' => $html],
            'options' => [$section],
            'status' => AiSuggestion::PROPOSED,
            'user_id' => $user->id,
        ]);

        return [$suggestion, (array) ($response['usage'] ?? [])];
    }

    /**
     * Add the (possibly edited) section to the body.
     *
     * @param  array{heading?: string, paragraphs?: list<string>, bullets?: list<string>}|null  $section  null keeps the proposal
     */
    public function apply(AiSuggestion $suggestion, User $user, ?array $section = null): Model
    {
        abort_unless($suggestion->task === self::TASK, 404, __('ai.errors.not_found'));
        [$definition, $record] = $this->resolve($suggestion->entity_type, $suggestion->entity_id, $user);
        abort_unless($suggestion->isOpen(), 410, __('ai.errors.closed'));

        $proposal = (array) ($suggestion->options[0] ?? []);
        $final = $this->clean($section ?? $proposal);
        abort_if($final['heading'] === '' || $final['paragraphs'] === [], 422, __('ai.errors.option'));

        DB::transaction(function () use ($suggestion, $definition, $record, $final, $user) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());
            $current = $this->body($definition, $record, $suggestion->locale);

            if ($current !== $suggestion->old_values['html']) {
                abort(409, __('ai.errors.conflict_content'));
            }

            $this->writeBody($record, $definition, $suggestion->locale, $this->insert($current, $this->html($final)), $user);
            $suggestion->update(['status' => AiSuggestion::APPLIED, 'applied_option' => 0, 'user_id' => $user->id, 'applied_at' => now()]);

            ActivityLogger::log('ai_query_gap_added', $record, 'Thêm đoạn nội dung theo từ khoá khách tìm do AI viết ('.$suggestion->locale.')', [
                'heading' => $final['heading'],
            ]);
        });

        $this->report($suggestion, $this->clean($proposal) === $final ? 'applied' : 'applied_after_edit', ['option_index' => 0]);

        return $record;
    }

    private function text(Model $record, string $field, string $locale): string
    {
        return method_exists($record, 'getTranslation') ? (string) $record->getTranslation($field, $locale, false) : '';
    }

    /** @return array{heading: string, paragraphs: list<string>, bullets: list<string>} */
    private function clean(array $section): array
    {
        $texts = fn ($values, int $max) => array_values(array_slice(array_filter(array_map(
            fn ($text) => Str::limit(trim(strip_tags((string) $text)), $max, ''), (array) $values), fn (string $text) => $text !== ''), 0, 6));

        return [
            'heading' => Str::limit(trim(strip_tags((string) ($section['heading'] ?? ''))), 120, ''),
            'paragraphs' => $texts($section['paragraphs'] ?? [], 1000),
            'bullets' => $texts($section['bullets'] ?? [], 300),
        ];
    }

    /** @param  array{heading: string, paragraphs: list<string>, bullets: list<string>}  $section */
    private function html(array $section): string
    {
        $html = '<h2>'.e($section['heading']).'</h2>';
        foreach ($section['paragraphs'] as $paragraph) {
            $html .= '<p>'.e($paragraph).'</p>';
        }
        if ($section['bullets'] !== []) {
            $html .= '<ul>'.implode('', array_map(fn (string $bullet) => '<li>'.e($bullet).'</li>', $section['bullets'])).'</ul>';
        }

        return $html;
    }

    /** Before the FAQ section the FAQ tool added (it stays last), otherwise at the end. */
    private function insert(string $body, string $section): string
    {
        $faq = '<h2>'.e(__('ai.faq.heading')).'</h2>';
        $position = strrpos($body, $faq);

        return $position === false ? $body.$section : substr($body, 0, $position).$section.substr($body, $position);
    }
}
