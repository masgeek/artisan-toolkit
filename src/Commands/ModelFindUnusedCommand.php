<?php

/**
 * Copyright (c) 2026 Munywele Consulting LTD. All rights reserved.
 * https://munywele.co.ke
 */

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Throwable;

final class ModelFindUnusedCommand extends Command
{
    protected $signature = 'model:find-unused
                            {--model=* : Limit to the given model classes (FQCN or short name)}
                            {--search= : Root directory to scan for references (defaults to prune_search_root)}
                            {--exclude=* : Extra column names to treat as always used}
                            {--include-timestamps : Report timestamp/soft-delete columns instead of skipping them}
                            {--json : Emit results as JSON instead of a table}';

    protected $description = 'Find model attributes that are never referenced anywhere in the codebase';

    public function handle(Filesystem $files): int
    {
        $searchPath = $this->resolvePath(
            $this->option('search') ?: config('artisan-toolkit.prune_search_root', 'app')
        );

        if (! $files->isDirectory($searchPath)) {
            $this->error("Search path does not exist: {$searchPath}");

            return self::FAILURE;
        }

        $models = $this->resolveModels();

        if ($models === []) {
            $this->warn('No models found to inspect.');

            return self::SUCCESS;
        }

        $excluded = array_map('strtolower', (array) $this->option('exclude'));
        $searchFiles = $this->collectSearchableFiles($searchPath);

        $unused = [];
        $skipped = [];
        $inspected = 0;

        foreach ($models as $model) {
            $result = $this->inspect($model, $searchFiles, $excluded);

            if (is_string($result)) {
                $skipped[] = $result;

                continue;
            }

            $inspected++;
            array_push($unused, ...$result);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(
                ['inspected' => $inspected, 'skipped' => $skipped, 'unused' => $unused],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return self::SUCCESS;
        }

        foreach ($skipped as $message) {
            $this->line(" <fg=gray>{$message}</>");
        }

        $this->render($unused, $inspected);

        return self::SUCCESS;
    }

    /**
     * @return list<class-string<Model>>
     */
    private function resolveModels(): array
    {
        $filters = (array) $this->option('model');
        $models = [];

        foreach ((array) config('artisan-toolkit.paths.model_scan', ['app/Models']) as $relative) {
            $directory = $this->resolvePath($relative);

            if (! is_dir($directory)) {
                continue;
            }

            foreach ((new Finder)->in($directory)->name('*.php')->files() as $file) {
                $class = $this->resolveClass($file->getRealPath());

                if ($class === null) {
                    continue;
                }

                if ($filters !== [] && ! $this->matchesFilter($class, $filters)) {
                    continue;
                }

                $models[$class] = $class;
            }
        }

        return array_values($models);
    }

    /**
     * @param  list<string>  $filters
     */
    private function matchesFilter(string $class, array $filters): bool
    {
        foreach ($filters as $filter) {
            if ($class === ltrim($filter, '\\') || class_basename($class) === ltrim($filter, '\\')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read every searchable file once so models do not re-scan the tree.
     *
     * @return array<string, string> real path => file contents
     */
    private function collectSearchableFiles(string $searchPath): array
    {
        $finder = (new Finder)
            ->in($searchPath)
            ->files()
            ->name(['*.php', '*.json', '*.vue', '*.js', '*.ts'])
            ->exclude(['vendor', 'node_modules', 'storage'])
            ->ignoreUnreadableDirs();

        $files = [];

        foreach ($finder as $file) {
            $path = $file->getRealPath();
            $files[$path] = (string) file_get_contents($path);
        }

        return $files;
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array<string, string>  $searchFiles
     * @param  list<string>  $excluded
     * @return list<array{class: string, table: string, column: string}>|string  Skip reason when not inspectable
     */
    private function inspect(string $class, array $searchFiles, array $excluded): array|string
    {
        try {
            $reflection = new ReflectionClass($class);

            if (! $reflection->isInstantiable()) {
                return "Skipping {$class}: not instantiable.";
            }

            /** @var Model $instance */
            $instance = new $class;
            $table = $instance->getTable();
            $schema = $instance->getConnection()->getSchemaBuilder();

            if (! $schema->hasTable($table)) {
                return "Skipping {$class}: table [{$table}] does not exist.";
            }

            $columns = $schema->getColumnListing($table);
        } catch (Throwable $e) {
            return "Skipping {$class}: {$e->getMessage()}";
        }

        // The model's own file is a declaration, not usage.
        $ownFile = $reflection->getFileName() !== false ? realpath($reflection->getFileName()) : false;
        $alwaysUsed = $this->alwaysUsedColumns($instance, $excluded);
        $unused = [];

        foreach ($columns as $column) {
            if (in_array(strtolower($column), $alwaysUsed, true)) {
                continue;
            }

            if ($this->isReferenced($column, $searchFiles, $ownFile ?: null)) {
                continue;
            }

            $unused[] = ['class' => $class, 'table' => $table, 'column' => $column];
        }

        return $unused;
    }

    /**
     * Primary key, user excludes and (unless --include-timestamps) timestamp
     * and soft-delete columns.
     *
     * @param  list<string>  $excluded
     * @return list<string>  Lowercased column names
     */
    private function alwaysUsedColumns(Model $instance, array $excluded): array
    {
        $used = [...$excluded, $instance->getKeyName()];

        if (! $this->option('include-timestamps')) {
            $used[] = 'created_at';
            $used[] = 'updated_at';

            if ($instance->usesTimestamps()) {
                $used[] = $instance->getCreatedAtColumn();
                $used[] = $instance->getUpdatedAtColumn();
            }

            if (in_array(SoftDeletes::class, class_uses_recursive($instance), true)) {
                $used[] = $instance->getDeletedAtColumn();
            }
        }

        return array_values(array_unique(array_map('strtolower', array_filter($used))));
    }

    /**
     * Look for the column being used anywhere outside the model's own file.
     *
     * @param  array<string, string>  $searchFiles
     */
    private function isReferenced(string $column, array $searchFiles, ?string $ownFile): bool
    {
        $q = preg_quote($column, '/');

        // ->column | 'column' / "column" | column =>
        $pattern = '/->\s*'.$q.'\b|[\'"]'.$q.'[\'"]|\b'.$q.'\b\s*=>/';

        foreach ($searchFiles as $path => $contents) {
            if ($path !== $ownFile && preg_match($pattern, $contents) === 1) {
                return true;
            }
        }

        return false;
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    private function resolveClass(string $file): ?string
    {
        $contents = (string) file_get_contents($file);

        if (preg_match('/^namespace\s+([^;]+);/m', $contents, $ns) !== 1) {
            return null;
        }

        if (preg_match('/^(?:(?:final|abstract|readonly)\s+)*class\s+(\w+)/m', $contents, $class) !== 1) {
            return null;
        }

        $fqcn = trim($ns[1]).'\\'.trim($class[1]);

        if (! class_exists($fqcn)) {
            require_once $file;
        }

        return class_exists($fqcn) && is_subclass_of($fqcn, Model::class) ? $fqcn : null;
    }

    /**
     * @param  list<array{class: string, table: string, column: string}>  $unused
     */
    private function render(array $unused, int $inspected): void
    {
        $this->newLine();

        if ($unused === []) {
            $this->info(" <fg=green>No unused attributes found</> across {$inspected} model(s).");
            $this->newLine();

            return;
        }

        $this->table(
            ['Model', 'Table', 'Attribute'],
            array_map(
                static fn (array $item): array => [class_basename($item['class']), $item['table'], $item['column']],
                $unused,
            ),
        );

        $this->newLine();
        $this->line(' <options=bold>'.count($unused)." unused attribute(s)</> <fg=gray>across {$inspected} model(s)</>");
        $this->warn(' Verify before removing: attributes may be read via dynamic access, accessors or raw queries.');
        $this->newLine();
    }
}