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

The published `config/artisan-toolkit.php` controls which commands are active:

```php
return [
    'overrides' => [
        'schema:dump' => \Masgeek\ArtisanToolkit\Commands\SchemaDumpCommand::class,
    ],

    'commands' => [
        'make:enum'          => \Masgeek\ArtisanToolkit\Commands\MakeEnumCommand::class,
        'make:repo'          => \Masgeek\ArtisanToolkit\Commands\MakeRepositoryCommand::class,
        'make:api-scaffold'  => \Masgeek\ArtisanToolkit\Commands\MakeApiScaffoldCommand::class,
        'make:resource-full' => \Masgeek\ArtisanToolkit\Commands\MakeFullResourceCommand::class,
        'model:relations'    => \Masgeek\ArtisanToolkit\Commands\ListModelRelationsCommand::class,
    ],
];
```

Setting a value to `false` (or removing the entry) disables that command and leaves Laravel's built-in in place.

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

## Adding new commands

1. Create a class in `src/Commands/` using namespace `Masgeek\ArtisanToolkit\Commands`.
   - To **override** a built-in: extend the original command class and change the behaviour.
   - To **add** a new command: extend `Illuminate\Console\Command` as normal.
2. Add it to the appropriate section in `config/artisan-toolkit.php` (`overrides` or `commands`).
3. The service provider registers it automatically when enabled.

## License

MIT
