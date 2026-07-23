<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class PublicOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'locale' => $this->locale,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'shipping_address' => $this->shipping_address,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'promotion_discount' => $this->promotion_discount,
            'shipping_fee' => $this->shipping_fee,
            'shipping_status' => $this->shipping_status,
            'grand_total' => $this->grand_total,
            'tracking_number' => $this->tracking_number,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'sku' => $item->sku,
                'promotion_id' => $item->promotion_id,
                'promotion_name' => $item->promotion_name,
                'original_price' => $item->original_price,
                'promotion_discount' => $item->promotion_discount,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'total' => $item->total,
            ])),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
