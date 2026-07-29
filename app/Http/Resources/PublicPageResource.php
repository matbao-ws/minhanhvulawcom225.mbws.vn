<?php

namespace App\Http\Resources;

use App\Services\LocalizedContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $content = app(LocalizedContent::class);

        return [
            'id' => $this->id,
            'title' => $content->get($this->resource, 'title'),
            'slug' => $this->canonicalSlug(),
            'html' => $content->get($this->resource, 'published_html'),
            'css' => $content->get($this->resource, 'published_css'),
            'seo_title' => $content->get($this->resource, 'meta_title'),
            'seo_description' => $content->get($this->resource, 'meta_description'),
            'published_at' => $this->published_at?->toISOString(),
        ];
    }
}
