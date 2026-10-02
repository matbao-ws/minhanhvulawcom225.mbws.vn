<?php

namespace App\Support\Seo;

/**
 * Everything the storefront <head> says about one URL to search engines and
 * link previews. Built by App\Services\Seo\SeoMetaFactory and rendered by the
 * `core.partials.seo-head` partial (through @include('core.head')); a theme layout that includes core.head
 * gets the same tags without knowing how they are computed.
 */
final class SeoMeta
{
    /**
     * @param  array<string, string>  $alternates  hreflang code => absolute URL (empty on a one-language site)
     * @param  list<array<string, mixed>>  $structuredData  schema.org nodes, encoded by StructuredData::script()
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $canonical,
        public readonly bool $index = true,
        public readonly bool $follow = true,
        public readonly string $type = 'website',
        public readonly ?string $image = null,
        public readonly array $alternates = [],
        public readonly ?string $xDefault = null,
        public readonly array $structuredData = [],
    ) {}

    public function robots(): string
    {
        return ($this->index ? 'index' : 'noindex').', '.($this->follow ? 'follow' : 'nofollow');
    }
}
