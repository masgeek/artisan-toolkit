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
        'key:generate' => \Masgeek\ArtisanToolkit\Commands\RotateAppKey::class,

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
    | Key Storage Path
    |--------------------------------------------------------------------------
    |
    | Absolute path to a JSON file where the current APP_KEY and all previous
    | keys are persisted after each rotation. Useful in Docker containers:
    | mount a volume to this path so other containers (e.g. a sidecar or
    | backup sidecar) can read the latest keys without touching .env.
    |
    | Set to null to disable (default). When disabled, keys are only stored
    | in .env and the runtime config.
    |
    | WARNING: This file contains sensitive encryption keys in plain text.
    | Ensure the file has restrictive permissions (e.g. 0600) and the
    | parent directory is not world-readable. Never commit this file to
    | version control.
    |
    | Example:
    |   KEY_STORAGE_PATH=/run/secrets/app-keys.json
    |   KEY_STORAGE_PATH=null
    |
    */

    'key_storage_path' => env('KEY_STORAGE_PATH', storage_path('app/keys/app-keys.json')),

    /*
    |--------------------------------------------------------------------------
    | Queue Names
    |--------------------------------------------------------------------------
    |
    | List of queues that the 'queues' command should manage.
    |
    */

    'queues' => [
        'default'
    ],

    'queue_timeout' => env('QUEUE_TIMEOUT', 60),

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
        'make:dto' => \Masgeek\ArtisanToolkit\Commands\MakeDtoCommand::class,
        'make:service' => \Masgeek\ArtisanToolkit\Commands\MakeServiceCommand::class,
        'make:api-endpoint' => \Masgeek\ArtisanToolkit\Commands\MakeApiEndpointCommand::class,

        'model:relations' => \Masgeek\ArtisanToolkit\Commands\ListModelRelationsCommand::class,
        'model:prune-orphaned' => \Masgeek\ArtisanToolkit\Commands\PruneOrphanedModelsCommand::class,
        'model:analyze' => \Masgeek\ArtisanToolkit\Commands\ModelAnalyzeCommand::class,
        'model:find-unused' => \Masgeek\ArtisanToolkit\Commands\ModelFindUnusedCommand::class,

        'queues:list' => \Masgeek\ArtisanToolkit\Commands\QueuesListCommand::class,
        'queues:listen' => \Masgeek\ArtisanToolkit\Commands\QueuesListenCommand::class,
        'queues:clear' => \Masgeek\ArtisanToolkit\Commands\QueuesClearCommand::class,

        'config:diff' => \Masgeek\ArtisanToolkit\Commands\ConfigDiffCommand::class,
        'db:seed-partial' => \Masgeek\ArtisanToolkit\Commands\DbSeedPartialCommand::class,
        'app:status' => \Masgeek\ArtisanToolkit\Commands\AppStatusCommand::class,
        'route:filter' => \Masgeek\ArtisanToolkit\Commands\RouteFilterCommand::class,
    ],


];
