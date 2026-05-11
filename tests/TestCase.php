<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Masgeek\ArtisanToolkit\ArtisanToolkitServiceProvider;
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
            'schema:dump' => \Masgeek\ArtisanToolkit\Commands\SchemaDumpCommand::class,
        ]);

        config()->set('artisan-toolkit.commands', [
            'make:enum'          => \Masgeek\ArtisanToolkit\Commands\MakeEnumCommand::class,
            'make:api-scaffold'  => \Masgeek\ArtisanToolkit\Commands\MakeApiScaffoldCommand::class,
            'make:resource-full' => \Masgeek\ArtisanToolkit\Commands\MakeFullResourceCommand::class,
            'make:repo'          => \Masgeek\ArtisanToolkit\Commands\MakeRepositoryCommand::class,
            'model:relations'    => \Masgeek\ArtisanToolkit\Commands\ListModelRelationsCommand::class,
        ]);
    }
}
