<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'name',
        'is_system',
        'permissions',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'permissions' => 'array',
    ];

    public function hasPermission(string $ability): bool
    {
        return in_array('*', $this->permissions ?? [], true)
            || in_array($ability, $this->permissions ?? [], true);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
