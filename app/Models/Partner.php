<?php

namespace App\Models;

use App\Models\Concerns\HasMediaUrls;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One partner / customer logo of a {@see PartnerGroup}.
 *
 * @property int $id
 * @property int $partner_group_id
 * @property string $name
 * @property string|null $logo_path resolved URL on read; stored root-relative for this site's own media
 * @property string|null $url
 * @property int $sort_order
 * @property bool $is_active
 */
class Partner extends Model
{
    use HasFactory, HasMediaUrls;

    protected $fillable = [
        'partner_group_id',
        'name',
        'logo_path',
        'url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<PartnerGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(PartnerGroup::class, 'partner_group_id');
    }

    /**
     * Stored portable (root-relative for our own uploads), read back as a URL resolved against
     * the current APP_URL — see App\Support\MediaUrl.
     */
    protected function logoPath(): Attribute
    {
        return self::mediaUrlAttribute();
    }
}
