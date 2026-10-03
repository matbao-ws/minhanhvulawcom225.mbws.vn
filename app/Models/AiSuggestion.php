<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSuggestion extends Model
{
    public const PROPOSED = 'proposed';

    public const APPLIED = 'applied';

    public const REJECTED = 'rejected';

    /** Matches the gateway: a two-week-old suggestion is about a page that has moved on. */
    public const TTL_DAYS = 14;

    protected $fillable = [
        'gateway_suggestion_id', 'task', 'entity_type', 'entity_id', 'locale', 'old_values', 'options',
        'status', 'applied_option', 'user_id', 'applied_at',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'old_values' => 'array',
        'options' => 'array',
        'applied_option' => 'integer',
        'applied_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::PROPOSED && $this->created_at?->gt(now()->subDays(self::TTL_DAYS));
    }
}
