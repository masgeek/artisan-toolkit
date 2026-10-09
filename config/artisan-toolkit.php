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
        // Replaces schema:dump so --prune only deletes migrations already run,
        // keeping pending migration files on disk. Set to false for the original.
        'schema:dump' => \Masgeek\ArtisanToolkit\Commands\SchemaDumpCommand::class,

        // Replaces key:generate with a rotation that re-encrypts configured
        // models. Requires --apply to write; otherwise runs as a dry run.
        'key:generate' => \Masgeek\ArtisanToolkit\Commands\RotateAppKey::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Path Configurations
    |--------------------------------------------------------------------------
    |
    | Where the toolkit generates files and looks for models.
    | Paths are relative to your application's base path.
    |
    */

    'paths' => [
        // Target directory for make:enum.
        'enums' => 'app/Enums',

        // Target directory for make:repo and make:api-scaffold repositories.
        'repositories' => 'app/Repositories',

        // Target directory for make:service.
        'services' => 'app/Services',

        // Target directory for make:dto.
        'dtos' => 'app/DTOs',

        // Target directory for make:api-scaffold and make:api-endpoint controllers.
        'api_controllers' => 'app/Http/Controllers/Api',

        // Target directory for make:resource-full and make:api-scaffold resources.
        'resources' => 'app/Http/Resources',

        // Target directory for generated resource collections.
        'resource_collections' => 'app/Http/Resources/Collections',

        // Target directory for generated form request classes.
        'requests' => 'app/Http/Requests',

        // Directories model:prune-orphaned and model:analyze scan for models.
        'model_scan' => [
            'app/Models',
            'app/Models/Base',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Base Classes
    |--------------------------------------------------------------------------
    |
    | Parent classes that generated components extend. Point these at your own
    | abstract layers if you use a different architecture.
    |
    */

    'base_classes' => [
        // Abstract repository that make:repo and make:api-scaffold repos extend.
        'repository' => \App\Repositories\BaseRepo::class,

        // Base JSON resource that make:resource-full resources extend.
        'resource' => \Illuminate\Http\Resources\Json\JsonResource::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Defaults
    |--------------------------------------------------------------------------
    |
    | Controls how a model class is resolved from a short name or table name.
    |
    */

    // Namespace used when resolving short model names, e.g. "User" => App\Models\User.
    'model_base_namespace' => 'App\\Models',

    /*
    |--------------------------------------------------------------------------
    | API Versioning
    |--------------------------------------------------------------------------
    |
    | Version segment prefixed to routes registered by make:api-scaffold.
    |
    */

    // Generates routes such as GET /v1/currencies.
    'api_version' => 'v1',

    /*
    |--------------------------------------------------------------------------
    | Key Rotation & Encryption
    |--------------------------------------------------------------------------
    |
    | Configuration for the key:generate override. Rotation re-encrypts every
    | attribute listed in encrypted_models, so entries must be accurate.
    |
    */

    // Model class => attributes using Laravel's `encrypted` cast.
    // An empty array makes key:generate exit early rather than rotate unsafely.
    'encrypted_models' => [
        // \App\Models\User::class => ['secret_token'],
    ],

    // JSON file that stores the current and previous keys for sidecar access.
    // WARNING: contains keys in plain text — restrict permissions, never commit.
    // Set to null to disable and keep keys in .env only.
    'key_storage_path' => env('KEY_STORAGE_PATH', storage_path('app/keys/app-keys.json')),

    /*
    |--------------------------------------------------------------------------
    | Queue Management
    |--------------------------------------------------------------------------
    |
    | Configuration for the queues:* commands, which act on every queue listed.
    |
    */

    // Queues managed by queues:list, queues:listen and queues:clear.
    'queues' => [
        'default',
        'high',
        'low',
    ],

    // Seconds a worker waits for a job before timing out, passed to queue:listen.
    'queue_timeout' => env('QUEUE_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Pruning Settings
    |--------------------------------------------------------------------------
    |
    | Controls how model:prune-orphaned decides whether a model is unused.
    |
    */

    // Root directory searched for references before a model is called orphaned.
    'prune_search_root' => 'app',

    /*
    |--------------------------------------------------------------------------
    | Custom Commands
    |--------------------------------------------------------------------------
    |
    | Commands provided by this package. Set any entry to false to disable it.
    | New commands released by package updates are registered automatically
    | unless they are explicitly disabled here.
    |
    */

    'commands' => [
        // Scaffolding --------------------------------------------------------

        // Creates PHP enums in paths.enums; supports --table to read cases from a DB column.
        'make:enum' => \Masgeek\ArtisanToolkit\Commands\MakeEnumCommand::class,

        // Creates a controller, repository, resource, collection and form request in one pass.
        'make:api-scaffold' => \Masgeek\ArtisanToolkit\Commands\MakeApiScaffoldCommand::class,

        // Creates a resource and collection, optionally expanding model relationships.
        'make:resource-full' => \Masgeek\ArtisanToolkit\Commands\MakeFullResourceCommand::class,

        // Creates a repository extending base_classes.repository.
        'make:repo' => \Masgeek\ArtisanToolkit\Commands\MakeRepositoryCommand::class,

        // Creates a data transfer object, optionally populated from a model.
        'make:dto' => \Masgeek\ArtisanToolkit\Commands\MakeDtoCommand::class,

        // Creates an empty service class in paths.services.
        'make:service' => \Masgeek\ArtisanToolkit\Commands\MakeServiceCommand::class,

        // Appends a single method and form request to an existing controller.
        'make:api-endpoint' => \Masgeek\ArtisanToolkit\Commands\MakeApiEndpointCommand::class,

        // Model tools --------------------------------------------------------

        // Lists a model's relationships, including those on its base model.
        'model:relations' => \Masgeek\ArtisanToolkit\Commands\ListModelRelationsCommand::class,

        // Finds and optionally deletes models with no table and no references.
        'model:prune-orphaned' => \Masgeek\ArtisanToolkit\Commands\PruneOrphanedModelsCommand::class,

        // Shows a model's table columns alongside their casts.
        'model:analyze' => \Masgeek\ArtisanToolkit\Commands\ModelAnalyzeCommand::class,

        // Reports model attributes that are never referenced in the codebase.
        'model:find-unused' => \Masgeek\ArtisanToolkit\Commands\ModelFindUnusedCommand::class,

        // Queues -------------------------------------------------------------

        // Lists the queues defined in the queues array above.
        'queues:list' => \Masgeek\ArtisanToolkit\Commands\QueuesListCommand::class,

        // Listens on every configured queue using queue_timeout.
        'queues:listen' => \Masgeek\ArtisanToolkit\Commands\QueuesListenCommand::class,

        // Clears every configured queue.
        'queues:clear' => \Masgeek\ArtisanToolkit\Commands\QueuesClearCommand::class,

        // Utilities ----------------------------------------------------------

        // Compares this config against the package defaults.
        'config:diff' => \Masgeek\ArtisanToolkit\Commands\ConfigDiffCommand::class,

        // Runs a single seeder class without the full DatabaseSeeder.
        'db:seed-partial' => \Masgeek\ArtisanToolkit\Commands\DbSeedPartialCommand::class,

        // Shows environment, framework versions and active toolkit commands.
        'app:status' => \Masgeek\ArtisanToolkit\Commands\AppStatusCommand::class,

        // Lists routes matching a keyword or pattern.
        'route:filter' => \Masgeek\ArtisanToolkit\Commands\RouteFilterCommand::class,
    ],
];