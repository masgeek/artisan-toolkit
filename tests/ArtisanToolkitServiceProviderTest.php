<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Support\Facades\Artisan;
use Masgeek\ArtisanToolkit\Commands\ListModelRelationsCommand;
use Masgeek\ArtisanToolkit\Commands\MakeApiScaffoldCommand;
use Masgeek\ArtisanToolkit\Commands\MakeEnumCommand;
use Masgeek\ArtisanToolkit\Commands\MakeFullResourceCommand;
use Masgeek\ArtisanToolkit\Commands\MakeRepositoryCommand;
use Masgeek\ArtisanToolkit\Commands\SchemaDumpCommand;

class ArtisanToolkitServiceProviderTest extends TestCase
{
    public function test_make_enum_command_is_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('make:enum', $commands);
        $this->assertInstanceOf(MakeEnumCommand::class, $commands['make:enum']);
    }

    public function test_schema_dump_command_is_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('schema:dump', $commands);
        $this->assertInstanceOf(SchemaDumpCommand::class, $commands['schema:dump']);
    }

    public function test_make_api_scaffold_command_is_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('make:api-scaffold', $commands);
        $this->assertInstanceOf(MakeApiScaffoldCommand::class, $commands['make:api-scaffold']);
    }

    public function test_make_full_resource_command_is_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('make:resource-full', $commands);
        $this->assertInstanceOf(MakeFullResourceCommand::class, $commands['make:resource-full']);
    }

    public function test_make_repo_command_is_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('make:repo', $commands);
        $this->assertInstanceOf(MakeRepositoryCommand::class, $commands['make:repo']);
    }

    public function test_model_relations_command_is_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('model:relations', $commands);
        $this->assertInstanceOf(ListModelRelationsCommand::class, $commands['model:relations']);
    }

    public function test_config_is_merged(): void
    {
        $overrides = config('artisan-toolkit.overrides');
        $commands = config('artisan-toolkit.commands');

        $this->assertIsArray($overrides);
        $this->assertIsArray($commands);
        $this->assertArrayHasKey('schema:dump', $overrides);
        $this->assertArrayHasKey('make:enum', $commands);
        $this->assertArrayHasKey('make:api-scaffold', $commands);
        $this->assertArrayHasKey('make:resource-full', $commands);
        $this->assertArrayHasKey('make:repo', $commands);
        $this->assertArrayHasKey('model:relations', $commands);
    }
}
