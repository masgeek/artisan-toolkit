<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

        // 2. Search the configured scan directories for a matching class file.
        //    The directory may live anywhere (absolute path, outside app/), so the
        //    namespace is read from the file rather than guessed from the path.
        $found = $this->findInScanPaths($studlyInput);

        if ($found !== null) {
            return $found;
        }

        // 3. Try inferring from table name (Singularize -> Base Namespace)
        $modelName = Str::studly(Str::singular($input));
        $fqcn = $baseNamespace . '\\' . $modelName;
        if (class_exists($fqcn)) {
            return $fqcn;
        }

        return null;
    }

    /**
     * Locate a model class by filename inside paths.model_scan.
     */
    private function findInScanPaths(string $studlyInput): ?string
    {
        $paths = config('artisan-toolkit.paths.model_scan', ['app/Models', 'app/Models/Base']);

        foreach ((array) $paths as $path) {
            $directory = $this->resolveDirectory((string) $path);

            if ($directory === null) {
                continue;
            }

            $file = $directory.DIRECTORY_SEPARATOR.$studlyInput.'.php';

            if (! is_file($file)) {
                continue;
            }

            $contents = (string) file_get_contents($file);

            if (preg_match('/^namespace\s+([^;]+);/m', $contents, $ns) !== 1) {
                continue;
            }

            $fqcn = trim($ns[1]).'\\'.$studlyInput;

            if (! class_exists($fqcn)) {
                require_once $file;
            }

            if (class_exists($fqcn)) {
                return $fqcn;
            }
        }

        return null;
    }

    private function resolveDirectory(string $path): ?string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return is_dir($path) ? $path : null;
        }

        $absolute = base_path($path);

        return is_dir($absolute) ? $absolute : null;
    }
}
