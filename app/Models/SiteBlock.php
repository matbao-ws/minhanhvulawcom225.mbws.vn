<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class SiteBlock extends Model
{
    use HasTranslations;

    public const TYPE_TEXT = 'text';

    public const TYPE_HTML = 'html';

    public const TYPE_IMAGE = 'image';

    public const TYPES = [self::TYPE_TEXT, self::TYPE_HTML, self::TYPE_IMAGE];

    /**
     * Semantic wrapper an editor may choose from the inline toolbar.
     *
     * Absent from this list means the Blade template's own tag stands.
     */
    public const FORMAT_PARAGRAPH = 'p';

    public const FORMATS = [self::FORMAT_PARAGRAPH, 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    public array $translatable = ['content'];

    protected $fillable = [
        'key',
        'type',
        'format',
        'content',
    ];

    public function revisions(): HasMany
    {
        return $this->hasMany(SiteBlockRevision::class)->latest('created_at');
    }

    /**
     * The raw translations, including locales stored as an empty string.
     *
     * `getTranslations()` filters empty strings out, and an empty string is the
     * value that means "the admin hid this region on purpose". Reading the raw
     * column is the only way to tell that apart from "never edited".
     *
     * @return array<string, string>
     */
    public function rawTranslations(): array
    {
        $decoded = json_decode((string) ($this->getAttributes()['content'] ?? ''), true);

        return is_array($decoded) ? $decoded : [];
    }
}
