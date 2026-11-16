<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Masgeek\ArtisanToolkit\Support\GeneratorPath;
use Masgeek\ArtisanToolkit\Support\ModelColumns;
use ReflectionClass;
use Throwable;

final class MakeDtoCommand extends Command
{
    protected $signature = 'make:dto 
                            {name : The DTO class name (e.g. UserData)} 
                            {--model= : Model to derive attributes from} 
                            {--from-request= : FormRequest class to derive attributes from} 
                            {--force : Overwrite an existing DTO} 
                            {--dry-run : Show the file that would be created without writing it}';

    protected $description = 'Create a Data Transfer Object (DTO) class';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly($this->argument('name'));
        $modelName = $this->option('model');
        $requestClass = $this->option('from-request');

        $namespace = GeneratorPath::namespaceFor('dtos', 'app/DTOs');
        $filePath = GeneratorPath::to('dtos', 'app/DTOs')."/{$name}.php";

        if ($files->exists($filePath) && ! $this->option('force')) {
            $this->error("DTO [{$name}] already exists. Use --force to overwrite.");

            return self::FAILURE;
        }

        $properties = [];

        if ($requestClass) {
            $properties = $this->propertiesFromRequest($requestClass);
        } elseif ($modelName) {
            $properties = $this->propertiesFromModel($modelName);
        }

        $content = $this->buildContent($name, $properties, $namespace);

        if ($this->option('dry-run')) {
            $this->line(" <fg=cyan>[dry-run]</> Would create: {$filePath}");

            return self::SUCCESS;
        }

        $files->ensureDirectoryExists(dirname($filePath));
        $files->put($filePath, $content);

        $this->info("DTO [{$filePath}] created successfully.");

        return self::SUCCESS;
    }

    /**
     * @return list<array{name: string, type: string}>
     */
    private function propertiesFromModel(string $modelName): array
    {
        $modelClass = GeneratorPath::modelClass($modelName);

        if (! class_exists($modelClass)) {
            $this->warn("Model class {$modelClass} not found. Generating empty DTO.");

            return [];
        }

        $columns = ModelColumns::forModel($modelClass);

        if ($columns === []) {
            $this->warn("Could not fetch columns for model {$modelName}. Generating empty DTO.");

            return [];
        }

        return array_map(
            static fn (array $column): array => [
                'name' => $column['name'],
                'type' => ModelColumns::phpType($column),
            ],
            $columns,
        );
    }

    /**
     * Derive typed properties from a FormRequest's rules().
     *
     * @return list<array{name: string, type: string}>
     */
    private function propertiesFromRequest(string $requestClass): array
    {
        $requestClass = ltrim($requestClass, '\\');

        if (! class_exists($requestClass)) {
            $this->warn("Request class {$requestClass} not found. Generating empty DTO.");

            return [];
        }

        try {
            // Avoid the container: resolving a FormRequest triggers validation.
            $request = (new ReflectionClass($requestClass))->newInstanceWithoutConstructor();
            $rules = method_exists($request, 'rules') ? (array) $request->rules() : [];
        } catch (Throwable) {
            $this->warn("Could not read rules from {$requestClass}. Generating empty DTO.");

            return [];
        }

        $properties = [];

        foreach ($rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', (string) $ruleSet);
            $names = array_map(
                static fn (mixed $rule): string => is_string($rule) ? strtolower(trim(explode(':', $rule)[0])) : '',
                $rules,
            );

            $type = match (true) {
                in_array('integer', $names, true) => 'int',
                in_array('boolean', $names, true) => 'bool',
                in_array('numeric', $names, true) => 'float',
                in_array('array', $names, true) => 'array',
                default => 'string',
            };

            $properties[] = [
                'name' => (string) $field,
                'type' => in_array('nullable', $names, true) ? $type.'|null' : $type,
            ];
        }

        return $properties;
    }

    /**
     * @param  list<array{name: string, type: string}>  $properties
     */
    private function buildContent(string $name, array $properties, string $namespace): string
    {
        $propLines = [];
        $constructorParams = [];
        $assignments = [];

        foreach ($properties as $property) {
            $field = $property['name'];
            $type = $property['type'];
            $propLines[] = "    public readonly {$type} \${$field};";
            $constructorParams[] = "{$type} \${$field}";
            $assignments[] = "        \$this->{$field} = \${$field};";
        }

        if (empty($propLines)) {
            $propLines[] = '    // Add properties here';
            $constructorParams[] = 'array $data';
            $assignments[] = '        // Map data to properties here';
        }

        return implode("\n", [
            '<?php',
            '',
            "namespace {$namespace};",
            '',
            "final class {$name}",
            '{',
            implode("\n", $propLines),
            '',
            '    public function __construct(',
            implode(', ', $constructorParams),
            ')',
            '    {',
            implode("\n", $assignments),
            '    }',
            '}',
        ]);
    }
}
