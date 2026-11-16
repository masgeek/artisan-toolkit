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
use Masgeek\ArtisanToolkit\Support\ModelColumns;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Throwable;

final class ModelFillableAuditCommand extends Command
{
    protected $signature = 'model:fillable-audit
                            {--model=* : Limit to the given model classes (FQCN or short name)}
                            {--fail : Exit non-zero when missing attributes or casts are found}
                            {--json : Emit results as JSON instead of a table}';

    protected $description = 'Compare $fillable and $casts against the model\'s actual table columns';

    public function handle(): int
    {
        $models = $this->resolveModels();

        if ($models === []) {
            $this->warn('No models found to inspect.');

            return self::SUCCESS;
        }

        $issues = [];
        $skipped = [];
        $inspected = 0;

        foreach ($models as $class) {
            $result = $this->inspect($class);

            if (is_string($result)) {
                $skipped[] = $result;

                continue;
            }

            $inspected++;
            array_push($issues, ...$result);
        }

        $summary = [
            'error' => count(array_filter($issues, static fn (array $issue): bool => $issue['severity'] === 'error')),
            'warning' => count(array_filter($issues, static fn (array $issue): bool => $issue['severity'] === 'warning')),
            'info' => count(array_filter($issues, static fn (array $issue): bool => $issue['severity'] === 'info')),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode(
                [
                    'inspected' => $inspected,
                    'skipped' => $skipped,
                    'issues' => $issues,
                    'summary' => $summary,
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return $this->exitCode($summary);
        }

        foreach ($skipped as $message) {
            $this->line(" <fg=gray>{$message}</>");
        }

        $this->render($issues, $inspected, $summary);

        return $this->exitCode($summary);
    }

    private function exitCode(array $summary): int
    {
        if ($this->option('fail') && $summary['error'] > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $class
     * @return list<array{class: string, model: string, table: string, kind: string, attribute: string, detail: string, severity: string}>|string
     */
    private function inspect(string $class): array|string
    {
        try {
            $reflection = new ReflectionClass($class);

            if (! $reflection->isInstantiable()) {
                return "Skipping {$class}: not instantiable.";
            }

            $instance = app($class);

            if (! $instance instanceof Model) {
                return "Skipping {$class}: not an Eloquent model.";
            }

            $table = $instance->getTable();

            if (! $instance->getConnection()->getSchemaBuilder()->hasTable($table)) {
                return "Skipping {$class}: table [{$table}] does not exist.";
            }
        } catch (Throwable $e) {
            return "Skipping {$class}: {$e->getMessage()}";
        }

        $columns = ModelColumns::forModel($class);

        if ($columns === []) {
            return "Skipping {$class}: table [{$table}] has no inspectable columns.";
        }

        $names = array_map(static fn (array $column): string => (string) $column['name'], $columns);
        $lookup = array_fill_keys(array_map('strtolower', $names), true);
        $ignored = $this->ignoredColumns($instance);
        $fillable = $instance->getFillable();
        $casts = array_filter(
            $instance->getCasts(),
            static fn (mixed $cast, string $attribute): bool => strtolower($attribute) !== strtolower($instance->getKeyName()),
            ARRAY_FILTER_USE_BOTH,
        );
        $castLookup = array_fill_keys(array_map('strtolower', array_keys($casts)), true);

        $issues = [];

        foreach ($fillable as $attribute) {
            if (! isset($lookup[strtolower($attribute)])) {
                $issues[] = $this->issue($class, $table, 'missing-fillable', $attribute, 'not a column on this table', 'error');
            }
        }

        foreach (array_keys($casts) as $attribute) {
            if (! isset($lookup[strtolower($attribute)])) {
                $issues[] = $this->issue($class, $table, 'missing-cast', $attribute, 'not a column on this table', 'error');
            }
        }

        foreach ($this->unfillableColumns($instance, $fillable, $ignored, $names, $lookup) as $attribute) {
            $issues[] = $this->issue($class, $table, 'unfillable', $attribute, 'not in $fillable', 'warning');
        }

        if ($fillable === [] && $instance->getGuarded() === ['*']) {
            $issues[] = $this->issue($class, $table, 'guarded', '*', 'mass assignment fully guarded; declare $fillable', 'info');
        }

        foreach ($columns as $column) {
            $attribute = (string) $column['name'];

            if (isset($ignored[strtolower($attribute)]) || isset($castLookup[strtolower($attribute)])) {
                continue;
            }

            $suggestion = $this->suggestedCast($column);
            $detail = $suggestion !== null ? "suggest cast: {$suggestion}" : 'no cast defined';
            $issues[] = $this->issue($class, $table, 'uncast', $attribute, $detail, 'info');
        }


        return $issues;
    }

    /**
     * Columns that are never expected to be mass assignable.
     *
     * @return array<string, true> lowercased names
     */
    private function ignoredColumns(Model $instance): array
    {
        $ignored = [$instance->getKeyName()];

        if ($instance->usesTimestamps()) {
            $ignored[] = $instance->getCreatedAtColumn();
            $ignored[] = $instance->getUpdatedAtColumn();
        }

        if (in_array(SoftDeletes::class, class_uses_recursive($instance), true)) {
            $ignored[] = $instance->getDeletedAtColumn();
        }

        return array_fill_keys(array_map('strtolower', array_filter($ignored)), true);
    }

    /**
     * Table columns that cannot be mass assigned.
     *
     * @param  list<string>  $fillable
     * @param  array<string, true>  $ignored
     * @param  list<string>  $names
     * @param  array<string, true>  $lookup
     * @return list<string>
     */
    private function unfillableColumns(Model $instance, array $fillable, array $ignored, array $names, array $lookup): array
    {
        if ($fillable !== []) {
            $fillableLookup = array_fill_keys(array_map('strtolower', $fillable), true);

            return array_values(array_filter(
                $names,
                static fn (string $name): bool => ! isset($ignored[strtolower($name)])
                    && ! isset($fillableLookup[strtolower($name)]),
            ));
        }

        $guarded = $instance->getGuarded();

        if ($guarded === [] || $guarded === ['*']) {
            return [];
        }

        return array_values(array_filter(
            $guarded,
            static fn (string $name): bool => isset($lookup[strtolower($name)]) && ! isset($ignored[strtolower($name)]),
        ));
    }

    /**
     * @param  array{name: string, type: string, type_name: string, nullable: bool, length: int|null}  $column
     */
    private function suggestedCast(array $column): ?string
    {
        $type = (string) ($column['type_name'] ?: $column['type']);

        return match (true) {
            str_contains($type, 'json'), str_contains($type, 'array') => 'array',
            str_contains($type, 'bool') => 'boolean',
            str_contains($type, 'datetime'), str_contains($type, 'timestamp'), str_contains($type, 'date') => 'datetime',
            str_contains($type, 'decimal'), str_contains($type, 'numeric'),
            str_contains($type, 'float'), str_contains($type, 'double'),
            str_contains($type, 'real') => 'decimal:2',
            default => null,
        };
    }

    /**
     * @return array{class: string, model: string, table: string, kind: string, attribute: string, detail: string, severity: string}
     */
    private function issue(string $class, string $table, string $kind, string $attribute, string $detail, string $severity): array
    {
        return [
            'class' => $class,
            'model' => class_basename($class),
            'table' => $table,
            'kind' => $kind,
            'attribute' => $attribute,
            'detail' => $detail,
            'severity' => $severity,
        ];
    }

    /**
     * @return list<class-string<Model>>
     */
    private function resolveModels(): array
    {
        $filters = (array) $this->option('model');
        $models = [];

        foreach ((array) config('artisan-toolkit.paths.model_scan', ['app/Models']) as $relative) {
            $directory = $this->resolvePath((string) $relative);

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
     * @param  list<array{class: string, model: string, table: string, kind: string, attribute: string, detail: string, severity: string}>  $issues
     * @param  array{error: int, warning: int, info: int}  $summary
     */
    private function render(array $issues, int $inspected, array $summary): void
    {
        $this->newLine();

        if ($issues === []) {
            $this->info(" <fg=green>No fillable or cast drift found</> across {$inspected} model(s).");
            $this->newLine();

            return;
        }

        $this->table(
            ['Model', 'Issue', 'Attribute', 'Detail'],
            array_map(
                static fn (array $issue): array => [
                    $issue['model'],
                    $issue['kind'],
                    $issue['attribute'],
                    $issue['detail'],
                ],
                $issues,
            ),
        );

        $this->newLine();
        $this->line(' <options=bold>'.count($issues)." issue(s)</> <fg=gray>across {$inspected} model(s)</>"
            ." — {$summary['error']} error(s), {$summary['warning']} warning(s), {$summary['info']} suggestion(s)");

        if ($summary['error'] > 0) {
            $this->warn(' Missing attributes and casts reference columns that do not exist; fix them before deploying.');
        }

        $this->newLine();
    }
}
