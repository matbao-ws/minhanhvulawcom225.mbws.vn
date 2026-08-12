<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasMediaUrls;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasMediaUrls, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'name',
        'email',
        'preferred_locale',
        'avatar_url',
        'password',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected function avatarUrl(): Attribute
    {
        return self::mediaUrlAttribute();
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function addresses()
    {
        return $this->hasMany(UserAddress::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role
            && $this->role->hasPermission('*')
            && ($this->role->is_system || $this->role->name === 'Superadmin');
    }

    public function hasPrivilegedRole(): bool
    {
        return $this->role !== null
            && ($this->role->is_system || in_array('*', $this->role->permissions ?? [], true));
    }
}
