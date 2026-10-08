# AGENTS.md — masgeek/artisan-toolkit

## Quick start

```bash
composer install
composer test                          # phpunit, no coverage
composer test:coverage                 # phpunit with coverage (threshold: 70%)
vendor/bin/phpunit --filter test_name  # single test
vendor/bin/phpunit tests/SchemaDumpCommandTest.php  # single file
```

## Architecture

- Laravel library tested via **Orchestra Testbench** (SQLite in-memory, `DB_CONNECTION=testing`).
- `ArtisanToolkitServiceProvider` registers commands from `config/artisan-toolkit.php`:
  - `overrides` — replaces built-in Artisan commands.
  - `commands` — new custom commands.
- Setting a config entry to `false` or omitting it disables the command.
- PHP 8.2+, Laravel 11–13.

## Critical Logic & Gotchas

- **`schema:dump --prune`**: Modified to only delete migration files already present in the `migrations` table; pending migrations are kept.
- **`key:generate`**: 
  - Rotates `APP_KEY` and appends old keys to `APP_PREVIOUS_KEYS` in `.env`.
  - Re-encrypts models defined in `config/artisan-toolkit.php` (`encrypted_models`).
  - Can persist keys to a JSON file via `key_storage_path`.
  - `--reverse` rolls back rotations using stored previous keys.

## Adding a new command

1. Create class in `src/Commands/` (namespace `Masgeek\ArtisanToolkit\Commands`).
2. Add to `config/artisan-toolkit.php` under `overrides` or `commands`.
3. Register in `tests/TestCase.php` `defineEnvironment()` to ensure tests can resolve it.

## Testing quirks

- `tests/TestCase::defineEnvironment()` must be updated with any new commands for them to be active during tests.
- `make:*` commands create files in temp dirs and must be cleaned up in `tearDown()`.
- `SchemaDumpCommandTest` requires the `testing` DB connection.
- Coverage reports are in `coverage/`.

## CI / Release flow

| Event | Action |
|---|---|
| Push to any branch | Unit tests (PHP 8.4, SQLite, coverage ≥70%) |
| PR to `main` or `develop` | Unit tests |
| Push to `develop` | Auto PR to `main` (via `next-release.yml`) |
| Push to `main` | Auto bump-tag + GitHub release (via `bump-and-tag.yml`) |

## Git & Commit Conventions

- **Conventional Commits**: `feat:`, `fix:`, `docs:`, `refactor:`, `chore:`, etc.
- **Breaking Changes**: Use `!` after type (e.g., `feat!:`) and a `BREAKING CHANGE:` footer. Must be in an isolated commit.
- **Branches**: `main` (stable) ← `develop` (integration).
