<?php

namespace MohamedGaldi\ViltFilepond;

use Illuminate\Support\ServiceProvider;

class ViltFilePondServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/vilt-filepond.php',
            'vilt-filepond'
        );
    }
    public function boot(): void
    {
        // Publish config file
        $this->publishes([
            __DIR__ . '/../config/vilt-filepond.php' => config_path('vilt-filepond.php'),
        ], ['vilt-filepond-config', 'vilt-filepond']);
        
        // Publish Vue components and composables together
        $this->publishes([
            __DIR__ . '/resources/js/components' => resource_path('js/Components/ViltFilePond'),
            __DIR__ . '/resources/js/composables' => resource_path('js/Composables/ViltFilePond'),
        ], ['vilt-filepond-vue', 'vilt-filepond']);

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], ['vilt-filepond-migrations', 'vilt-filepond']);

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
