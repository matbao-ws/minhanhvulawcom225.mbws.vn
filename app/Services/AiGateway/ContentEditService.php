<?php

namespace App\Services\AiGateway;

use App\Models\AiSuggestion;
use App\Models\Page;
use App\Models\Post;
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
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * "AI copy edit" of a record's saved body (product description, post content,
 * policy page HTML). The model returns the whole edited HTML; before anyone
 * sees it, it goes through the same sanitizer the record's own form uses, and
 * it is thrown away if it changed any link or image — the prompt forbids that,
 * and this is the check that enforces it. Applying writes through the record's
 * normal path (Page: PageContentService, with its revision checkpoint).
 */
class ContentEditService
{
    use HandlesAiSuggestions;

    public const TASK = 'content.edit';

    public const LEVELS = ['light', 'medium'];

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
     * @return array{0: AiSuggestion, 1: array<string, int>} the suggestion and the credit usage after it
     *
     * @throws GatewayUnavailable
     */
    public function suggest(string $type, int $id, string $locale, string $level, User $user): array
    {
        [$definition, $record] = $this->resolve($type, $id, $user);
        abort_unless($this->languages->supports($locale), 422, __('ai.errors.locale'));
        abort_unless(in_array($level, self::LEVELS, true), 422);

        // The saved body, never what the browser is holding: only stored content leaves the site.
        $html = (string) $record->getTranslation($definition['content'], $locale, false);
        abort_if(trim(strip_tags($html)) === '', 422, __('ai.edit.empty'));

        $response = $this->gateway->task(self::TASK, [
            'locale' => $locale,
            'entity_type' => $type,
            'entity_id' => $record->getKey(),
            'html' => $html,
            'level' => $level,
        ], (string) Str::uuid());

        $edited = $this->sanitize($record, (string) ($response['result']['html'] ?? ''));

        if ($this->references($edited) !== $this->references($this->sanitize($record, $html))) {
            // Links and images are the author's, not the editor's.
            throw new GatewayUnavailable('unsafe_output');
        }

        $suggestion = AiSuggestion::query()->create([
            'gateway_suggestion_id' => (int) $response['suggestion_id'],
            'task' => self::TASK,
            'entity_type' => $type,
            'entity_id' => $record->getKey(),
            'locale' => $locale,
            'old_values' => ['html' => $html],
            'options' => [[
                'html' => $edited,
                'changes' => array_slice(array_values(array_filter((array) ($response['result']['changes'] ?? []), 'is_array')), 0, 30),
                'level' => $level,
            ]],
            'status' => AiSuggestion::PROPOSED,
            'user_id' => $user->id,
        ]);

        return [$suggestion, (array) ($response['usage'] ?? [])];
    }

    /**
     * Write the edited body to the record.
     *
     * @throws HttpException 409 when the body changed since, 410 when closed
     */
    public function apply(AiSuggestion $suggestion, User $user): Model
    {
        abort_unless($suggestion->task === self::TASK, 404, __('ai.errors.not_found'));
        [$definition, $record] = $this->resolve($suggestion->entity_type, $suggestion->entity_id, $user);
        abort_unless($suggestion->isOpen(), 410, __('ai.errors.closed'));

        $locale = $suggestion->locale;
        $html = (string) ($suggestion->options[0]['html'] ?? '');

        DB::transaction(function () use ($suggestion, $definition, $record, $locale, $html, $user) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());

            if ((string) $record->getTranslation($definition['content'], $locale, false) !== $suggestion->old_values['html']) {
                abort(409, __('ai.errors.conflict_content'));
            }

            if ($record instanceof Page) {
                // Same path as the inline editor: sanitizer + revision checkpoint.
                $this->pages->updateLocale($record, $locale, $html, $user->id);
            } else {
                $record->setTranslation($definition['content'], $locale, $html);
                if ($record instanceof Post) {
                    $this->refreshPostScore($record);
                }
                $record->save();
            }

            $suggestion->update([
                'status' => AiSuggestion::APPLIED,
                'applied_option' => 0,
                'user_id' => $user->id,
                'applied_at' => now(),
            ]);

            ActivityLogger::log('ai_content_edited', $record, 'Áp dụng biên tập nội dung bằng AI ('.$locale.')', [
                'level' => $suggestion->options[0]['level'] ?? null,
                'changes' => count($suggestion->options[0]['changes'] ?? []),
            ]);
        });

        // The edited HTML is applied as proposed; there is no in-modal editing of it.
        $this->report($suggestion, 'applied', ['option_index' => 0, 'edit_distance' => 0]);

        return $record;
    }

    private function sanitize(Model $record, string $html): string
    {
        return $record instanceof Page ? $this->pageSanitizer->clean($html) : $this->htmlSanitizer->clean($html);
    }

    /**
     * Every href/src in the HTML, in order. An edit that changes this list
     * added, removed or retargeted a link or image.
     *
     * @return list<string>
     */
    private function references(string $html): array
    {
        preg_match_all('/\s(?:href|src)\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $html, $matches);

        return array_map(fn ($double, $single) => html_entity_decode($double !== '' ? $double : $single), $matches[2], $matches[3]);
    }
}
