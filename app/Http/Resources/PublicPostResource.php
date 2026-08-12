<?php

namespace App\Http\Resources;

use App\Services\LocalizedContent;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $content = app(LocalizedContent::class);

        return [
            'id' => $this->id,
            'title' => $content->get($this->resource, 'title'),
            'slug' => $this->canonicalSlug(),
            'summary' => $content->get($this->resource, 'summary'),
            'content' => $this->when(
                $this->relationLoaded('category'),
                fn (): string => MediaUrl::absolutizeHtmlSources($content->get($this->resource, 'content')),
            ),
            'image_url' => $this->image_url,
            'seo_title' => $content->get($this->resource, 'seo_title'),
            'seo_description' => $content->get($this->resource, 'seo_description'),
            'canonical_url' => $this->canonical_url,
            'robots' => [
                'index' => (bool) $this->robots_index,
                'follow' => (bool) $this->robots_follow,
            ],
            'published_at' => $this->published_at?->toISOString(),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $content->get($this->category, 'name'),
                'slug' => $this->category->canonicalSlug(),
            ] : null),
        ];
    }
}
