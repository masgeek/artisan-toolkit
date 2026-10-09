# Development

Local setup, testing, and conventions for contributing to `masgeek/artisan-toolkit`.

---

## Requirements

- PHP 8.2+
- Composer 2

The package is a Laravel **library**, not an application — there is no `artisan` binary in this repo. Commands are exercised through PHPUnit against a real Laravel application provided by Orchestra Testbench.

---

## Setup

```bash
composer install
```

Run the test suite:

```bash
composer test                          # phpunit, no coverage
composer test:coverage                 # phpunit with coverage (prints a summary table)
vendor/bin/phpunit --filter test_name  # single test by name
vendor/bin/phpunit tests/MakeEnumCommandTest.php   # single file
```

Lint before committing:

```bash
vendor/bin/pint                        # fix
vendor/bin/pint --test                 # check only
```

> Coverage needs Xdebug or PCOV. The script sets `XDEBUG_MODE=coverage` for you, so Xdebug does not need to be enabled globally. If you still see "No code coverage driver available", the extension is not installed or enabled for your PHP binary.

---

## Trying commands in a real application

Because this repo has no application, the fastest way to exercise a command by hand is to symlink the package into a separate Laravel app using Composer's path repository.

Add this to the **application's** `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "D:\\Dev\\php\\artisan-overrides",
            "options": {
                "symlink": true
            }
        }
    ]
}
```

Then:

```bash
composer require masgeek/artisan-toolkit:@dev
php artisan vendor:publish --tag=artisan-toolkit-config
php artisan app:status
```

Composer symlinks the directory, so edits in this repo are reflected immediately in the app — no reinstall needed. If changes are not picked up, dump the autoloader:

```bash
composer dump-autoload
```

Edit `config/artisan-toolkit.php` in the app to enable, disable, or reconfigure commands, then run them:

```bash
php artisan make:enum UserRole --backed=string
php artisan config:diff
```

---

## Project layout

```
src/
  ArtisanToolkitServiceProvider.php   Merges defaults with user config, registers commands
  Commands/                           One class per command
config/
  artisan-toolkit.php                 Shipped defaults; also the source of truth for config:diff
tests/
  TestCase.php                        Orchestra Testbench base; registers commands
  *CommandTest.php                    One test class per command
```

---

## How commands are registered

The provider does **not** register commands straight from the config file. It merges the package defaults with the user's published config first:

```php
$overrides = array_merge($defaults['overrides'], config('artisan-toolkit.overrides', []));
$commands  = array_merge($defaults['commands'],  config('artisan-toolkit.commands', []));
```

`array_merge` means the user's value wins for a given key, so `false` disables a command permanently and a custom class replaces the default.

**If you add a command, add it to `src/ArtisanToolkitServiceProvider.php` `$defaults` as well as `config/artisan-toolkit.php`.** Skipping the provider means users with an existing published config will never get the command, because their file already defines the `commands` key.

---

## Adding a new command

1. Create `src/Commands/YourCommand.php`:

   ```php
   <?php

   declare(strict_types=1);

   namespace Masgeek\ArtisanToolkit\Commands;

   use Illuminate\Console\Command;

   final class YourCommand extends Command
   {
       protected $signature = 'your:command {--option=}';

       protected $description = 'One-line summary shown in artisan list';

       public function handle(): int
       {
           // ...

           return self::SUCCESS;
       }
   }
   ```

2. Register it in **both** places:
   - `config/artisan-toolkit.php` under `commands`, with a comment describing it.
   - `src/ArtisanToolkitServiceProvider.php` under `$defaults['commands']`.

3. Register it in `tests/TestCase.php` `defineEnvironment()` so tests can resolve it.

4. Add `tests/YourCommandTest.php` extending `TestCase`.

---

## Conventions

- **No hardcoded paths.** Read every target directory from `config('artisan-toolkit.paths.*')` and every parent class from `config('artisan-toolkit.base_classes.*')`. If you need a new setting, add it to `config/artisan-toolkit.php` with a comment.
- **Config as the single source of truth.** `ConfigDiffCommand` `require`s the shipped config file rather than duplicating the defaults, so add new keys there and the diff picks them up automatically.
- **Lint with Pint.** Run `vendor/bin/pint` before committing.
- **Document every config entry** with an inline comment explaining what consumes it.
- **Fail gracefully.** Prefer a clear error message over a stack trace when required config is missing or empty.
- **Tests write to temp directories** and clean up in `tearDown()`. Never write into the package directory during a test.

### Commit messages

Conventional Commits. Breaking changes use `!` after the type **and** a `BREAKING CHANGE:` footer, in an isolated commit:

```
feat(config)!: replace model_scan_paths with paths map

BREAKING CHANGE: config key 'model_scan_paths' is renamed to 'paths.model_scan'.
```

Branches: `main` (stable) ← `develop` (integration).

---

## CI

| Event | Action |
|---|---|
| Push to any branch | Unit tests (PHP 8.4, SQLite, coverage ≥ 70%) |
| PR to `main` or `develop` | Unit tests |
| Push to `develop` | Auto PR to `main` |
| Push to `main` | Auto bump-tag + GitHub release |

Workflows live in `.github/workflows/`: `unit-test.yml`, `pr-automation.yml`, `next-release.yml`, `bump-and-tag.yml`.