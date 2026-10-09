<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Command Overrides
    |--------------------------------------------------------------------------
    |
    | Each entry maps a built-in Artisan command name to a replacement class.
    | Set a command to false to leave the built-in untouched.
    |
    */

    'overrides' => [
        'schema:dump' => \Masgeek\ArtisanToolkit\Commands\SchemaDumpCommand::class,
        'key:generate' => \Masgeek\ArtisanToolkit\Commands\RotateAppKey::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Path Configurations
    |--------------------------------------------------------------------------
    |
    | Define where the toolkit should generate files and look for models.
    | Paths are relative to the base path of your application.
    |
    */

    'paths' => [
        'enums'               => 'app/Enums',
        'repositories'       => 'app/Repositories',
        'services'            => 'app/Services',
        'dtos'                => 'app/DTOs',
        'api_controllers'     => 'app/Http/Controllers/Api',
        'resources'           => 'app/Http/Resources',
        'resource_collections' => 'app/Http/Resources/Collections',
        'requests'            => 'app/Http/Requests',
        'model_scan'          => [
            'app/Models',
            'app/Models/Base',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Base Classes
    |--------------------------------------------------------------------------
    |
    | Define the base classes that generated components should extend.
    |
    */

    'base_classes' => [
        'repository' => \App\Repositories\BaseRepository::class,
        'resource'   => \App\Http\Resources\BaseJsonResource::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Defaults
    |--------------------------------------------------------------------------
    |
    | Configuration for model resolution and analysis.
    |
    */

    'model_base_namespace' => 'App\\Models',

    /*
    |--------------------------------------------------------------------------
    | API Versioning
    |--------------------------------------------------------------------------
    |
    | Default version prefix for generated API routes.
    |
    */

    'api_version' => 'v1',

    /*
    |--------------------------------------------------------------------------
    | Key Rotation & Encryption
    |--------------------------------------------------------------------------
    |
    | Configure how the key:generate command handles encrypted data.
    |
    */

    'encrypted_models' => [
        // \App\Models\User::class => ['secret_token'],
    ],

    'key_storage_path' => env('KEY_STORAGE_PATH', storage_path('app/keys/app-keys.json')),

    /*
    |--------------------------------------------------------------------------
    | Queue Management
    |--------------------------------------------------------------------------
    |
    | Configuration for the queues:* commands.
    |
    */

    'queues' => [
        'default',
        'high',
        'low',
    ],

    'queue_timeout' => env('QUEUE_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Pruning Settings
    |--------------------------------------------------------------------------
    |
    | Root directory to search for references when pruning orphaned models.
    |
    */

    'prune_search_root' => 'app',

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
