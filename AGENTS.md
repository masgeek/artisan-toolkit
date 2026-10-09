# AGENTS.md — masgeek/artisan-toolkit

## Commands

```bash
composer test                 # phpunit, no coverage
composer test:coverage        # needs Xdebug/PCOV locally; CI has it
vendor/bin/phpunit --filter X # single test
vendor/bin/pint               # lint/fix
```

Coverage threshold in CI is 70%.

## Architecture

- Laravel **library**, not an app. No `artisan` binary here — tests run via **Orchestra Testbench** (`DB_CONNECTION=testing`, SQLite in-memory, `APP_KEY` set in `phpunit.xml.dist`).
- `ArtisanToolkitServiceProvider` does **not** register commands straight from config. It merges its `$defaults` arrays with the user's published config:
  ```php
  array_merge($defaults['commands'], config('artisan-toolkit.commands', []))
  ```
  So new commands auto-register for existing users, user `false` wins, and user class replacements win.

## Gotchas that will bite you

- **A new command must be registered in TWO places**: `config/artisan-toolkit.php` (`commands`) **and** `src/ArtisanToolkitServiceProvider.php` (`$defaults['commands']`). Missing the provider entry means users with a published config never get the command.
- **`tests/TestCase.php` `defineEnvironment()`** must also list it, or tests can't resolve it.
- **`ConfigDiffCommand` requires the shipped config** (`require __DIR__.'/../../config/artisan-toolkit.php'`) rather than duplicating defaults — that is intentional, do not "clean it up" into a literal array.
- **`key:generate` is dry-run by default** and needs `--apply` to write. Breaking change from earlier versions.
- **Heredoc interpolation trap**: `\$this->$repoVar->method()` parses as property access *on the string variable*. Use `{$repoVar}`. This silently emitted `$this->(` into generated code once already.
- **`ModelFindUnusedCommand` excludes the model's own file** from reference scanning, so a `$fillable` declaration can't mask a dead column. Preserve that.
- **`MakeRepositoryCommand`, `MakeFullResourceCommand`, `MakeApiScaffoldCommand` resolve paths via `GeneratorPath`** (`src/Support/GeneratorPath.php`) — never call `app_path()` directly in a generator. The helper also derives the namespace from the configured path.
- **Stubs must `use` their base class and `extends` the short name.** A bare `extends BaseRepo` with no import generates an undefined-class file. `GeneratedCodeSyntaxTest` guards this.
- Pint fails on a few pre-existing files (`ArtisanToolkitServiceProvider.php`, `MakeApiScaffoldCommand.php`, `MakeApiScaffoldCommandTest.php`). Verify with `git stash` before assuming you introduced it.

## Conventions

- **No hardcoded paths or namespaces.** Read targets from `config('artisan-toolkit.paths.*')` and parents from `config('artisan-toolkit.base_classes.*')`.
- **Comment every config entry** in `config/artisan-toolkit.php` explaining what consumes it.
- Tests write to temp dirs and clean up in `tearDown()`.

## Git

- Conventional Commits. Breaking changes: `feat!:` **and** a `BREAKING CHANGE:` footer, in an isolated commit.
- Branches: `main` (stable) ← `develop` (integration).
- `develop` push auto-PRs to `main`; `main` push auto-tags and releases.

## Docs

- `README.md` — end-user docs: every command, option, and config key.
- `DEVELOPMENT.md` — local setup, path-repo symlink workflow, adding commands, conventions.

Update both when behaviour changes.