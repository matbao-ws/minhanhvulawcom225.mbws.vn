<?php

namespace App\Http\Resources;

use App\Services\LocalizedContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductVariant */
class PublicProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $content = app(LocalizedContent::class);

        return [
            'id' => $this->id,
            'name' => $content->get($this->resource, 'name'),
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'options' => $this->whenLoaded('optionValues', fn () => $this->optionValues->map(fn ($value) => [
                'value_id' => $value->id,
                'value' => $content->get($value, 'label'),
                'code' => $value->code,
                'group' => [
                    'id' => $value->optionGroup?->id,
                    'name' => $value->optionGroup ? $content->get($value->optionGroup, 'name') : null,
                    'code' => $value->optionGroup?->code,
                ],
            ])),
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'image_url' => $this->image_url,
            'weight_grams' => $this->weight_grams,
            'stock_quantity' => $this->stock_quantity,
            'is_default' => $this->is_default,
        ];
    }
}
