<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The ids of the boxes an editor added to one repeatable region.
 *
 * Only the ordering and membership live here; each box's content is a normal
 * site block keyed `<list key>.item_<id>.<slot>`.
 */
class SiteList extends Model
{
    protected $fillable = [
        'key',
        'items',
    ];

    protected $casts = [
        'items' => 'array',
    ];
}
