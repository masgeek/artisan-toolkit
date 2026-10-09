# masgeek/artisan-toolkit

A collection of custom and override Artisan commands for Laravel. Enable only what you need via a single published config file.

## Installation

```bash
composer require masgeek/artisan-toolkit
```

Publish the config:

```bash
php artisan vendor:publish --tag=artisan-toolkit-config
```

## Configuration

The published `config/artisan-toolkit.php` controls which commands are active and configures package-wide settings. New commands added in package updates are available automatically unless you explicitly disable them by setting their value to `false`.

```php
return [
    // Replace built-in Laravel commands
    'overrides' => [
        'schema:dump'  => \Masgeek\ArtisanToolkit\Commands\SchemaDumpCommand::class,
        'key:generate' => \Masgeek\ArtisanToolkit\Commands\RotateAppKey::class,
    ],

    // Directories to scan for orphaned models
    'model_scan_paths' => [
        'app/Models',
        'app/Models/Base',
    ],

    // Models and fields to re-encrypt during key rotation
    'encrypted_models' => [
        \App\Models\User::class => ['secret_token'],
    ],

    // Path to persist keys for Docker/Sidecar access
    'key_storage_path' => env('KEY_STORAGE_PATH', storage_path('app/keys/app-keys.json')),

    // Queues to manage via 'queues:*' commands
    'queues' => ['default', 'high', 'low'],
    'queue_timeout' => env('QUEUE_TIMEOUT', 60),

    // Custom toolkit commands
    'commands' => [
        'make:enum' => \Masgeek\ArtisanToolkit\Commands\MakeEnumCommand::class,
        // ... other commands
    ],
];
```

## Available Overrides

### `schema:dump`
Modifies `php artisan schema:dump --prune` to only delete migration files already present in the `migrations` table. **Pending migrations are kept on disk**.

### `key:generate`
Rotates the `APP_KEY`, appends the old key to `APP_PREVIOUS_KEYS`, and re-encrypts configured model attributes.
- **Default Behavior**: Now performs a **Dry Run** (preview).
- **Execute**: Pass `--apply` to perform the actual rotation.
- **Reversal**: Use `--reverse` and `--steps=N` to roll back.
- **Fresh Setup**: Use `--new` to skip rotation and re-encryption.

---

## Available Commands

### 🛠️ Scaffolding
- `make:enum`: Creates PHP enums. Supports `--backed=string|int`, `--cases=...`, and `--table=table.column` to generate cases from DB values.
- `make:repo`: Creates a repository extending `BaseRepository`.
- `make:api-scaffold`: Generates a Controller, Repository, Resource, Collection, and `FormRequest` (based on model fillables). Registers a `/v1/{prefix}` route.
- `make:dto`: Creates a Data Transfer Object. Use `--model=Model` to pre-fill properties from DB columns.
- `make:service`: Creates a business logic Service class in `app/Services`.
- `make:api-endpoint`: Adds a specific method and a dedicated `FormRequest` to an existing API Controller.
- `make:resource-full`: Generates a Resource and Collection, optionally detecting relationships.

### 📊 Model Intelligence
- `model:relations`: Lists all Eloquent relationships in a model (including base models) in a formatted table.
- `model:analyze`: Deep-dive analysis of a model's table columns and their Laravel casts.
- `model:prune-orphaned`: Finds models with no DB table and no references in `.php`, `.blade.php`, or `.json` files. Use `--delete` to remove them.

### 🚀 Queue Management
- `queues:list`: Lists all queues defined in the toolkit config.
- `queues:listen`: Listens on all configured queues with a configurable timeout.
- `queues:clear`: Clears all configured queues.

### 🔧 Maintenance & Utilities
- `config:diff`: Displays a beautiful diff between your published config and the package defaults.
- `db:seed-partial`: Runs a specific seeder class (`php artisan db:seed-partial UserSeeder`).
- `app:status`: Dashboard showing environment, Laravel version, and active toolkit commands.
- `route:filter`: Quickly filter and list routes using a keyword or regex.

## License
MIT
