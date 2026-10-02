<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'stopped'], true);
    }
}
