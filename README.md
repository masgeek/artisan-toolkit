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

The published `config/artisan-toolkit.php` controls which commands are active and configures package-wide settings:

```php
return [
    'overrides' => [
        'schema:dump'  => \Masgeek\ArtisanToolkit\Commands\SchemaDumpCommand::class,
        'key:generate' => \Masgeek\ArtisanToolkit\Commands\RotateAppKey::class,
    ],

    'model_scan_paths' => [
        'app/Models',
        'app/Models/Base',
    ],

    'encrypted_models' => [
        // \App\Models\ApiCredential::class => [
        //     'api_key',
        //     'api_secret',
        // ],
        // \App\Models\User::class => [
        //     'two_factor_secret',
        // ],
    ],

    'key_storage_path' => null,

    'commands' => [
        'make:enum'            => \Masgeek\ArtisanToolkit\Commands\MakeEnumCommand::class,
        'make:repo'            => \Masgeek\ArtisanToolkit\Commands\MakeRepositoryCommand::class,
        'make:api-scaffold'    => \Masgeek\ArtisanToolkit\Commands\MakeApiScaffoldCommand::class,
        'make:resource-full'   => \Masgeek\ArtisanToolkit\Commands\MakeFullResourceCommand::class,
        'model:relations'      => \Masgeek\ArtisanToolkit\Commands\ListModelRelationsCommand::class,
        'model:prune-orphaned' => \Masgeek\ArtisanToolkit\Commands\PruneOrphanedModelsCommand::class,
    ],
];
```

Setting a command value to `false` (or removing the entry) disables it and leaves Laravel's built-in in place.

### `model_scan_paths`

Directories (relative to your app's base path) that `model:prune-orphaned` scans when no `--path` option is given on the CLI. Adjust this list to match your project's model layout:

```php
'model_scan_paths' => [
    'app/Models',
    'app/Models/Base',
    'app/Domain/Shared/Models', // example custom path
],
```

## Available overrides

### `schema:dump`

The built-in `php artisan schema:dump --prune` deletes **every** file under `database/migrations/`. This override changes `--prune` to only delete migration files whose name exists in the `migrations` table — **pending (unrun) migrations are kept on disk**.

```bash
# Dump schema and delete only already-run migration files
php artisan schema:dump --prune

# Dump without pruning (identical to built-in behaviour)
php artisan schema:dump
```

Output when `--prune` is used:

```
2024_01_01_000000_create_users_table.php .............. deleted
2026_05_07_085600_rename_playground_role.php .......... kept (pending)
Database schema dumped and pruned (1 deleted, 1 pending kept) successfully.
```

### `key:generate`

Overrides the native `key:generate` command to rotate the application key. The old `APP_KEY` is appended to `APP_PREVIOUS_KEYS` in `.env`, a new key is generated, and all configured encrypted model attributes are re-encrypted with the new key.

The command prompts for confirmation before proceeding. Use `--force` to skip the prompt (e.g. in scripts or CI).

```bash
# Generate a new key, rotate old key, and re-encrypt configured models
php artisan key:generate

# Display the new key without applying it
php artisan key:generate --show

# Force in production
php artisan key:generate --force

# Skip .env file writes (Docker / readonly filesystem)
php artisan key:generate --no-env-file

# Reverse the last key rotation
php artisan key:generate --reverse

# Reverse the last 3 key rotations
php artisan key:generate --reverse --steps=3
```

| Option | Description |
|---|---|
| `--show` | Print the generated key without applying changes |
| `--force` | Skip confirmation prompts; allow running in production |
| `--no-env-file` | Skip `.env` writes; prints the new key and previous keys for manual injection |
| `--reverse` | Roll back key rotations using previous keys |
| `--steps=N` | Number of rotations to roll back (default: `1`, used with `--reverse`) |

The command **fails early** if no models are defined in `encrypted_models` or none of the configured models are valid. This prevents accidental key rotation without re-encrypting sensitive data.

When `--no-env-file` is used, the command outputs the `APP_KEY` and `APP_PREVIOUS_KEYS` values so they can be injected as environment variables (e.g. in Docker).

If `key_storage_path` is configured, the command also writes the keys to a JSON file after each rotation (see [`key_storage_path`](#key_storage_path) below).

`--reverse` iterates through `N` previous keys, re-encrypting data back to each one in sequence, then sets the restored key as `APP_KEY`. It fails if fewer previous keys exist than the requested `--steps`.

#### `encrypted_models`

Map your Eloquent models to the array of attributes that use Laravel's `encrypted` cast. The `key:generate` command iterates these models, chunking through records and re-encrypting each listed field with the new `APP_KEY`.

```php
'encrypted_models' => [
    \App\Models\ApiCredential::class => [
        'api_key',
        'api_secret',
    ],
    \App\Models\User::class => [
        'two_factor_secret',
    ],
],
```

Each entry's value must be a non-empty array of attribute names. Models that don't exist or have no fields defined are skipped with a warning; if all models are skipped the command exits with a failure.

### `key_storage_path`

Absolute path to a JSON file where the current `APP_KEY` and all previous keys are persisted after each key rotation. This is useful in Docker environments where other containers (sidecars, backup services) need to read the latest keys without accessing `.env`.

```php
'key_storage_path' => env('KEY_STORAGE_PATH', storage_path('app/keys/app-keys.json')), // default
```

Override via `.env`:

```dotenv
KEY_STORAGE_PATH=/run/secrets/app-keys.json  # Docker secret mount
KEY_STORAGE_PATH=null                        # disable key file
```

When set to a non-null path, each `key:generate` run (forward or reverse) writes a JSON file with this structure:

```json
{
    "current_key": "base64:...",
    "previous_keys": ["base64:...", "base64:..."],
    "updated_at": "2026-07-23T12:00:00+00:00"
}
```

The parent directory is created automatically if it does not exist. Set to `null` (default) to disable.

> **Security warning:** This file contains your encryption keys in plain text. Ensure the file has restrictive permissions (e.g. `chmod 600`), the parent directory is not world-readable, and the file is never committed to version control. When using Docker, mount the path as a `tmpfs` volume or use Docker secrets to avoid persisting keys to disk.

## Available commands

### `make:enum`

Laravel has no built-in enum generator. This command creates a PHP enum in `app/Enums/`.

```bash
# Pure (unbacked) enum
php artisan make:enum Status

# Backed string enum with cases pre-filled
php artisan make:enum UserRole --backed=string --cases=Admin,Partner,User

# Backed int enum in a sub-namespace
php artisan make:enum Billing/InvoiceStatus --backed=int --cases=Draft,Pending,Paid,Void

# Overwrite an existing file
php artisan make:enum UserRole --force
```

Example output for `php artisan make:enum UserRole --backed=string --cases=Admin,Partner,User`:

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Partner = 'partner';
    case User = 'user';
}
```

Sub-namespaces (e.g. `Billing/InvoiceStatus`) are placed under `app/Enums/Billing/` with the correct `namespace App\Enums\Billing;` declaration.

### `make:repo`

Creates a repository class in `app/Repositories/` extending `BaseRepository`. Optionally binds it to an Eloquent model.

```bash
# Create a repository without a model
php artisan make:repo UserRepo

# Create a repository bound to a specific model
php artisan make:repo PostRepo --model=Post
```

Example output for `php artisan make:repo PostRepo --model=Post`:

```php
<?php

namespace App\Repositories;

use App\Models\Post;

/**
 * @extends \App\Repositories\BaseRepo<Post>
 */
class PostRepo extends \App\Repositories\BaseRepository
{
    protected Post $model;

    protected function model(): string
    {
        return Post::class;
    }
}
```

### `make:api-scaffold`

Scaffolds a full API controller, repository, resource, and resource collection for a given name.

```bash
# Create all four files with default pluralised route
php artisan make:api-scaffold Currency

# Use a different model class than the scaffold name
php artisan make:api-scaffold Currency --model=MyCurrency

# Overwrite existing files
php artisan make:api-scaffold Currency --force

# Skip automatic route registration in routes/api.php
php artisan make:api-scaffold Currency --no-route

# Custom URL prefix for the route
php artisan make:api-scaffold Currency --prefix=my-currencies
```

Files generated:
- `app/Http/Controllers/Api/{Name}Controller.php`
- `app/Repositories/{Name}Repo.php`
- `app/Http/Resources/{Name}Resource.php`
- `app/Http/Resources/Collections/{Name}ResourceCollection.php`

When `--no-route` is omitted, the command also registers a `GET /v1/{prefix}` route inside the `throttle:120,1` group in `routes/api.php`. The prefix defaults to the kebab-case plural of the name (e.g. `currencies` for `Currency`).

### `make:resource-full`

Creates an API Resource and its Resource Collection. Can inspect an existing model to pre-fill the `toArray()` fields.

```bash
# Basic resource and collection with placeholder fields
php artisan make:resource-full UserResource

# Specify the model explicitly (inferred from name if it ends in 'Resource')
php artisan make:resource-full UserResource --model=User

# Auto-detect relationships from the model and generate related resources
php artisan make:resource-full UserResource --model=User --with-relationships
```

Files generated:
- `app/Http/Resources/{Name}.php`
- `app/Http/Resources/Collections/{Name}Collection.php`

When `--with-relationships` is used, the command inspects the model's public methods returning Eloquent Relation instances, adds relation lines to `toArray()`, generates missing related resource classes, and adds the necessary `use` imports.

### `model:relations`

Lists all Eloquent relationships defined in a model class by inspecting method return types.

```bash
# List relations for a specific model
php artisan model:relations "App\Models\User"
```

Example output:

```
Relationships in App\Models\User (including base model):
[ "posts", "profile", "roles" ]
```

If the model extends a base model in `App\Models\Base\`, relationships from both the parent and child are listed. Non-existent models produce an error message.

### `model:prune-orphaned`

Scans one or more model directories and reports any model files that have **no backing database table** and **no references** anywhere in the codebase. By default it only lists candidates; pass `--delete` to remove them.

```bash
# List orphaned models using paths from config
php artisan model:prune-orphaned

# Scan specific directories (overrides config)
php artisan model:prune-orphaned --path=app/Models --path=app/Models/Base

# Delete orphaned models (prompts for confirmation)
php artisan model:prune-orphaned --delete

# Delete without confirmation prompt
php artisan model:prune-orphaned --delete --force
```

The directories scanned when no `--path` is given are controlled by `model_scan_paths` in the published config file (see [Configuration](#configuration)).

A model is considered orphaned only when **both** conditions are true:

- `Schema::hasTable()` returns `false` for its table
- No other `.php` file under the search path (default `app/`) references the class by name or fully-qualified class name

Non-Eloquent PHP files (plain classes, interfaces, traits) found in the model directories are silently skipped. Missing directories produce a warning and are skipped; the command only fails when every configured path is missing.

## Adding new commands

1. Create a class in `src/Commands/` using namespace `Masgeek\ArtisanToolkit\Commands`.
   - To **override** a built-in: extend the original command class and change the behaviour.
   - To **add** a new command: extend `Illuminate\Console\Command` as normal.
2. Add it to the appropriate section in `config/artisan-toolkit.php` (`overrides` or `commands`).
3. The service provider registers it automatically when enabled.

## License

MIT
