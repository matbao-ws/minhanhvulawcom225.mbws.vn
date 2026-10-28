<?php

namespace App\Http\Resources;

use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Partner
 */
class PublicPartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // The model resolves the stored root-relative path against the current APP_URL (MediaUrl).
            'logo_url' => $this->logo_path,
            'url' => $this->url,
            'group' => $this->group?->key,
        ];
    }
}
