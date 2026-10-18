<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An old URL and where it now lives. Served by App\Services\Seo\RedirectService when nothing
 * else answers the old URL; managed at admin/redirects.
 */
class Redirect extends Model
{
    public const STATUS_CODES = [301, 302, 307, 410];

    public const ORIGIN_MANUAL = 'manual';

    public const ORIGIN_WORDPRESS = 'wordpress';

    public const ORIGIN_NOT_FOUND = 'not_found';

    protected $fillable = [
        'source_path',
        'target_url',
        'redirectable_type',
        'redirectable_id',
        'status_code',
        'origin',
        'is_active',
        'hits',
        'last_hit_at',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'is_active' => 'boolean',
        'hits' => 'integer',
        'last_hit_at' => 'datetime',
    ];

    public function redirectable(): MorphTo
    {
        return $this->morphTo();
    }
}
