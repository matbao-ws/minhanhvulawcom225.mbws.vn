<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranslationRequest extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'source_locale',
        'target_locale',
        'character_count',
        'source_hash',
        'status',
        'error_code',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
