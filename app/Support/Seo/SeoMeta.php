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
        public readonly ?string $socialTitle = null,
        public readonly ?string $socialDescription = null,
    ) {}

    /**
     * An indexable page also lets Google show a long snippet and a large image preview (Search,
     * Discover), as Rank Math does by default; without max-image-preview:large only a thumbnail.
     */
    public function robots(): string
    {
        $robots = ($this->index ? 'index' : 'noindex').', '.($this->follow ? 'follow' : 'nofollow');

        return $this->index ? $robots.', max-snippet:-1, max-image-preview:large, max-video-preview:-1' : $robots;
    }

    /** The title shared on Facebook, Zalo, X: its own when set, else the page title. */
    public function ogTitle(): string
    {
        return filled($this->socialTitle) ? (string) $this->socialTitle : $this->title;
    }

    public function ogDescription(): ?string
    {
        return filled($this->socialDescription) ? $this->socialDescription : $this->description;
    }

    /** A large card when there is a picture to show. */
    public function twitterCard(): string
    {
        return $this->image ? 'summary_large_image' : 'summary';
    }
}
