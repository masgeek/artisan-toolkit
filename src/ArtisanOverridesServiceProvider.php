<?php

namespace Masgeek\ArtisanOverrides;

use Illuminate\Support\ServiceProvider;

class ArtisanOverridesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/artisan-overrides.php', 'artisan-overrides');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/artisan-overrides.php' => config_path('artisan-overrides.php'),
            ], 'artisan-overrides-config');

            $this->registerEnabledOverrides();
        }
    }

    private function registerEnabledOverrides(): void
    {
        $overrides = config('artisan-overrides.overrides', []);

        $active = array_filter(
            $overrides,
            fn ($class) => is_string($class) && class_exists($class),
        );

        if (! empty($active)) {
            $this->commands(array_values($active));
        }
    }
}
