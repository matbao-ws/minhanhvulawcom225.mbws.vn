<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'message',
        'meta',
        'is_read',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_read' => 'boolean',
    ];
}
