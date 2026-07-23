<?php

namespace App\Http\Resources;

use App\Services\LocalizedContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicBrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $content = app(LocalizedContent::class);

        return [
            'id' => $this->id,
            'name' => $content->get($this->resource, 'name'),
            'slug' => $this->canonicalSlug(),
            'description' => $content->get($this->resource, 'description'),
            'image_url' => $this->image_url,
        ];
    }
}
