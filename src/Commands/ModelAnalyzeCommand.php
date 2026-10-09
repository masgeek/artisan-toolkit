<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final class ModelAnalyzeCommand extends Command
{
    protected $signature = 'model:analyze {model : Model class, short name, or table name}';

    protected $description = 'Deep dive analysis of an Eloquent model';

    public function handle(): int
    {
        $input = $this->argument('model');
        $modelClass = $this->resolveModelClass($input);

        if (! $modelClass || ! class_exists($modelClass)) {
            $this->error("Could not resolve model class for input: {$input}");
            return self::FAILURE;
        }

        $instance = app($modelClass);
        $table = $instance->getTable();

        $this->info("Analysis for <comment>{$modelClass}</comment>");
        $this->line("Table: {$table}");
        $this->newLine();

        $columns = Schema::getColumnListing($table);
        $casts = $instance->getCasts();

        $rows = [];
        foreach ($columns as $col) {
            $cast = $casts[$col] ?? 'none';
            $rows[] = [$col, $cast];
        }

        $this->table(['Column', 'Cast'], $rows);

        return self::SUCCESS;
    }

    private function resolveModelClass(string $input): ?string
    {
        if (class_exists($input)) {
            return $input;
        }

        $baseNamespace = config('artisan-toolkit.model_base_namespace', 'App\\Models');
        $studlyInput = Str::studly($input);

        // 1. Try base namespace first
        $fqcn = $baseNamespace . '\\' . $studlyInput;
        if (class_exists($fqcn)) {
            return $fqcn;
        }

        // 2. Try configured scan paths
        $paths = config('artisan-toolkit.paths.model_scan', ['app/Models', 'app/Models/Base']);
        foreach ($paths as $path) {
            $namespace = str_replace(['app/', '/'], ['', '\\'], $path);
            $namespace = ucfirst($namespace);
            
            $fqcn = $namespace . '\\' . $studlyInput;
            if (class_exists($fqcn)) {
                return $fqcn;
            }
        }

        // 3. Try inferring from table name (Singularize -> Base Namespace)
        $modelName = Str::studly(Str::singular($input));
        $fqcn = $baseNamespace . '\\' . $modelName;
        if (class_exists($fqcn)) {
            return $fqcn;
        }

        return null;
    }
}
