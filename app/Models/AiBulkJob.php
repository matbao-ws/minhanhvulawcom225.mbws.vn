<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AiBulkJob extends Model
{
    protected $fillable = [
        'gateway_job_id', 'type', 'locale', 'status', 'stop_reason', 'items_total', 'credits_estimated', 'items', 'user_id', 'imported_at',
    ];

    protected $casts = [
        'gateway_job_id' => 'integer',
        'items' => 'array',
        'imported_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** meta | seo_fix | alt, from the gateway job type (bulk.meta …). */
    public function kind(): string
    {
        return (string) Str::after($this->type, 'bulk.');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'stopped'], true);
    }
}
