# masgeek/artisan-toolkit

Opinionated Artisan commands for Laravel applications — scaffolding generators, model tooling, and safe replacements for two built-in commands.

Every command is opt-out. Nothing replaces a built-in unless you enable it in the published config.

- **PHP** 8.2+
- **Laravel** 11, 12, 13

---

## Table of contents

- [Installation](#installation)
- [How configuration works](#how-configuration-works)
- [Command reference](#command-reference)
  - [Overrides](#overrides)
  - [Scaffolding](#scaffolding)
  - [Model tooling](#model-tooling)
  - [Queues](#queues)
  - [Utilities](#utilities)
- [Configuration reference](#configuration-reference)
- [Upgrading](#upgrading)
- [Development](#development)

---

## Installation

```bash
composer require masgeek/artisan-toolkit
```

Publish the config file:

```bash
php artisan vendor:publish --tag=artisan-toolkit-config
```

This creates `config/artisan-toolkit.php`. The package is inert until you publish — without a published file the shipped defaults are used, and all commands run with default settings.

Verify the install:

```bash
php artisan app:status
```

---

## How configuration works

The service provider merges the package defaults with your published config rather than letting the published file replace them wholesale:

```php
// ArtisanToolkitServiceProvider
$defaults  = ['overrides' => [...], 'commands' => [...]];
$overrides = array_merge($defaults['overrides'], config('artisan-toolkit.overrides', []));
$commands  = array_merge($defaults['commands'], config('artisan-toolkit.commands', []));
```

Three consequences worth knowing:

1. **New commands appear automatically.** Install a version that ships `make:dto` and it works immediately — you do not need to re-publish the config or add the entry by hand.
2. **Your `false` wins.** Set any entry to `false` to disable it, and it stays disabled across upgrades.
3. **Your class replacements win.** Pointing `schema:dump` at your own subclass overrides the package default.

Run `php artisan config:diff` at any time to see exactly which settings deviate from the shipped defaults.

---

## Command reference

### Overrides

These replace built-in Laravel commands. Disable any of them by setting the value to `false`.

#### `schema:dump`

Dumps the database schema. The override changes what `--prune` does: Laravel's built-in deletes **every** file in `database/migrations`, which destroys pending migrations. This version only deletes migration files already recorded in the `migrations` table.

```bash
php artisan schema:dump --prune
```

```
2024_01_01_000000_create_users_table.php .............. deleted
2026_05_07_085600_rename_playground_role.php .......... kept (pending)
Database schema dumped and pruned (1 deleted, 1 pending kept) successfully.
```

| Option | Description |
|---|---|
| `--database=` | Connection to dump |
| `--path=` | Where the dump file is written |
| `--prune` | Delete only already-run migration files |
| `--without-migration-data` | Omit migration data from the dump |

The command refuses to run in production.

#### `key:generate`

Rotates `APP_KEY` and re-encrypts every attribute listed in `encrypted_models`.

> ⚠️ **Dry run by default.** This command only writes when you pass `--apply`. Without it you get a preview of what would change and nothing is modified.

```bash
php artisan key:generate                    # dry run — reports what would happen
php artisan key:generate --apply            # actually rotate and re-encrypt
php artisan key:generate --reverse --apply  # roll back the last rotation
```

| Option | Description |
|---|---|
| `--apply` | Execute the rotation. Without this the command is a dry run. |
| `--new` | Fresh setup. Generates a key, skips rotation and re-encryption entirely. |
| `--show` | Print a generated key without touching any files. |
| `--force` | Skip the confirmation prompt and allow running in production. |
| `--no-env-file` | Skip `.env` writes; prints values for manual injection (Docker). |
| `--reverse` | Roll back using stored previous keys. |
| `--steps=N` | How many rotations to roll back. Default `1`. Requires `--reverse`. |

How a rotation proceeds:

1. A new key is generated.
2. The old key is pushed to the front of `APP_PREVIOUS_KEYS` in `.env`.
3. Every attribute in `encrypted_models` is decrypted with the old key and re-encrypted with the new one, in database transactions, with a progress bar.
4. If `key_storage_path` is set, keys are also written to that JSON file.

**The command exits early with a failure if `encrypted_models` is empty** or if none of the configured models are valid. This prevents rotating a key without re-encrypting the data it protects.

`--reverse` walks back through `N` previous keys, re-encrypting data forward into each one in sequence. It fails if fewer previous keys exist than the requested `--steps`.

#### `encrypted_models`

Maps models to the attributes using Laravel's `encrypted` cast:

```php
'encrypted_models' => [
    \App\Models\ApiCredential::class => ['api_key', 'api_secret'],
    \App\Models\User::class           => ['two_factor_secret'],
],
```

Every model listed must exist and declare at least one field. Models that fail are skipped with a warning; if *all* fail, the command aborts rather than rotating unsafely.

#### `key_storage_path`

Writes current and previous keys to a JSON file after each rotation, for Docker sidecars that need keys without reading `.env`.

```php
'key_storage_path' => env('KEY_STORAGE_PATH', storage_path('app/keys/app-keys.json')),
```

```dotenv
KEY_STORAGE_PATH=/run/secrets/app-keys.json   # Docker secret mount
KEY_STORAGE_PATH=null                         # disable
```

```json
{
    "current_key": "base64:...",
    "previous_keys": ["base64:...", "base64:..."],
    "updated_at": "2026-07-23T12:00:00+00:00"
}
```

> 🔐 This file stores encryption keys in plain text. Restrict it to `0600`, never commit it, and prefer a tmpfs mount or Docker secrets over writing to disk.

---

### Scaffolding

All generators write to the directories configured under [`paths`](#configuration-reference).

#### `make:enum`

Generates a PHP enum. If the name is omitted you are prompted for it.

```bash
php artisan make:enum Status
php artisan make:enum UserRole --backed=string --cases=Admin,Partner,User
php artisan make:enum Billing/InvoiceStatus --backed=int --cases=Draft,Pending,Paid,Void
```

| Option | Description |
|---|---|
| `--backed=` | `string` or `int`. Omit for a pure enum. |
| `--cases=` | Comma-separated case names. |
| `--table=` | Generate cases from the distinct values of a DB column, e.g. `users.role`. |
| `--force` | Overwrite an existing file. |

`--table` requires the value to already exist in the database:

```bash
php artisan make:enum UserRole --backed=string --table=users.role
```

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Partner = 'partner';
}
```

Slash-separated names become sub-namespaces: `Billing/InvoiceStatus` → `app/Enums/Billing/InvoiceStatus.php`.

#### `make:api-scaffold`

Generates a complete API slice in one pass: controller, repository, resource, resource collection, and a form request. Prompts for the name if omitted.

```bash
php artisan make:api-scaffold Currency
php artisan make:api-scaffold Currency --model=MyCurrency
php artisan make:api-scaffold Currency --prefix=my-currencies
php artisan make:api-scaffold Currency --no-route
php artisan make:api-scaffold Currency --force
```

| Option | Description |
|---|---|
| `--model=` | Model class name. Defaults to the name argument. |
| `--prefix=` | URL prefix. Defaults to the kebab-case plural of the name. |
| `--force` | Overwrite existing files. |
| `--no-route` | Skip route registration. |

Files created:

| File | Purpose |
|---|---|
| `Http/Controllers/Api/{Name}Controller.php` | Controller using a `HasPaginationParams` trait |
| `Repositories/{Name}Repo.php` | Repository extending `base_classes.repository` |
| `Http/Resources/{Name}Resource.php` | Resource with fields read from the model's table |
| `Http/Resources/Collections/{Name}ResourceCollection.php` | Resource collection |
| `Http/Requests/{Name}Request.php` | Form request with rules inferred from the model's columns |

The controller is generated with a ready `index()` endpoint using `paginateWithSort()`, and the resource's `toArray()` is pre-filled from the real database columns, with date-cast and timestamp columns routed through `formatDate()`.

**Route registration.** Unless `--no-route` is passed, a `GET /{api_version}/{prefix}` route is written into `routes/api.php`:

- If the file contains the `// Mutating` anchor, the route is inserted just above it.
- Otherwise it is appended to the end of the file, which is correct for standard Laravel 11+ files.
- If `routes/api.php` does not exist you are told to run `php artisan install:api`.
- Duplicate prefixes are detected and skipped.

#### `make:repo`

Generates a repository class.

```bash
php artisan make:repo UserRepo
php artisan make:repo PostRepo --model=Post
```

#### `make:resource-full`

Generates a resource and its collection, inferring the model from a `*Resource` name when `--model` is omitted.

```bash
php artisan make:resource-full UserResource
php artisan make:resource-full UserResource --model=User
php artisan make:resource-full UserResource --model=User --with-relationships
```

| Option | Description |
|---|---|
| `--model=` | Model to inspect. Inferred from the name when omitted. |
| `--force` | Overwrite existing resources. |
| `--with-relationships` | Detect relations, add them to `toArray()`, and generate the related resources. |

#### `make:dto`

Generates a data transfer object, optionally populated from a model's columns.

```bash
php artisan make:dto UserData
php artisan make:dto UserData --model=User
```

#### `make:service`

Generates an empty service class.

```bash
php artisan make:service Billing
```

#### `make:api-endpoint`

Appends a single method to an **existing** controller and generates the matching form request.

```bash
php artisan make:api-endpoint Currency store
php artisan make:api-endpoint Currency update --model=MyCurrency
```

| Argument | Description |
|---|---|
| `name` | Base name; the controller must already exist |
| `method` | Method to add, e.g. `store`, `update` |

Fails with a helpful message if the controller has not been scaffolded yet.

---

### Model tooling

#### `model:relations`

Lists a model's relationships as a table, including those inherited from a base model in `App\Models\Base`.

```bash
php artisan model:relations "App\Models\User"
```

```
┌────────────┬────────────┬──────────────┐
│ Method     │ Type       │ Target Model │
├────────────┼────────────┼──────────────┤
│ posts      │ HasMany    │ Post         │
│ profile    │ HasOne     │ Profile      │
└────────────┴────────────┴──────────────┘
```

#### `model:analyze`

Shows a model's table columns alongside their Laravel casts. Accepts a fully qualified class name, a short name, or even a table name.

```bash
php artisan model:analyze "App\Models\User"
php artisan model:analyze User
php artisan model:analyze users        # infers App\Models\User
```

Resolution order: exact class → `model_base_namespace` + studly name → each entry in `paths.model_scan` → singularized/studly name in `model_base_namespace`.

#### `model:prune-orphaned`

Finds models with **no backing table** *and* **no references anywhere in the codebase**. Lists by default; deletes only with `--delete`.

```bash
php artisan model:prune-orphaned
php artisan model:prune-orphaned --path=app/Models --path=app/Domain/Models
php artisan model:prune-orphaned --delete
php artisan model:prune-orphaned --delete --force
```

| Option | Description |
|---|---|
| `--path=*` | Directories to scan. Defaults to `paths.model_scan`. |
| `--search=` | Root directory for reference scanning. Defaults to `prune_search_root`. |
| `--delete` | Delete the files that are found. |
| `--force` | Skip the confirmation prompt. |

References are matched across `.php`, `.blade.php`, and `.json` files, so a model referenced only from a Blade view or a JSON config is correctly treated as used. Use `-v` to print per-model reasoning.

#### `model:find-unused`

The inverse view: reports **columns that exist but are never read** anywhere in the codebase.

```bash
php artisan model:find-unused
php artisan model:find-unused --model=User --model=Post
php artisan model:find-unused --json
```

| Option | Description |
|---|---|
| `--model=*` | Limit to specific models (FQCN or short name). |
| `--search=` | Root directory to scan. Defaults to `prune_search_root`. |
| `--exclude=*` | Extra columns to treat as always-used. |
| `--include-timestamps` | Also report timestamp columns instead of skipping them. |
| `--json` | Emit JSON instead of a table. |

Automatically skips the primary key, `created_at`/`updated_at`, soft-delete columns, and anything declared in the model's `$fillable`, `$casts`, or `$appends` — a column the model wires up is not dead. The model's own file is also excluded from reference scanning, so a `$fillable` declaration can't mask a genuinely unused column.

Searchable file types: `.php`, `.blade.php`, `.json`, `.vue`, `.js`, `.ts`. A column counts as referenced if it appears as `->column`, `['column']`, or `'column' =>` anywhere.

> ⚠️ Attributes accessed dynamically or in raw SQL will produce false positives. Treat the output as a review list, not an auto-delete list.

---

### Queues

These operate on every queue listed in `queues`, so you declare your queue topology once and get a consistent set of commands.

#### `queues:list`

```bash
php artisan queues:list
```

#### `queues:listen`

Listens on all configured queues at once, using `queue_timeout`.

```bash
php artisan queues:listen
```

#### `queues:clear`

```bash
php artisan queues:clear
```

All three fail with a clear message if `queues` is empty.

---

### Utilities

#### `config:diff`

Compares your published config against the shipped defaults. Reads the defaults directly from the package's own `config/artisan-toolkit.php`, so it can never drift from the real defaults.

```bash
php artisan config:diff
php artisan config:diff --fail    # non-zero exit when differences exist (for CI)
```

Output is a table with a status badge per row, values rendered as indented JSON blocks, and class names shortened to their basename. Sensitive-looking keys (`*_secret`, `*_token`, `api_key`, …) are masked, with `key_storage_path` explicitly exempted since it holds a path rather than a secret.

Statuses: `added`, `extended`, `modified`, `reordered`, `trimmed`, `disabled`, `unset`.

#### `app:status`

Quick environment check.

```bash
php artisan app:status
```

```
Application Status
PHP Version: 8.4.24
Laravel Version: 12.x
Environment: local

Toolkit Status
Active Overrides: 2
Active Commands: 18
```

#### `route:filter`

Filters registered routes by URI or name substring.

```bash
php artisan route:filter v1/currencies
php artisan route:filter users
```

#### `db:seed-partial`

Runs a single seeder without invoking the full `DatabaseSeeder`.

```bash
php artisan db:seed-partial "Database\Seeders\UserSeeder"
```

---

## Configuration reference

| Key | Default | Purpose |
|---|---|---|
| `overrides.*` | see config | Built-in commands to replace |
| `commands.*` | see config | Package commands to register |
| `paths.enums` | `app/Enums` | Target for `make:enum` |
| `paths.repositories` | `app/Repositories` | Target for repositories |
| `paths.services` | `app/Services` | Target for `make:service` |
| `paths.dtos` | `app/DTOs` | Target for `make:dto` |
| `paths.api_controllers` | `app/Http/Controllers/Api` | Target for API controllers |
| `paths.resources` | `app/Http/Resources` | Target for resources |
| `paths.resource_collections` | `app/Http/Resources/Collections` | Target for resource collections |
| `paths.requests` | `app/Http/Requests` | Target for form requests |
| `paths.model_scan` | `['app/Models', 'app/Models/Base']` | Directories scanned for models |
| `base_classes.repository` | `App\Repositories\BaseRepo` | Parent class for generated repositories |
| `base_classes.resource` | `Illuminate\Http\Resources\Json\JsonResource` | Parent class for generated resources |
| `model_base_namespace` | `App\Models` | Namespace used to resolve short model names |
| `api_version` | `v1` | Version segment in generated routes |
| `encrypted_models` | `[]` | Models and attributes to re-encrypt on key rotation |
| `key_storage_path` | `storage/app/keys/app-keys.json` | JSON file mirroring current/previous keys |
| `queues` | `['default', 'high', 'low']` | Queues managed by `queues:*` |
| `queue_timeout` | `60` | Seconds passed to `queue:listen` |
| `prune_search_root` | `app` | Root directory for reference scanning |

Each entry is documented inline in `config/artisan-toolkit.php`.

Generated classes derive their `namespace` from the configured path, so moving `paths.repositories` to `app/Domain/Repos` produces files declaring `namespace App\Domain\Repos;`. Likewise `base_classes.*` controls which parent class is emitted (and imported) in the generated file.

---

## Upgrading

Package updates add new commands to the service provider's defaults, so they register without any action from you. If you want them *disabled*, add an explicit `false` entry to your published config.

`php artisan config:diff` shows you exactly what differs from the shipped defaults after an upgrade.

### Breaking changes

**`key:generate` is now dry-run by default.** Previously `php artisan key:generate` rotated immediately. It now previews and requires `--apply`:

```bash
# Before (≤ previous versions)
php artisan key:generate

# Now
php artisan key:generate --apply
```

---

## Development

See [DEVELOPMENT.md](DEVELOPMENT.md) for local setup, running tests, and adding new commands.

## License

MIT