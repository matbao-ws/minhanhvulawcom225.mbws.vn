<?php

namespace App\Http\Resources;

use App\Services\LocalizedContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $content = app(LocalizedContent::class);

        return [
            'id' => $this->id,
            'name' => $content->get($this->resource, 'name'),
            'slug' => $this->canonicalSlug(),
            'description' => $content->get($this->resource, 'description'),
            'meta_title' => $content->get($this->resource, 'meta_title'),
            'meta_description' => $content->get($this->resource, 'meta_description'),
            'image_url' => $this->image_url,
            'children' => PublicCategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
