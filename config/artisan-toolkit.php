<?php

use Masgeek\ArtisanToolkit\Commands\ListModelRelationsCommand;
use Masgeek\ArtisanToolkit\Commands\MakeApiScaffoldCommand;
use Masgeek\ArtisanToolkit\Commands\MakeEnumCommand;
use Masgeek\ArtisanToolkit\Commands\MakeFullResourceCommand;
use Masgeek\ArtisanToolkit\Commands\MakeRepositoryCommand;
use Masgeek\ArtisanToolkit\Commands\PruneOrphanedModelsCommand;
use Masgeek\ArtisanToolkit\Commands\RotateAppKey;
use Masgeek\ArtisanToolkit\Commands\SchemaDumpCommand;

return [

    /*
    |--------------------------------------------------------------------------
    | Command Overrides
    |--------------------------------------------------------------------------
    |
    | Each entry maps a built-in Artisan command name to a replacement class.
    |
    | Set a command to false to leave the built-in untouched.
    | Swap in any class that extends the original to provide your own logic.
    |
    | Example:
    |   'schema:dump' => false,                              // disabled
    |   'schema:dump' => App\Console\SchemaDump::class,     // custom class
    |
    */

    'overrides' => [

        'schema:dump' => SchemaDumpCommand::class,
        'key:generate' => RotateAppKey::class,

    ],

    /*
    |--------------------------------------------------------------------------
    | Model Scan Paths
    |--------------------------------------------------------------------------
    |
    | Directories (relative to base_path) that model:prune-orphaned will scan
    | when no --path option is supplied on the CLI.  Add or remove entries to
    | match the model layout of your application.
    |
    */

    'model_scan_paths' => [
        'app/Models',
        'app/Models/Base',
    ],

    /*
    |--------------------------------------------------------------------------
    | Encrypted Model Attributes
    |--------------------------------------------------------------------------
    |
    | Map your Eloquent models to the array of fields that use Laravel's
    | encrypted casting. The key:generate command will chunk through
    | these models and re-encrypt the specified attributes using the new APP_KEY.
    |
    */
    'encrypted_models' => [
        // \App\Models\ApiCredential::class => [
        //     'api_key',
        //     'api_secret',
        // ],
        // \App\Models\User::class => [
        //     'two_factor_secret',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Commands
    |--------------------------------------------------------------------------
    |
    | Register additional Artisan commands provided by this package.
    | Set a command to false to disable it.
    |
    */

    'commands' => [

        'make:enum' => MakeEnumCommand::class,
        'make:api-scaffold' => MakeApiScaffoldCommand::class,
        'make:resource-full' => MakeFullResourceCommand::class,
        'make:repo' => MakeRepositoryCommand::class,

        'model:relations' => ListModelRelationsCommand::class,
        'model:prune-orphaned' => PruneOrphanedModelsCommand::class,
    ],

];
