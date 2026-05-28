<?php

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

        'schema:dump' => \Masgeek\ArtisanToolkit\Commands\SchemaDumpCommand::class,

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
    | Custom Commands
    |--------------------------------------------------------------------------
    |
    | Register additional Artisan commands provided by this package.
    | Set a command to false to disable it.
    |
    */

    'commands' => [

        'make:enum' => \Masgeek\ArtisanToolkit\Commands\MakeEnumCommand::class,
        'make:api-scaffold' => \Masgeek\ArtisanToolkit\Commands\MakeApiScaffoldCommand::class,
        'make:resource-full' => \Masgeek\ArtisanToolkit\Commands\MakeFullResourceCommand::class,
        'make:repo' => \Masgeek\ArtisanToolkit\Commands\MakeRepositoryCommand::class,

        'model:relations'        => \Masgeek\ArtisanToolkit\Commands\ListModelRelationsCommand::class,
        'model:prune-orphaned'   => \Masgeek\ArtisanToolkit\Commands\PruneOrphanedModelsCommand::class,
    ],

];
