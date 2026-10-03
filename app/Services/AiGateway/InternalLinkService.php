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
use DOMDocument;
use DOMText;
use DOMXPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Internal links": pages of this website the gateway suggests linking from
 * the saved body, each on a phrase already in the text. Applying wraps the
 * chosen phrases in links — the words themselves are never changed — and only
 * where the phrase is plain text outside an existing link.
 */
class InternalLinkService
{
    use HandlesAiSuggestions;

    public const TASK = 'content.internal_links';

    public const ENTITY_TYPES = ['post', 'product'];

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

        $links = array_values(array_filter((array) ($response['result']['links'] ?? []), fn ($link) => is_array($link)
            && filled($link['anchor'] ?? null)
            && preg_match('#^(https?://|/)#i', (string) ($link['url'] ?? ''))));

        $suggestion = AiSuggestion::query()->create([
            'gateway_suggestion_id' => (int) $response['suggestion_id'],
            'task' => self::TASK,
            'entity_type' => $type,
            'entity_id' => $record->getKey(),
            'locale' => $locale,
            'old_values' => ['html' => $html],
            'options' => array_map(fn (array $link) => [
                'anchor' => (string) $link['anchor'],
                'url' => (string) $link['url'],
                'title' => (string) ($link['title'] ?? ''),
                'reason' => (string) ($link['reason'] ?? ''),
                'needs_review' => (bool) ($link['needs_review'] ?? false),
            ], $links),
            'status' => AiSuggestion::PROPOSED,
            'user_id' => $user->id,
        ]);

        return [$suggestion, (array) ($response['usage'] ?? [])];
    }

    /**
     * Insert the chosen links.
     *
     * @param  list<int>  $chosen  indexes into the suggestion's options
     * @return array{0: Model, 1: int} the record and how many links went in
     */
    public function apply(AiSuggestion $suggestion, array $chosen, User $user): array
    {
        abort_unless($suggestion->task === self::TASK, 404, __('ai.errors.not_found'));
        [$definition, $record] = $this->resolve($suggestion->entity_type, $suggestion->entity_id, $user);
        abort_unless($suggestion->isOpen(), 410, __('ai.errors.closed'));

        $links = array_values(array_filter(array_map(fn (int $i) => $suggestion->options[$i] ?? null, array_unique($chosen))));
        abort_if($links === [], 422, __('ai.errors.option'));

        $inserted = 0;
        DB::transaction(function () use ($suggestion, $definition, $record, $links, $user, &$inserted) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());
            $current = $this->body($definition, $record, $suggestion->locale);

            if ($current !== $suggestion->old_values['html']) {
                abort(409, __('ai.errors.conflict_content'));
            }

            [$html, $inserted] = self::insertLinks($current, $links);
            abort_if($inserted === 0, 422, __('ai.links.none_inserted'));

            $this->writeBody($record, $definition, $suggestion->locale, $html, $user);
            $suggestion->update(['status' => AiSuggestion::APPLIED, 'applied_option' => 0, 'user_id' => $user->id, 'applied_at' => now()]);

            ActivityLogger::log('ai_internal_links_added', $record, 'Thêm liên kết nội bộ do AI gợi ý ('.$suggestion->locale.', '.$inserted.' liên kết)', [
                'urls' => array_column($links, 'url'),
            ]);
        });

        $this->report($suggestion, count($links) === count($suggestion->options) ? 'applied' : 'applied_after_edit', ['option_index' => 0]);

        return [$record, $inserted];
    }

    /**
     * Wrap the first plain-text occurrence of each anchor (outside links) in a link.
     *
     * @param  list<array{anchor: string, url: string}>  $links
     * @return array{0: string, 1: int}
     */
    public static function insertLinks(string $html, array $links): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div id="__links_root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('__links_root');
        if (! $root) {
            return [$html, 0];
        }

        $xpath = new DOMXPath($document);
        $inserted = 0;

        foreach ($links as $link) {
            $anchor = trim((string) $link['anchor']);
            if ($anchor === '') {
                continue;
            }

            foreach ($xpath->query('.//text()[not(ancestor::a)]', $root) as $node) {
                /** @var DOMText $node */
                $text = $node->nodeValue;
                $at = mb_stripos($text, $anchor);
                if ($at === false) {
                    continue;
                }

                $length = mb_strlen($anchor);
                $match = $node->splitText($at);
                $match->splitText($length);

                $a = $document->createElement('a');
                $a->setAttribute('href', (string) $link['url']);
                $match->parentNode->replaceChild($a, $match);
                $a->appendChild($match);
                $inserted++;
                break;
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return [$out, $inserted];
    }
}
