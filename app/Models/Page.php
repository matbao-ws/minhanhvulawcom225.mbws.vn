<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedSlugs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Page extends Model
{
    use HasLocalizedSlugs, HasTranslations, SoftDeletes;

    public array $translatable = [
        'title',
        'published_html',
        'meta_title',
        'meta_description',
    ];

    protected $fillable = [
        'title',
        'slug',
        'published_html',
        'meta_title',
        'meta_description',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function revisions()
    {
        return $this->hasMany(PageRevision::class)->latest('created_at');
    }
}
