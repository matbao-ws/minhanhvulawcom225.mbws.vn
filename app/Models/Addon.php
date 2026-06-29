<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Addon extends Model
{
    protected $fillable = [
        'code',
        'name',
        'price',
        'description',
        'is_purchased',
    ];

    protected $casts = [
        'price' => 'float',
        'is_purchased' => 'boolean',
    ];

    /**
     * Get invoices for this addon.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'addon_code', 'code');
    }

    /**
     * Check if a specific addon is purchased.
     */
    public static function isPurchased(string $code): bool
    {
        $addon = self::where('code', $code)->first();
        return $addon ? $addon->is_purchased : false;
    }
}
