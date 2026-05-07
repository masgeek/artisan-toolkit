<?php

namespace Masgeek\ArtisanToolkit;

use Illuminate\Support\ServiceProvider;

class ArtisanToolkitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/artisan-toolkit.php', 'artisan-toolkit');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/artisan-toolkit.php' => config_path('artisan-toolkit.php'),
            ], 'artisan-toolkit-config');

            $this->registerCommands();
        }
    }

    private function registerCommands(): void
    {
        $active = array_filter(
            array_merge(
                config('artisan-toolkit.overrides', []),
                config('artisan-toolkit.commands', []),
            ),
            fn ($class) => is_string($class) && class_exists($class),
        );

        if (! empty($active)) {
            $this->commands(array_values($active));
        }
    }
}
