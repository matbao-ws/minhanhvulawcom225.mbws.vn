<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class PublicProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'short_description' => $this->short_description,
            'description' => $this->when($this->relationLoaded('category'), $this->description),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'image_url' => $this->image_url,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'stock_quantity' => $this->stock_quantity,
            'manage_stock' => $this->manage_stock,
            'is_featured' => $this->is_featured,
            'option_groups' => $this->whenLoaded('optionGroups', fn () => $this->optionGroups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'code' => $group->code,
                'display_type' => $group->display_type,
                'values' => $group->values->map(fn ($value) => [
                    'id' => $value->id,
                    'label' => $value->label,
                    'code' => $value->code,
                    'color_hex' => $value->color_hex,
                    'image_url' => $value->image_url,
                ]),
            ])),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'slug' => $this->category?->slug,
            ]),
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand?->id,
                'name' => $this->brand?->name,
                'slug' => $this->brand?->slug,
            ]),
            'variants' => PublicProductVariantResource::collection($this->whenLoaded('variants')),
            'reviews' => PublicReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
