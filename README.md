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
        // Enabled — uses the package's safer implementation
        'schema:dump' => \Masgeek\ArtisanToolkit\Commands\SchemaDumpCommand::class,

        // Disabled — leave the built-in untouched
        // 'schema:dump' => false,

        // Custom — provide your own class instead
        // 'schema:dump' => App\Console\Commands\MyDump::class,
    ],

    'commands' => [
        // Register additional custom commands here
        // 'my:command' => \Masgeek\ArtisanToolkit\Commands\MyCommand::class,
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

## Adding new commands

1. Create a class in `src/Commands/` using namespace `Masgeek\ArtisanToolkit\Commands`.
   - To **override** a built-in: extend the original command class and change the behaviour.
   - To **add** a new command: extend `Illuminate\Console\Command` as normal.
2. Add it to the appropriate section in `config/artisan-toolkit.php` (`overrides` or `commands`).
3. The service provider registers it automatically when enabled.

## License

MIT
