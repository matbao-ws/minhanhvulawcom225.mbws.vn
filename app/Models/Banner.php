<?php

namespace App\Models;

use App\Models\Concerns\HasMediaUrls;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory, HasMediaUrls;

    protected $fillable = [
        'title',
        'image_path',
        'link_url',
        'position',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Stored portable (root-relative for our own uploads), read back as a URL
     * resolved against the current APP_URL.
     */
    protected function imagePath(): Attribute
    {
        return self::mediaUrlAttribute();
    }

    /**
     * Get the URL of the banner image, falling back to the bundled placeholder.
     */
    public function getImageUrlAttribute(): string
    {
        return $this->image_path ?: asset('admin-assets/images/backgrounds/login-side.jpg');
    }
}
