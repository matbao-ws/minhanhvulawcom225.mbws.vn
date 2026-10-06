<?php

namespace App\Support\Seo;

use Illuminate\Support\HtmlString;

/**
 * schema.org JSON-LD built from data the site already holds. Nothing here is
 * guessed or generated: a field without a value is left out, because markup
 * that disagrees with the visible page is what search engines penalise.
 */
final class StructuredData
{
    /** @param  array{name?: string, logo_url?: string}  $branding */
    public static function organization(array $branding, string $url): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $branding['name'] ?? null,
            'url' => $url,
            'logo' => $branding['logo_url'] ?? null,
        ]);
    }

    /**
     * @param  list<array{name: string, url: string}>  $trail  root first, current page last
     */
    public static function breadcrumbs(array $trail): ?array
    {
        $items = [];

        foreach (array_values($trail) as $index => $crumb) {
            $name = trim((string) ($crumb['name'] ?? ''));

            if ($name === '' || blank($crumb['url'] ?? null)) {
                continue;
            }

            $items[] = [
                '@type' => 'ListItem',
                'position' => count($items) + 1,
                'name' => $name,
                'item' => $crumb['url'],
            ];
        }

        // A one-item trail tells a search engine nothing it does not already know.
        if (count($items) < 2) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /** Every price in this core is in đồng (checkout, VNPAY, SePay). */
    public const CURRENCY = 'VND';

    /**
     * A product with its price(s), availability and visible reviews.
     *
     * @param  array{name: string, url: string, description?: ?string, images?: list<string>, sku?: ?string, brand?: ?string}  $product
     * @param  list<array{price: float, in_stock: bool}>  $offers  one per variant on sale, or the product itself
     * @param  array{average: float, count: int}|null  $rating  visible reviews only
     */
    public static function product(array $product, array $offers, ?array $rating = null): array
    {
        $node = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product['name'],
            'url' => $product['url'],
            'description' => $product['description'] ?? null,
            'image' => array_values(array_filter($product['images'] ?? [])) ?: null,
            'sku' => $product['sku'] ?? null,
            'brand' => filled($product['brand'] ?? null) ? ['@type' => 'Brand', 'name' => $product['brand']] : null,
        ], fn ($value) => $value !== null && $value !== '');

        // A price of zero is "contact us", not a free product: leave the offer out.
        $priced = array_values(array_filter($offers, fn (array $offer) => $offer['price'] > 0));
        if ($priced !== []) {
            $prices = array_column($priced, 'price');
            $availability = 'https://schema.org/'.(in_array(true, array_column($priced, 'in_stock'), true) ? 'InStock' : 'OutOfStock');

            $node['offers'] = count($priced) === 1 || min($prices) === max($prices)
                ? ['@type' => 'Offer', 'url' => $product['url'], 'price' => self::price(min($prices)), 'priceCurrency' => self::CURRENCY, 'availability' => $availability]
                : ['@type' => 'AggregateOffer', 'url' => $product['url'], 'lowPrice' => self::price(min($prices)), 'highPrice' => self::price(max($prices)),
                    'offerCount' => count($priced), 'priceCurrency' => self::CURRENCY, 'availability' => $availability];
        }

        if ($rating !== null && $rating['count'] > 0) {
            $node['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round($rating['average'], 1),
                'reviewCount' => $rating['count'],
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        return $node;
    }

    /**
     * A blog post. The shop is the author: the core keeps no per-post author.
     *
     * @param  array{headline: string, url: string, description?: ?string, image?: ?string, published?: ?string, modified?: ?string}  $post
     * @param  array{name?: string, logo_url?: string}  $branding
     */
    public static function article(array $post, array $branding): array
    {
        $publisher = array_filter([
            '@type' => 'Organization',
            'name' => $branding['name'] ?? null,
            'logo' => filled($branding['logo_url'] ?? null) ? ['@type' => 'ImageObject', 'url' => $branding['logo_url']] : null,
        ]);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => mb_substr($post['headline'], 0, 110),
            'description' => $post['description'] ?? null,
            'image' => filled($post['image'] ?? null) ? [$post['image']] : null,
            'datePublished' => $post['published'] ?? null,
            'dateModified' => $post['modified'] ?? ($post['published'] ?? null),
            'mainEntityOfPage' => $post['url'],
            'author' => $publisher,
            'publisher' => $publisher,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /** Whole đồng as a string: schema.org wants a plain number, never "1.250.000 ₫". */
    private static function price(float $price): string
    {
        return rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');
    }

    /**
     * A <script type="application/ld+json"> element. JSON_HEX_TAG turns "<" and
     * ">" into </>, so a shop name or title containing "</script>"
     * cannot close the element and inject markup.
     */
    public static function script(array $node): HtmlString
    {
        $json = json_encode(
            $node,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR,
        );

        return new HtmlString('<script type="application/ld+json">'.$json.'</script>');
    }
}
