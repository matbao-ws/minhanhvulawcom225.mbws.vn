<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Seed showcase data for a disposable local environment only.
     * Several seeders below truncate their target tables before inserting data.
     */
    public function run(): void
    {
        $this->call([
            OasisProductSeeder::class,
            OrderSeeder::class,
            VoucherSeeder::class,
            PostSeeder::class,
            ReviewSeeder::class,
            // Additive and idempotent, unlike the seeders above. Ordered last
            // so the menu can link to the pages and categories they created.
            PageContentSeeder::class,
            MenuSeeder::class,
        ]);
    }
}
