# masgeek/artisan-overrides

Selectively replace Laravel's built-in Artisan commands with safer, smarter alternatives. Enable only the overrides you need via a single published config file.

## Installation

```bash
composer require masgeek/artisan-overrides
```

Publish the config:

```bash
php artisan vendor:publish --tag=artisan-overrides-config
```

## Configuration

The published `config/artisan-overrides.php` controls which commands are active:

```php
return [
    'overrides' => [
        // Enabled — uses the package's safer implementation
        'schema:dump' => \Masgeek\ArtisanOverrides\Commands\SchemaDumpCommand::class,

        // Disabled — leave the built-in untouched
        // 'schema:dump' => false,

        // Custom — provide your own class instead
        // 'schema:dump' => App\Console\Commands\MyDump::class,
    ],
];
```

Setting a value to `false` (or removing the entry) disables that override and leaves Laravel's built-in in place.

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

## Adding new overrides

1. Create a class in `src/Commands/` that extends the built-in command you want to replace.
2. Add it to `config/artisan-overrides.php` under `overrides`.
3. The service provider will register it automatically when enabled.

## License

MIT
