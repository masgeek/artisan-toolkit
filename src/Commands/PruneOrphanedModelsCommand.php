<?php

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;

class PruneOrphanedModelsCommand extends Command
{
    protected $signature = 'model:prune-orphaned
                            {--path=* : One or more directories to scan (defaults to artisan-toolkit.model_scan_paths config)}
                            {--search=app : Directory to search for references (relative to base path, or absolute)}
                            {--delete : Delete the orphaned model files}
                            {--force : Skip confirmation prompt when deleting}';

    protected $description = 'Find and optionally delete Eloquent model files with no backing table and no codebase references';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $scanPaths = $this->option('path') ?: config(
            'artisan-toolkit.model_scan_paths',
            ['app/Models', 'app/Models/Base']
        );

        $searchPath = $this->resolvePath($this->option('search'));

        $validPaths = $this->resolveValidPaths($scanPaths);

        if (empty($validPaths)) {
            $this->error('No valid model directories found.');

            return self::FAILURE;
        }

        $orphaned = [];

        foreach ($validPaths as $modelsPath) {
            $this->info("Scanning {$modelsPath}...");

            foreach ($this->modelFiles($modelsPath) as $file) {
                $class = $this->resolveClass($file);

                if ($class === null) {
                    continue;
                }

                if ($this->hasBackingTable($class) || $this->hasReferences($class, $file->getRealPath(), $searchPath)) {
                    continue;
                }

                $orphaned[] = ['class' => $class, 'path' => $file->getRealPath()];
            }
        }

        if (empty($orphaned)) {
            $this->info('No orphaned models found.');

            return self::SUCCESS;
        }

        $this->table(['Class', 'File'], array_map(
            fn ($m) => [$m['class'], $m['path']],
            $orphaned
        ));

        if (! $this->option('delete')) {
            $this->warn(count($orphaned).' orphaned model(s) found. Pass --delete to remove them.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Delete '.count($orphaned).' orphaned model file(s)?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        foreach ($orphaned as $model) {
            $this->files->delete($model['path']);
            $this->line("Deleted: {$model['path']}");
        }

        $this->info(count($orphaned).' orphaned model file(s) deleted.');

        return self::SUCCESS;
    }

    /** @param  string[]  $paths */
    private function resolveValidPaths(array $paths): array
    {
        $valid = [];

        foreach ($paths as $path) {
            $resolved = $this->resolvePath($path);

            if ($this->files->isDirectory($resolved)) {
                $valid[] = $resolved;
            } else {
                $this->warn("Skipping missing directory: {$resolved}");
            }
        }

        return $valid;
    }

    private function resolvePath(string $path): string
    {
        // Already absolute on Unix (/) or Windows (C:\, D:/, etc.)
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\/\\\\]/', $path)) {
            return $path;
        }

        return base_path($path);
    }

    private function modelFiles(string $path): Finder
    {
        return (new Finder())->in($path)->name('*.php')->files();
    }

    private function resolveClass(\SplFileInfo $file): ?string
    {
        $content = file_get_contents($file->getRealPath());

        if (! preg_match('/^namespace\s+([^;]+);/m', $content, $nsMatch)) {
            return null;
        }

        if (! preg_match('/^class\s+(\w+)/m', $content, $classMatch)) {
            return null;
        }

        $fqcn = trim($nsMatch[1]).'\\'.trim($classMatch[1]);

        if (! class_exists($fqcn)) {
            require_once $file->getRealPath();
        }

        if (! class_exists($fqcn)) {
            return null;
        }

        if (! is_subclass_of($fqcn, \Illuminate\Database\Eloquent\Model::class)) {
            return null;
        }

        return $fqcn;
    }

    private function hasBackingTable(string $class): bool
    {
        try {
            return Schema::hasTable((new $class())->getTable());
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasReferences(string $class, string $modelPath, string $searchPath): bool
    {
        if (! $this->files->isDirectory($searchPath)) {
            return false;
        }

        $shortName = class_basename($class);

        foreach ((new Finder())->in($searchPath)->name('*.php')->files() as $file) {
            if ($file->getRealPath() === $modelPath) {
                continue;
            }

            $content = file_get_contents($file->getRealPath());

            if (preg_match('/\b'.preg_quote($shortName, '/').'\\b/', $content) ||
                str_contains($content, $class)) {
                return true;
            }
        }

        return false;
    }
}
