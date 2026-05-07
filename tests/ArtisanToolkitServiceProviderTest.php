<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Support\Facades\Artisan;
use Masgeek\ArtisanToolkit\Commands\MakeEnumCommand;
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

    public function test_config_is_merged(): void
    {
        $overrides = config('artisan-toolkit.overrides');
        $commands = config('artisan-toolkit.commands');

        $this->assertIsArray($overrides);
        $this->assertIsArray($commands);
        $this->assertArrayHasKey('schema:dump', $overrides);
        $this->assertArrayHasKey('make:enum', $commands);
    }
}
