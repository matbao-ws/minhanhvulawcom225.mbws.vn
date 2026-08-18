<?php

namespace Tests\Feature;

use App\Models\LocalizedSlug;
use App\Models\Post;
use App\Models\PostCategory;
use Database\Seeders\Concerns\ClearsLocalizedSlugs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the seeders that call `truncate()`.
 *
 * Truncating skips model events, so localized slugs used to outlive their
 * records; because truncate also resets the auto-increment, those rows then
 * re-attached to whatever new record reused the id, and every fresh record was
 * pushed onto `-2`, `-3`, `-4` variants of its own slug.
 */
class SeederLocalizedSlugCleanupTest extends TestCase
{
    use RefreshDatabase;

    /** The trait is what is under test; it needs no Seeder base class. */
    private function cleaner(): object
    {
        return new class
        {
            use ClearsLocalizedSlugs;

            /** @param array<int, class-string> $models */
            public function clear(array $models): void
            {
                $this->clearLocalizedSlugs($models);
            }
        };
    }

    public function test_it_removes_slugs_of_the_given_types_only(): void
    {
        $post = Post::query()->create([
            'title' => ['vi' => 'Bài viết'],
            'slug' => 'bai-viet',
            'content' => ['vi' => '<p>Nội dung</p>'],
            'is_active' => true,
        ]);
        $category = PostCategory::query()->create([
            'name' => ['vi' => 'Tin tức'],
            'slug' => 'tin-tuc',
            'is_active' => true,
        ]);

        $post->localizedSlugs()->create(['locale' => 'vi', 'slug' => 'bai-viet', 'is_current' => true]);
        $category->localizedSlugs()->create(['locale' => 'vi', 'slug' => 'tin-tuc', 'is_current' => true]);

        $this->cleaner()->clear([Post::class]);

        $this->assertSame(0, LocalizedSlug::query()->where('sluggable_type', $post->getMorphClass())->count());
        $this->assertSame(1, LocalizedSlug::query()->where('sluggable_type', $category->getMorphClass())->count());
    }

    public function test_it_is_safe_to_call_when_nothing_is_stored(): void
    {
        $this->cleaner()->clear([Post::class, PostCategory::class]);

        $this->assertSame(0, LocalizedSlug::query()->count());
    }
}
