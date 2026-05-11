<?php

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MakeFullResourceCommand extends Command
{
    protected $signature = 'make:resource-full {name} {--model=} {--force} {--with-relationships}';

    protected $description = 'Create an API Resource and Resource Collection, optionally using a model';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $model = $this->option('model');

        // Infer model from a resource name if not provided
        if (! $model && Str::endsWith($name, 'Resource')) {
            $model = Str::before($name, 'Resource');
        }

        $resourcePath = app_path("Http/Resources/{$name}.php");
        $collectionDir = app_path('Http/Resources/Collections');
        $collectionPath = $collectionDir."/{$name}Collection.php";

        if (file_exists($resourcePath) || file_exists($collectionPath)) {
            $this->error('One or both resources already exist. Use --force to overwrite.');

            return false;
        }

        (new Filesystem)->ensureDirectoryExists(app_path('Http/Resources'));
        (new Filesystem)->ensureDirectoryExists($collectionDir);

        $modelImport = $model ? "use App\\Models\\{$model};" : '';
        $modelDoc = $model ? "@var {$model}" : '';

        // Generate fillable fields for toArray()
        $fields = $this->getFillableFields($model);
        $fieldLines = '';
        if (! empty($fields)) {
            $fieldLines = collect($fields)->map(function ($field) {
                return "            '{$field}' => \$model->{$field},";
            })->implode("\n");
        } else {
            $fieldLines = "            // 'id' => \$model->id,\n            // 'name' => \$model->name,";
        }

        // Generate relationships if a with-relationships flag is passed
        $relationships = [];
        $relationLines = '';
        $resourceUses = '';

        if ($this->option('with-relationships')) {
            $relationships = $this->getRelationships($model);

            if (count($relationships)) {
                $relationLines = collect($relationships)->map(function ($isCollection, $relation) {
                    $resourceName = Str::studly(Str::singular($relation)).'Resource';

                    return $isCollection
                        ? "            '{$relation}' => {$resourceName}::collection(\$model->{$relation}),"
                        : "            '{$relation}' => new {$resourceName}(\$model->{$relation}),";
                })->implode("\n");

                // Auto generate missing related resources only if with-relationships flag is passed
                foreach ($relationships as $relation => $isCollection) {
                    $relatedResourceName = Str::studly(Str::singular($relation)).'Resource';
                    $this->generateMissingResource($relatedResourceName);
                }

                // Add use statements for related resources
                $resourceUses = collect($relationships)->map(function ($_, $relation) {
                    $resourceName = Str::studly(Str::singular($relation)).'Resource';

                    return "use App\\Http\\Resources\\{$resourceName};";
                })->implode("\n");

                if ($resourceUses) {
                    $resourceUses = "\n".$resourceUses;
                }
            }
        }

        // Resource Stub
        $resourceStub = <<<PHP
<?php

namespace App\Http\Resources;

$modelImport$resourceUses

class {$name} extends \Illuminate\Http\Resources\Json\JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @var {$model} \$this->resource
     */
    public function toArray(\$request):array
    {
        /** @var {$model} \$model */
        \$model = \$this->resource;

        return [
{$fieldLines}
{$relationLines}
        ];
    }
}
PHP;

        // Collection Stub
        $collectionStub = <<<PHP
<?php

namespace App\Http\Resources\Collections;

class {$name}Collection extends \Illuminate\Http\Resources\Json\ResourceCollection
{
    public function toArray(\$request):array
    {
        return [
            'data' => \\App\\Http\\Resources\\{$name}::collection(\$this->collection),
        ];
    }
}
PHP;

        file_put_contents($resourcePath, $resourceStub);
        file_put_contents($collectionPath, $collectionStub);

        $this->info("Resource '{$name}' and 'Collections/{$name}Collection' created successfully.");
    }

    /**
     * Get fillable fields for the resource's model.
     */
    protected function getFillableFields($model): ?array
    {
        $modelClass = "App\\Models\\{$model}";

        if (! class_exists($modelClass)) {
            return null;
        }

        try {
            $instance = app($modelClass);

            return Schema::getColumnListing($instance->getTable());
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get relationships for the resource's model.
     */
    protected function getRelationships($model): array
    {
        $modelClass = "App\\Models\\{$model}";

        if (! class_exists($modelClass)) {
            return [];
        }

        $instance = app($modelClass);
        $reflection = new \ReflectionClass($instance);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        $relationships = [];

        foreach ($methods as $method) {
            if ($method->getNumberOfParameters() === 0 && $method->class === $modelClass) {
                try {
                    $return = $method->invoke($instance);
                    if ($return instanceof Relation) {
                        $name = $method->getName();
                        $isCollection = in_array(class_basename($return), ['HasMany', 'BelongsToMany', 'MorphMany']);
                        $relationships[$name] = $isCollection;
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        return $relationships;
    }

    /**
     * Generate missing related resource class.
     */
    protected function generateMissingResource($resourceName): void
    {
        $resourcePath = app_path("Http/Resources/{$resourceName}.php");

        if (file_exists($resourcePath)) {
            return;
        }

        $stub = <<<PHP
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class {$resourceName} extends JsonResource
{
    public function toArray(\$request)
    {
        return parent::toArray(\$request);
    }
}
PHP;

        file_put_contents($resourcePath, $stub);
        $this->info("Generated missing resource: {$resourceName}");
    }
}
