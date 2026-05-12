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

- Laravel library (not a full app). Tested via **Orchestra Testbench** with `testing` DB (SQLite in-memory, `APP_KEY` in phpunit.xml.dist).
- `ArtisanToolkitServiceProvider` merges `config/artisan-toolkit.php` and registers commands in two groups:
  - `overrides` — classes that replace a built-in Artisan command (e.g. `schema:dump`)
  - `commands` — brand-new commands (e.g. `make:enum`, `model:relations`)
- Setting a config entry to `false` or omitting it disables the command.
- Namespace: `Masgeek\ArtisanToolkit\` → `src/`, tests → `Masgeek\ArtisanToolkit\Tests\` → `tests/`.
- PHP 8.2+, Laravel 11–13.

## Adding a new command

1. Create class in `src/Commands/` (namespace `Masgeek\ArtisanToolkit\Commands`).
2. Add to `config/artisan-toolkit.php` under `overrides` or `commands`.
3. Register in `tests/TestCase.php` `defineEnvironment()` so tests can resolve it.

## Testing quirks

- Tests must configure both `artisan-toolkit.overrides` and `artisan-toolkit.commands` in `defineEnvironment()`.
- Commands that touch filesystem (make:* commands) create files in temp dirs and clean up in `tearDown()`.
- `SchemaDumpCommandTest` requires a real DB connection (SQLite in-memory via `testing` driver).
- Coverage reports output to `coverage/` (gitignored).

## CI / Release flow

| Event | Action |
|---|---|
| Push to any branch | Unit tests (PHP 8.4, SQLite, coverage ≥70%) |
| PR to `main` or `develop` | Unit tests |
| Push to `develop` | Auto PR created targeting `main` (via `next-release.yml`) |
| Push to `main` (tests pass) | Auto bump-tag + GitHub release (via `bump-and-tag.yml`) |

## git conventions

- Commits follow [Conventional Commits](https://www.conventionalcommits.org/): `feat:`, `fix:`, `docs:`, `refactor:`, `chore:`, etc.
- Breaking changes use `!` suffix (e.g. `feat!:`).
- Branches: `main` (stable) ← `develop` (integration).
