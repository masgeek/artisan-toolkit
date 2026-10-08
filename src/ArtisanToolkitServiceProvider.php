<?php

namespace Masgeek\ArtisanToolkit;

use Illuminate\Support\ServiceProvider;

class ArtisanToolkitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/artisan-toolkit.php', 'artisan-toolkit');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/artisan-toolkit.php' => config_path('artisan-toolkit.php'),
            ], 'artisan-toolkit-config');

            $this->registerCommands();
        }
    }

    private function registerCommands(): void
    {
        $defaults = [
            'overrides' => [
                'schema:dump' => \Masgeek\ArtisanToolkit\Commands\SchemaDumpCommand::class,
                'key:generate' => \Masgeek\ArtisanToolkit\Commands\RotateAppKey::class,
            ],
            'commands' => [
                'make:enum' => \Masgeek\ArtisanToolkit\Commands\MakeEnumCommand::class,
                'make:api-scaffold' => \Masgeek\ArtisanToolkit\Commands\MakeApiScaffoldCommand::class,
                'make:resource-full' => \Masgeek\ArtisanToolkit\Commands\MakeFullResourceCommand::class,
                'make:repo' => \Masgeek\ArtisanToolkit\Commands\MakeRepositoryCommand::class,
                'model:relations' => \Masgeek\ArtisanToolkit\Commands\ListModelRelationsCommand::class,
                'model:prune-orphaned' => \Masgeek\ArtisanToolkit\Commands\PruneOrphanedModelsCommand::class,
                'queues:list' => \Masgeek\ArtisanToolkit\Commands\QueuesListCommand::class,
                'queues:listen' => \Masgeek\ArtisanToolkit\Commands\QueuesListenCommand::class,
                'queues:clear' => \Masgeek\ArtisanToolkit\Commands\QueuesClearCommand::class,
            ],
        ];

        $userOverrides = config('artisan-toolkit.overrides', []);
        $userCommands = config('artisan-toolkit.commands', []);

        // Merge defaults with user config. User config wins.
        // If user sets a command to false, it stays false and is filtered out later.
        $overrides = array_merge($defaults['overrides'], $userOverrides);
        $commands = array_merge($defaults['commands'], $userCommands);

        $active = array_filter(
            array_merge($overrides, $commands),
            fn ($class) => is_string($class) && class_exists($class),
        );

        if (! empty($active)) {
            $this->commands(array_values($active));
        }
    }
}
