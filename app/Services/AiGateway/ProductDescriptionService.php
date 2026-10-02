<?php

namespace App\Services\AiGateway;

use App\Models\AiSuggestion;
use App\Models\Product;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AiGateway\Concerns\HandlesAiSuggestions;
use App\Services\LanguageRegistry;
use App\Services\LocalizedContent;
use App\Services\PageContentService;
use App\Services\PostSeoAnalyzer;
use App\Support\FeatureGate;
use App\Support\HtmlSanitizer;
use App\Support\PageHtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "AI product description" from the product's own data. Prices, stock and
 * customer data are not sent: the description must not quote a price that
 * changes, and nothing but catalogue facts is needed.
 */
class ProductDescriptionService
{
    use HandlesAiSuggestions;

    public const TASK = 'content.product_description';

    public const MIN_WORDS = 100;

    public const MAX_WORDS = 800;

    public const TIMEOUT = 58;

    public function __construct(
        private readonly GatewayClient $gateway,
        private readonly FeatureGate $features,
        private readonly LanguageRegistry $languages,
        private readonly PostSeoAnalyzer $seoAnalyzer,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly PageHtmlSanitizer $pageSanitizer,
        private readonly PageContentService $pages,
        private readonly LocalizedContent $content,
    ) {}

    /**
     * @return array{0: AiSuggestion, 1: array<string, int>}
     *
     * @throws GatewayUnavailable
     */
    public function suggest(int $id, string $locale, ?string $sellingPoints, int $words, User $user): array
    {
        [$definition, $product] = $this->resolve('product', $id, $user);
        abort_unless($this->languages->supports($locale), 422, __('ai.errors.locale'));

        /** @var Product $product */
        $product->loadMissing(['category', 'brand', 'optionGroups.values']);
        $current = $this->body($definition, $product, $locale);
        $text = fn ($model, string $field) => $model ? Str::limit(trim(strip_tags((string) $this->content->get($model, $field, $locale))), 190, '') : null;

        $response = $this->gateway->task(self::TASK, [
            'locale' => $locale,
            'entity_type' => 'product',
            'entity_id' => $product->id,
            'name' => Str::limit((string) $this->content->get($product, 'name', $locale), 290, ''),
            'category' => $text($product->category, 'name'),
            'brand' => $text($product->brand, 'name'),
            'short_description' => Str::limit(trim(strip_tags((string) $this->content->get($product, 'short_description', $locale))), 2900, ''),
            'attributes' => $product->optionGroups->take(20)->map(fn ($group) => Str::limit(
                $this->content->get($group, 'name', $locale).': '.$group->values->map(fn ($value) => $this->content->get($value, 'label', $locale))->filter()->implode(', '),
                490, '',
            ))->values()->all(),
            'selling_points' => filled($sellingPoints) ? Str::limit($sellingPoints, 1990, '') : null,
            'current_description' => $current !== '' ? $current : null,
            'target_words' => $words,
        ], (string) Str::uuid(), self::TIMEOUT);

        $html = $this->htmlSanitizer->clean($this->withoutLinksAndImages((string) ($response['result']['html'] ?? '')));
        abort_if(trim(strip_tags($html)) === '', 422, __('ai.errors.default'));

        $suggestion = AiSuggestion::query()->create([
            'gateway_suggestion_id' => (int) $response['suggestion_id'],
            'task' => self::TASK,
            'entity_type' => 'product',
            'entity_id' => $product->id,
            'locale' => $locale,
            'old_values' => ['html' => $current],
            'options' => [['html' => $html, 'missing_info' => array_values(array_filter((array) ($response['result']['missing_info'] ?? []), 'is_string'))]],
            'status' => AiSuggestion::PROPOSED,
            'user_id' => $user->id,
        ]);

        return [$suggestion, (array) ($response['usage'] ?? [])];
    }

    public function apply(AiSuggestion $suggestion, User $user): Product
    {
        abort_unless($suggestion->task === self::TASK, 404, __('ai.errors.not_found'));
        [$definition, $product] = $this->resolve('product', $suggestion->entity_id, $user);
        abort_unless($suggestion->isOpen(), 410, __('ai.errors.closed'));

        DB::transaction(function () use ($suggestion, $definition, $product, $user) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($this->body($definition, $product, $suggestion->locale) !== $suggestion->old_values['html']) {
                abort(409, __('ai.errors.conflict_content'));
            }

            $this->writeBody($product, $definition, $suggestion->locale, (string) $suggestion->options[0]['html'], $user);
            $suggestion->update(['status' => AiSuggestion::APPLIED, 'applied_option' => 0, 'user_id' => $user->id, 'applied_at' => now()]);
            ActivityLogger::log('ai_product_described', $product, 'Viết mô tả sản phẩm bằng AI ('.$suggestion->locale.')');
        });

        $this->report($suggestion, 'applied', ['option_index' => 0]);

        return $product;
    }
}
