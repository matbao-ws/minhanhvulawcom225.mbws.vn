<?php

namespace Extensions\Example;

use App\Extension\Registries\AdminMenu;
use Illuminate\Support\ServiceProvider;

/**
 * The reference extension (docs/EXTENSIONS.md). Its provider only registers logic — here, a menu
 * entry. The core loads views, translations, migrations and routes itself.
 */
class ExampleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        AdminMenu::register('example', [
            'key' => 'example.notes',
            'label' => __('example::notes.menu'),
            'icon' => 'solar:notes-line-duotone',
            'route' => 'ext.example.notes.index',
            'permission' => 'example.view',
        ]);
    }
}
