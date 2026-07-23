<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Services\LocalizedSlugService;
use Illuminate\Console\Command;

class BackfillLocalizedSlugs extends Command
{
    protected $signature = 'multilingual:backfill-slugs';

    protected $description = 'Backfill localized canonical slugs from existing catalog and CMS data';

    public function handle(LocalizedSlugService $slugs): int
    {
        foreach ([Product::class => 'name', Category::class => 'name', Brand::class => 'name', Post::class => 'title', PostCategory::class => 'name'] as $modelClass => $nameField) {
            $modelClass::query()->orderBy('id')->chunkById(100, function ($models) use ($slugs, $nameField): void {
                foreach ($models as $model) {
                    $names = $model->getTranslations($nameField);
                    $slugs->sync($model, ['vi' => $model->slug], $names);
                }
            });
        }

        $this->info('Localized slugs have been backfilled.');

        return self::SUCCESS;
    }
}
