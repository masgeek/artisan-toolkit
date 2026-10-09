<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Masgeek\ArtisanToolkit\Support\GeneratorPath;

final class MakeApiEndpointCommand extends Command
{
    protected $signature = 'make:api-endpoint 
                            {name : Base name, e.g. Currency} 
                            {method : Method name, e.g. store or update} 
                            {--model= : Model class name}';

    protected $description = 'Add a single endpoint to an API controller and generate its Request/Resource';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly($this->argument('name'));
        $method = $this->argument('method');
        $model = $this->option('model') ?: $name;

        $paths = config('artisan-toolkit.paths');
        $requestsNamespace = GeneratorPath::namespaceFor('requests', 'app/Http/Requests');
        $controllerPath = base_path($paths['api_controllers']."/{$name}Controller.php");

        if (! file_exists($controllerPath)) {
            $this->error("Controller {$name}Controller not found. Run make:api-scaffold first.");
            return self::FAILURE;
        }

        $requestName = "{$name}" . Str::studly($method) . "Request";
        $requestPath = base_path($paths['requests']."/{$requestName}.php");
        $requestClass = "{$requestsNamespace}\\{$requestName}";

        $this->info("Adding {$method} to {$name}Controller...");
        $this->injectMethod($controllerPath, $method, $requestName, $requestClass);

        if (! file_exists($requestPath)) {
            $files->ensureDirectoryExists(dirname($requestPath));
            $files->put($requestPath, $this->requestStub($requestName, $requestsNamespace));
            $this->info("Created Request: {$requestName}");
        }

        return self::SUCCESS;
    }

    private function injectMethod(string $path, string $method, string $requestName, string $requestClass): void
    {
        $content = file_get_contents($path);
        $methodStub = "\n    public function {$method}(\\{$requestClass} \$request)\n    {\n        // Logic here\n    }\n";

        // Insert before last closing brace
        $lastBrace = strrpos($content, '}');
        $updated = substr($content, 0, $lastBrace) . $methodStub . '}';
        file_put_contents($path, $updated);
    }

    private function requestStub(string $name, string $namespace): string
    {
        return "<?php\n\nnamespace {$namespace};\n\nuse Illuminate\\Foundation\\Http\\FormRequest;\n\nclass {$name} extends FormRequest\n{\n    public function authorize(): bool { return true; }\n\n    public function rules(): array { return []; }\n}";
    }
}
