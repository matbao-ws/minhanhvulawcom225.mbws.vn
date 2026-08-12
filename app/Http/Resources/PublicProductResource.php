<?php

namespace App\Http\Resources;

use App\Services\LocalizedContent;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class PublicProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $content = app(LocalizedContent::class);

        return [
            'id' => $this->id,
            'name' => $content->get($this->resource, 'name'),
            'slug' => $this->canonicalSlug(),
            'sku' => $this->sku,
            'short_description' => $content->get($this->resource, 'short_description'),
            'description' => $this->when(
                $this->relationLoaded('category'),
                fn (): string => MediaUrl::absolutizeHtmlSources($content->get($this->resource, 'description')),
            ),
            'meta_title' => $content->get($this->resource, 'meta_title'),
            'meta_description' => $content->get($this->resource, 'meta_description'),
            'image_url' => $this->image_url,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'stock_quantity' => $this->stock_quantity,
            'manage_stock' => $this->manage_stock,
            'is_featured' => $this->is_featured,
            'option_groups' => $this->whenLoaded('optionGroups', fn () => $this->optionGroups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $content->get($group, 'name'),
                'code' => $group->code,
                'display_type' => $group->display_type,
                'values' => $group->values->map(fn ($value) => [
                    'id' => $value->id,
                    'label' => $content->get($value, 'label'),
                    'code' => $value->code,
                    'color_hex' => $value->color_hex,
                    'image_url' => $value->image_url,
                ]),
            ])),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category ? $content->get($this->category, 'name') : null,
                'slug' => $this->category?->canonicalSlug(),
            ]),
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand?->id,
                'name' => $this->brand ? $content->get($this->brand, 'name') : null,
                'slug' => $this->brand?->canonicalSlug(),
            ]),
            'variants' => PublicProductVariantResource::collection($this->whenLoaded('variants')),
            'reviews' => PublicReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
