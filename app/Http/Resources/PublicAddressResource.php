<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A customer's saved address, as the storefront sees it. */
class PublicAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'address' => $this->address,
            'is_default' => (bool) $this->is_default,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
