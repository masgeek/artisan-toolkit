<?php

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Masgeek\ArtisanToolkit\Support\GeneratorPath;

class MakeRepositoryCommand extends Command
{
    protected $signature = 'make:repo {name} {--model=} {--force : Overwrite an existing repository}';

    protected $description = 'Create a new repository class';

    public function handle(Filesystem $files): int
    {
        $name = $this->argument('name');
        $model = $this->option('model');

        $relative = GeneratorPath::relative('repositories', 'app/Repositories');
        $namespace = GeneratorPath::namespaceFor('repositories', 'app/Repositories');
        $repositoryPath = base_path($relative."/{$name}.php");

        if (file_exists($repositoryPath) && ! $this->option('force')) {
            $this->error("Repository '{$name}' already exists! Use --force to overwrite.");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($repositoryPath));

        $baseClass = config('artisan-toolkit.base_classes.repository', 'App\\Repositories\\BaseRepo');
        $baseClass = is_string($baseClass) && $baseClass !== '' ? trim($baseClass, '\\') : 'App\\Repositories\\BaseRepo';
        $baseShort = class_basename($baseClass);

        $modelType = $model ?: 'Model';
        $modelImport = $model ? 'use '.GeneratorPath::modelClass($model).';' : '';

        $stub = <<<PHP
<?php

namespace {$namespace};

use {$baseClass};
{$modelImport}

/**
 * @extends {$baseShort}<{$modelType}>
 */
class {$name} extends {$baseShort}
{
    protected function model(): string
    {
        return {$modelType}::class;
    }
}
PHP;

        $files->put($repositoryPath, $stub);

        $this->info("Repository [{$repositoryPath}] created successfully.");

        return self::SUCCESS;
    }
}
