<?php

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Masgeek\ArtisanToolkit\Support\GeneratorPath;

class MakeRepositoryCommand extends Command
{
    protected $signature = 'make:repo 
                            {name} 
                            {--model=} 
                            {--force : Overwrite an existing repository} 
                            {--with-interface : Also generate a matching repository interface} 
                            {--dry-run : Show the files that would be created without writing them}';

    protected $description = 'Create a new repository class';

    public function handle(Filesystem $files): int
    {
        $name = $this->argument('name');
        $model = $this->option('model');
        $withInterface = $this->option('with-interface');
        $dryRun = $this->option('dry-run');

        $relative = GeneratorPath::relative('repositories', 'app/Repositories');
        $namespace = GeneratorPath::namespaceFor('repositories', 'app/Repositories');
        $repositoryPath = base_path($relative."/{$name}.php");

        $interfaceNamespace = GeneratorPath::namespaceFor('repository_interfaces', 'app/Repositories/Contracts');
        $interfacePath = GeneratorPath::to('repository_interfaces', 'app/Repositories/Contracts')."/{$name}Interface.php";

        if (file_exists($repositoryPath) && ! $this->option('force')) {
            $this->error("Repository '{$name}' already exists! Use --force to overwrite.");

            return self::FAILURE;
        }

        if ($withInterface && file_exists($interfacePath) && ! $this->option('force')) {
            $this->error("Interface '{$name}Interface' already exists! Use --force to overwrite.");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($repositoryPath));

        $baseClass = config('artisan-toolkit.base_classes.repository', 'App\\Repositories\\BaseRepo');
        $baseClass = is_string($baseClass) && $baseClass !== '' ? trim($baseClass, '\\') : 'App\\Repositories\\BaseRepo';
        $baseShort = class_basename($baseClass);

        $modelType = $model ?: 'Model';
        $modelImport = $model ? 'use '.GeneratorPath::modelClass($model).';' : '';
        $interfaceUse = $withInterface ? "use {$interfaceNamespace}\\{$name}Interface;" : '';
        $implements = $withInterface ? " implements {$name}Interface" : '';
        $visibility = $withInterface ? 'public' : 'protected';

        if ($withInterface) {
            $interfaceStub = <<<PHP
<?php

namespace {$interfaceNamespace};

interface {$name}Interface
{
    public function model(): string;
}
PHP;

            if ($dryRun) {
                $this->line(" <fg=cyan>[dry-run]</> Would create: {$interfacePath}");
            } else {
                $files->ensureDirectoryExists(dirname($interfacePath));
                $files->put($interfacePath, $interfaceStub);
            }
        }

        $stub = <<<PHP
<?php

namespace {$namespace};

use {$baseClass};
{$interfaceUse}
{$modelImport}

/**
 * @extends {$baseShort}<{$modelType}>
 */
class {$name} extends {$baseShort}{$implements}
{
    {$visibility} function model(): string
    {
        return {$modelType}::class;
    }
}
PHP;

        if ($dryRun) {
            $this->line(" <fg=cyan>[dry-run]</> Would create: {$repositoryPath}");

            return self::SUCCESS;
        }

        $files->put($repositoryPath, $stub);

        $this->info("Repository [{$repositoryPath}] created successfully.");

        if ($withInterface) {
            $this->info("Interface [{$interfacePath}] created successfully.");
        }

        return self::SUCCESS;
    }
}
