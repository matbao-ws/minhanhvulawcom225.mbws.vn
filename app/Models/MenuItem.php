<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class MenuItem extends Model
{
    use HasTranslations;

    public const TYPE_PAGE = 'page';

    public const TYPE_CATEGORY = 'category';

    public const TYPE_POST_CATEGORY = 'post_category';

    public const TYPE_URL = 'url';

    /** @return array<int, string> */
    public static function types(): array
    {
        return [self::TYPE_PAGE, self::TYPE_CATEGORY, self::TYPE_POST_CATEGORY, self::TYPE_URL];
    }

    public array $translatable = ['label'];

    protected $fillable = [
        'menu_id',
        'parent_id',
        'label',
        'type',
        'page_id',
        'category_id',
        'post_category_id',
        'url',
        'target_blank',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'target_blank' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function postCategory(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class);
    }

    /**
     * The relation that carries this item's target, or null for custom URLs.
     */
    public function targetRelation(): ?string
    {
        return match ($this->type) {
            self::TYPE_PAGE => 'page',
            self::TYPE_CATEGORY => 'category',
            self::TYPE_POST_CATEGORY => 'postCategory',
            default => null,
        };
    }

    /**
     * True when the item points at a record that has since been deleted, which
     * the foreign keys turn into a null target rather than a dangling id.
     */
    public function hasMissingTarget(): bool
    {
        $relation = $this->targetRelation();

        return $relation !== null && $this->{$relation} === null;
    }
}
