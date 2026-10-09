<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final class MakeDtoCommand extends Command
{
    protected $signature = 'make:dto 
                            {name : The DTO class name (e.g. UserData)} 
                            {--model= : Model to derive attributes from}';

    protected $description = 'Create a Data Transfer Object (DTO) class';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly($this->argument('name'));
        $modelName = $this->option('model');
        
        $filePath = app_path("DTOs/{$name}.php");
        
        if ($files->exists($filePath)) {
            $this->error("DTO [{$name}] already exists.");
            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($filePath));
        
        $properties = [];
        if ($modelName) {
            $modelClass = "App\\Models\\{$modelName}";
            if (class_exists($modelClass)) {
                try {
                    $instance = app($modelClass);
                    $properties = Schema::getColumnListing($instance->getTable());
                } catch (Throwable) {
                    $this->warn("Could not fetch columns for model {$modelName}. Generating empty DTO.");
                }
            } else {
                $this->warn("Model class {$modelClass} not found. Generating empty DTO.");
            }
        }

        $content = $this->buildContent($name, $properties);
        $files->put($filePath, $content);

        $this->info("DTO [{$filePath}] created successfully.");
        return self::SUCCESS;
    }

    private function buildContent(string $name, array $properties): string
    {
        $propLines = [];
        $constructorParams = [];
        $assignments = [];

        foreach ($properties as $prop) {
            $studlyProp = Str::studly($prop);
            $propLines[] = "    public readonly string|int|float|bool|null \${$prop};";
            $constructorParams[] = "\${$prop}";
            $assignments[] = "        \$this->{$prop} = \${$prop};";
        }

        if (empty($propLines)) {
            $propLines[] = "    // Add properties here";
            $constructorParams[] = "array \$data";
            $assignments[] = "        // Map data to properties here";
        }

        return implode("\n", [
            '<?php',
            '',
            'namespace App\\DTOs;',
            '',
            "final class {$name}",
            '{',
            implode("\n", $propLines),
            '',
            "    public function __construct(",
            implode(", ", $constructorParams),
            ")",
            '    {',
            implode("\n", $assignments),
            '    }',
            '}',
        ]);
    }
}
