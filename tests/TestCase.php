<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Masgeek\ArtisanToolkit\ArtisanToolkitServiceProvider;
use Masgeek\ArtisanToolkit\Commands\ListModelRelationsCommand;
use Masgeek\ArtisanToolkit\Commands\MakeApiScaffoldCommand;
use Masgeek\ArtisanToolkit\Commands\MakeEnumCommand;
use Masgeek\ArtisanToolkit\Commands\MakeFullResourceCommand;
use Masgeek\ArtisanToolkit\Commands\MakeRepositoryCommand;
use Masgeek\ArtisanToolkit\Commands\PruneOrphanedModelsCommand;
use Masgeek\ArtisanToolkit\Commands\RotateAppKey;
use Masgeek\ArtisanToolkit\Commands\SchemaDumpCommand;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ArtisanToolkitServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        config()->set('artisan-toolkit.overrides', [
            'schema:dump' => SchemaDumpCommand::class,
            'key:generate' => RotateAppKey::class,
        ]);

        config()->set('artisan-toolkit.commands', [
            'make:enum' => MakeEnumCommand::class,
            'make:api-scaffold' => MakeApiScaffoldCommand::class,
            'make:resource-full' => MakeFullResourceCommand::class,
            'make:repo' => MakeRepositoryCommand::class,
            'model:relations' => ListModelRelationsCommand::class,
            'model:prune-orphaned' => PruneOrphanedModelsCommand::class,
        ]);
    }
}
