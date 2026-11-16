<?php

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use Throwable;

class ListModelRelationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'model:relations 
                            {model : The fully qualified class name of the model} 
                            {--depth=1 : Recurse into related models up to this depth} 
                            {--json : Emit results as JSON}';

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

        $depth = max(1, (int) $this->option('depth'));

        $relations = $this->collect($modelClass, $depth);

        if ($this->option('json')) {
            $this->line((string) json_encode(
                array_map(static fn (array $relation): array => [
                    'name' => $relation['name'],
                    'path' => $relation['path'],
                    'type' => $relation['type'],
                    'target' => $relation['target'],
                    'inverse' => $relation['inverse'],
                    'pivot' => $relation['pivot'],
                    'depth' => $relation['depth'],
                ], $relations),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return Command::SUCCESS;
        }

        if ($relations === []) {
            $this->info("No relationships found in $modelClass or its base model.");

            return Command::SUCCESS;
        }

        $this->info("Relationships in $modelClass (including base model):");

        $headers = $depth > 1
            ? ['Path', 'Type', 'Target Model', 'Inverse', 'Pivot']
            : ['Method', 'Type', 'Target Model', 'Inverse', 'Pivot'];

        $this->table($headers, array_map(
            static fn (array $relation): array => [
                $depth > 1 ? $relation['path'] : $relation['name'],
                $relation['type'],
                $relation['target'],
                $relation['inverse'] ?? '—',
                $relation['pivot'] ?? '—',
            ],
            $relations,
        ));

        return Command::SUCCESS;
    }

    /**
     * Collect relations breadth-first up to the given depth.
     *
     * @return list<array{name: string, path: string, type: string, target: string, target_class: string|null, inverse: string|null, pivot: string|null, depth: int}>
     */
    private function collect(string $modelClass, int $maxDepth): array
    {
        $rows = [];
        $queue = [[$modelClass, '', 0, []]];

        while ($queue !== []) {
            [$class, $prefix, $depth, $ancestors] = array_shift($queue);

            if ($depth >= $maxDepth || in_array($class, $ancestors, true) || ! class_exists($class)) {
                continue;
            }

            try {
                $instance = app($class);
            } catch (Throwable) {
                continue;
            }

            $relations = $depth === 0
                ? $this->getModelRelations($class)
                : $this->extractRelationships($instance);

            foreach ($relations as $relation) {
                $path = $prefix === '' ? $relation['name'] : $prefix.'.'.$relation['name'];
                $relation['path'] = $path;
                $relation['depth'] = $depth;
                $rows[] = $relation;

                if ($relation['target_class'] !== null) {
                    $queue[] = [$relation['target_class'], $path, $depth + 1, [...$ancestors, $class]];
                }
            }
        }

        return $rows;
    }

    /**
     * Dynamically generate the base model class by inserting 'Base\Models' into the model class name.
     */
    private function getBaseModelClass(string $modelClass): string
    {
        return str_replace('App\Models', 'App\Models\Base', $modelClass);
    }

    /**
     * Get all relationships defined in the model and its base model.
     *
     * @throws ReflectionException
     */
    private function getModelRelations(string $modelClass): array
    {
        $relations = [];

        $baseModelClass = str_starts_with($modelClass, 'App\\Models\\Base')
            ? $modelClass
            : $this->getBaseModelClass($modelClass);

        if ($baseModelClass !== $modelClass && class_exists($baseModelClass)) {
            try {
                $relations = array_merge($relations, $this->extractRelationships(app($baseModelClass)));
            } catch (Throwable) {
                // Ignore an uninstantiable base model.
            }
        }

        try {
            $relations = array_merge($relations, $this->extractRelationships(app($modelClass)));
        } catch (Throwable) {
            return $relations;
        }

        $unique = [];
        foreach ($relations as $relation) {
            $unique[$relation['name']] = $relation;
        }

        return array_values($unique);
    }

    /**
     * @return list<array{name: string, type: string, target: string, target_class: string|null, inverse: string|null, pivot: string|null}>
     * @throws ReflectionException
     */
    private function extractRelationships($model): array
    {
        $relations = [];
        $methods = (new ReflectionClass($model))->getMethods();

        foreach ($methods as $method) {
            if ($method->class !== get_class($model)) {
                continue;
            }

            if ($method->getNumberOfParameters() !== 0) {
                continue;
            }

            $returnType = $method->getReturnType();

            if (! $returnType instanceof ReflectionNamedType || ! is_subclass_of($returnType->getName(), Relation::class)) {
                continue;
            }

            $typeName = (new ReflectionClass($returnType->getName()))->getShortName();
            $target = 'Unknown';
            $targetClass = null;
            $inverse = null;
            $pivot = null;

            try {
                $relation = $method->invoke($model);

                if ($relation instanceof Relation) {
                    $related = $relation->getRelated();
                    $targetClass = get_class($related);
                    $target = class_basename($related);
                    $inverse = $this->resolveInverse($related, get_class($model));

                    if ($relation instanceof BelongsToMany) {
                        $columns = $relation->getPivotColumns();
                        $pivot = $columns === [] ? null : implode(', ', $columns);
                    }
                }
            } catch (Throwable) {
                // Leave the target unresolved when the relation cannot be built.
            }

            $relations[] = [
                'name' => $method->name,
                'type' => $typeName,
                'target' => $target,
                'target_class' => $targetClass,
                'inverse' => $inverse,
                'pivot' => $pivot,
            ];
        }

        return $relations;
    }

    /**
     * Find the relation method on the related model that points back to the given class.
     */
    private function resolveInverse(object $related, string $currentClass): ?string
    {
        try {
            $methods = (new ReflectionClass($related))->getMethods();
        } catch (Throwable) {
            return null;
        }

        foreach ($methods as $method) {
            if ($method->class !== get_class($related) || $method->getNumberOfParameters() !== 0) {
                continue;
            }

            $returnType = $method->getReturnType();

            if (! $returnType instanceof ReflectionNamedType || ! is_subclass_of($returnType->getName(), Relation::class)) {
                continue;
            }

            try {
                $relation = $method->invoke($related);

                if ($relation instanceof Relation && get_class($relation->getRelated()) === $currentClass) {
                    return $method->name;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }
}
