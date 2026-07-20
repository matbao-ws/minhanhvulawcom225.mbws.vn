<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Promotion extends Model
{
    use HasTranslations;

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'name', 'description', 'kind', 'applies_to', 'discount_type', 'value',
        'min_quantity', 'quantity_limit', 'used_count', 'priority', 'is_stackable',
        'start_at', 'end_at', 'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_quantity' => 'integer',
        'quantity_limit' => 'integer',
        'used_count' => 'integer',
        'priority' => 'integer',
        'is_stackable' => 'boolean',
        'is_active' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function targets()
    {
        return $this->hasMany(PromotionTarget::class);
    }

    public function scopeActiveNow(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('start_at')->orWhere('start_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('end_at')->orWhere('end_at', '>=', now()))
            ->where(fn (Builder $query) => $query->whereNull('quantity_limit')->orWhereColumn('used_count', '<', 'quantity_limit'));
    }

    public function isAvailableFor(int $quantity): bool
    {
        return $this->is_active
            && (! $this->start_at || ! $this->start_at->isFuture())
            && (! $this->end_at || ! $this->end_at->isPast())
            && ($this->quantity_limit === null || $this->used_count + $quantity <= $this->quantity_limit);
    }
}
