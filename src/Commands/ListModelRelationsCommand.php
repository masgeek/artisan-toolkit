<?php

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

class ListModelRelationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'model:relations {model : The fully qualified class name of the model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all relationships defined in an Eloquent model';

    /**
     * Execute the console command.
     *
     * @noinspection PhpClassConstantAccessedViaChildClassInspection
     * @throws ReflectionException
     */
    public function handle(): int
    {
        $modelClass = $this->argument('model');

        if (! class_exists($modelClass)) {
            $this->error("Class $modelClass does not exist.");

            return Command::FAILURE;
        }

        // Check if the model is already in the base namespace
        if (str_starts_with($modelClass, 'App\\Models\\Base')) {
            $baseModelClass = $modelClass;
        } else {
            $baseModelClass = $this->getBaseModelClass($modelClass);
        }

        if (! class_exists($baseModelClass)) {
            $this->warn("Base model $baseModelClass does not exist.");
            $baseModelClass = null;
        }

        // Extract relationships with metadata
        $relations = $this->getModelRelations(
            modelClass: new $modelClass,
            baseModelClass: $baseModelClass ? new $baseModelClass : null,
        );

        if (empty($relations)) {
            $this->info("No relationships found in $modelClass or its base model.");
        } else {
            $this->info("Relationships in $modelClass (including base model):");
            $this->table(
                ['Method', 'Type', 'Target Model'],
                $relations
            );
        }

        return Command::SUCCESS;
    }

    /**
     * Dynamically generate the base model class by inserting 'Base\Models' into the model class name.
     */
    private function getBaseModelClass(string $modelClass): string
    {
        return str_replace('App\Models', 'App\Models\Base', $modelClass);
    }

    /**
     * Get all relationships defined in the model.
     * @throws ReflectionException
     */
    private function getModelRelations($modelClass, $baseModelClass = null): array
    {
        $relations = [];

        // Get relationships from the base model
        $relations = array_merge($relations, $this->extractRelationships($baseModelClass));

        // Get relationships from the model itself
        $relations = array_merge($relations, $this->extractRelationships($modelClass));

        // Remove duplicates based on method name
        $unique = [];
        foreach ($relations as $rel) {
            $unique[$rel['name']] = $rel;
        }

        return array_values($unique);
    }

    /**
     * @param $model
     * @return array
     * @throws ReflectionException
     */
    private function extractRelationships($model): array
    {
        if ($model === null) {
            return [];
        }

        $relations = [];
        $methods = (new ReflectionClass($model))->getMethods();

        foreach ($methods as $method) {
            if ($method->class !== get_class($model)) {
                continue;
            }

            if ($method->getNumberOfParameters() === 0) {
                $returnType = $method->getReturnType();
                if ($returnType instanceof ReflectionNamedType && is_subclass_of($returnType->getName(), Relation::class)) {
                    $typeClass = $returnType->getName();
                    $typeName = (new ReflectionClass($typeClass))->getShortName();
                    
                    // Try to find target model from return type or method body (simplified)
                    $target = 'Unknown';
                    
                    $relations[] = [
                        'name' => $method->name,
                        'type' => $typeName,
                        'target' => $target,
                    ];
                }
            }
        }

        return $relations;
    }
}
