<?php

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeRepositoryCommand extends Command
{
    protected $signature = 'make:repo {name} {--model=}';

    protected $description = 'Create a new repository class';

    public function handle(): int
    {
        $name = $this->argument('name');
        $model = $this->option('model');
        $repositoryPath = app_path("Repositories/{$name}.php");

        if (file_exists($repositoryPath)) {
            $this->error("Repository '{$name}' already exists!");

            return self::FAILURE;
        }

        (new Filesystem)->ensureDirectoryExists(app_path('Repositories'));

        $modelClass = $model ? "\\App\\Models\\{$model}" : '';
        $modelImport = $model ? "use {$modelClass};" : '';
        $modelVariable = 'model';
        $modelType = $model ?: 'Model';

        $stub = <<<PHP
<?php

namespace App\Repositories;

$modelImport


/**
 * @extends \App\Repositories\BaseRepo<$modelType>
 */
class {$name} extends \App\Repositories\BaseRepository
{
    protected {$modelType} \${$modelVariable};

    protected function model(): string
    {
        return {$modelType}::class;
    }
}
PHP;

        file_put_contents($repositoryPath, $stub);

        $this->info("Repository '{$name}' created successfully.");

        return self::SUCCESS;
    }
}
