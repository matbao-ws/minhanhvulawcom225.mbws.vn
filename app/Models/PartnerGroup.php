<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A strip of partner logos a theme asks for by `key` ("customers", "strategic"). The key is set
 * once on create and never changes: the theme references it.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property int $sort_order
 */
class PartnerGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /** @return HasMany<Partner, $this> */
    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }
}
