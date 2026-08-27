<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteBlockRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'site_block_id',
        'created_by',
        'content',
    ];

    protected $casts = [
        'content' => 'array',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(SiteBlock::class, 'site_block_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
