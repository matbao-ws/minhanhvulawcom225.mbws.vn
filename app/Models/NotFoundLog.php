<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A storefront URL that answered 404, with how often and where from (admin/redirects). */
class NotFoundLog extends Model
{
    protected $fillable = [
        'path',
        'hits',
        'referer',
        'last_seen_at',
    ];

    protected $casts = [
        'hits' => 'integer',
        'last_seen_at' => 'datetime',
    ];
}
