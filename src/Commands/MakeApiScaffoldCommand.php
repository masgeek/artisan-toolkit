<?php

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Masgeek\ArtisanToolkit\Support\GeneratorPath;
use Throwable;

class MakeApiScaffoldCommand extends Command
{
    protected $signature = 'make:api-scaffold
                                {name? : Base name, e.g. Currency or StarchFactory}
                                {--model= : Model class name (defaults to {name})}
                                {--prefix= : URL prefix override, e.g. starch-factories (defaults to kebab-plural of name)}
                                {--force : Overwrite existing files}
                                {--with-routes : Register the index route (default; use --no-route to skip)}
                                {--with-tests : Also generate a feature test for the index endpoint}
                                {--with-factory : Also generate a model factory}
                                {--no-route : Skip automatic route registration}';

    protected $description = 'Scaffold an API controller, repository, resource, and resource collection for a given name';

    public function handle(): int
    {
        $nameArg = $this->argument('name');

        if (! $nameArg) {
            $nameArg = $this->ask('What is the name of the API scaffold?');
            if (! $nameArg) {
                $this->error('The name argument is required.');
                return self::FAILURE;
            }
        }

        $name = Str::studly($nameArg);
        $model = Str::studly($this->option('model') ?: $name);
        $force = $this->option('force');
        $prefix = $this->option('prefix') ?: Str::kebab(Str::plural($name));

        $this->line("Scaffolding <info>{$name}</info> (model: <info>{$model}</info>)...");

        $generated = [];
        $skipped = [];

        $files = $this->buildFiles($name, $model);

        if ($this->option('with-tests')) {
            $files += $this->testFiles($name, $prefix);
        }

        if ($this->option('with-factory')) {
            $files += $this->factoryFiles($model);
        }

        foreach ($files as $label => [$path, $stub]) {
            if (! $force && file_exists($path)) {
                $skipped[] = $label;

                continue;
            }

            (new Filesystem)->ensureDirectoryExists(dirname($path));
            file_put_contents($path, $stub);
            $generated[] = $label;
        }

        foreach ($generated as $label) {
            $this->info("  Created: {$label}");
        }

        foreach ($skipped as $label) {
            $this->warn("  Skipped (already exists): {$label} — use --force to overwrite");
        }

        if (empty($generated)) {
            $this->error('Nothing generated. All files already exist.');

            return self::FAILURE;
        }

        if ($this->option('with-routes') || ! $this->option('no-route')) {
            $this->registerRoute($name, $prefix);
        }

        $this->line('');
        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * Inject a route block into routes/api.php inside the throttle:120,1 group,
     * just before the closing of that group (identified by the mutating-endpoints comment).
     */
    private function registerRoute(string $name, string $prefix): void
    {
        $routesFile = base_path('routes/api.php');

        if (! file_exists($routesFile)) {
            $this->warn('  routes/api.php not found. In Laravel 11+, run "php artisan install:api" to create it.');
            return;
        }

        $contents = file_get_contents($routesFile);
        $version = config('artisan-toolkit.api_version', 'v1');
        $controllerNamespace = GeneratorPath::namespaceFor('api_controllers', 'app/Http/Controllers/Api');
        $controller = "\\{$controllerNamespace}\\{$name}Controller";
        $uri = "{$version}/{$prefix}";

        $routeBlock = <<<ROUTE

    Route::prefix('{$uri}')->group(function () {
        Route::get('/', [{$controller}::class, 'index']);
    });

ROUTE;

        // Insertion strategy:
        // 1. If the "mutating-endpoints" anchor exists, use it (legacy/specific layout).
        // 2. Otherwise, append to the end of the file (standard for new Laravel 11+ installs).
        $anchor = "\n});\n\n// Mutating";

        if (str_contains($contents, $anchor)) {
            if (str_contains($contents, $uri)) {
                $this->warn("  Route prefix '{$uri}' already exists in routes/api.php — skipped.");
                return;
            }

            $updated = str_replace($anchor, "\n".$routeBlock."});\n\n// Mutating", $contents);
            file_put_contents($routesFile, $updated);
            $this->info("  Route:   GET /{$uri} registered in routes/api.php");
        } else {
            if (str_contains($contents, $uri)) {
                $this->warn("  Route prefix '{$uri}' already exists in routes/api.php — skipped.");
                return;
            }

            file_put_contents($routesFile, $contents . $routeBlock);
            $this->info("  Route:   GET /{$uri} appended to routes/api.php");
        }
    }

    /**
     * Returns all files to generate as [ label => [path, stub] ].
     */
    private function buildFiles(string $name, string $model): array
    {
        $requestName = "{$name}Request";

        return [
            "{$name}Controller.php" => [
                GeneratorPath::to('api_controllers', 'app/Http/Controllers/Api')."/{$name}Controller.php",
                $this->controllerStub($name, $model),
            ],
            "{$name}Repo.php" => [
                GeneratorPath::to('repositories', 'app/Repositories')."/{$name}Repo.php",
                $this->repoStub($name, $model),
            ],
            "{$name}Resource.php" => [
                GeneratorPath::to('resources', 'app/Http/Resources')."/{$name}Resource.php",
                $this->resourceStub($name, $model),
            ],
            "{$name}ResourceCollection.php" => [
                GeneratorPath::to('resource_collections', 'app/Http/Resources/Collections')."/{$name}ResourceCollection.php",
                $this->collectionStub($name),
            ],
            "{$requestName}.php" => [
                GeneratorPath::to('requests', 'app/Http/Requests')."/{$requestName}.php",
                $this->requestStub($name, $model),
            ],
        ];
    }

    /**
     * A feature test covering the generated index endpoint.
     *
     * @return array<string, array{string, string}>
     */
    private function testFiles(string $name, string $prefix): array
    {
        $namespace = GeneratorPath::namespaceFor('tests', 'tests/Feature').'\\Api';
        $path = GeneratorPath::to('tests', 'tests/Feature')."/Api/{$name}ControllerTest.php";
        $version = config('artisan-toolkit.api_version', 'v1');
        $uri = "/{$version}/{$prefix}";

        $stub = <<<PHP
<?php

namespace {$namespace};

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class {$name}ControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_a_successful_response(): void
    {
        \$response = \$this->getJson('{$uri}');

        \$response->assertOk();
    }
}
PHP;

        return ["{$name}ControllerTest.php" => [$path, $stub]];
    }

    /**
     * A model factory stub.
     *
     * @return array<string, array{string, string}>
     */
    private function factoryFiles(string $model): array
    {
        $namespace = GeneratorPath::namespaceFor('factories', 'database/factories');
        $path = GeneratorPath::to('factories', 'database/factories')."/{$model}Factory.php";
        $modelClass = GeneratorPath::modelClass($model);

        $stub = <<<PHP
<?php

namespace {$namespace};

use {$modelClass};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<{$model}>
 */
class {$model}Factory extends Factory
{
    protected \$model = {$model}::class;

    public function definition(): array
    {
        return [
            //
        ];
    }
}
PHP;

        return ["{$model}Factory.php" => [$path, $stub]];
    }

    // -------------------------------------------------------------------------
    // Stubs
    // -------------------------------------------------------------------------

    private function controllerStub(string $name, string $model): string
    {
        $namespace = GeneratorPath::namespaceFor('api_controllers', 'app/Http/Controllers/Api');
        $repoNamespace = GeneratorPath::namespaceFor('repositories', 'app/Repositories');
        $collectionNamespace = GeneratorPath::namespaceFor('resource_collections', 'app/Http/Resources/Collections');
        $repoClass = "{$repoNamespace}\\{$name}Repo";
        $collectionClass = "{$collectionNamespace}\\{$name}ResourceCollection";
        $repoVar = lcfirst($name).'Repo';

        return <<<PHP
<?php

namespace {$namespace};

use {$collectionClass};
use {$repoClass};
use App\Http\Concerns\HasPaginationParams;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class {$name}Controller extends Controller
{
    use HasPaginationParams;

    public function __construct(protected {$name}Repo \${$repoVar})
    {
    }

    public function index(Request \$request): {$name}ResourceCollection
    {
        \$perPage = \$this->getPerPage(\$request);
        \$orderBy = \$this->getOrderBy(\$request, ['created_at'], 'created_at');
        \$sort    = \$this->getSortDirection(\$request);

        \$items = \$this->{$repoVar}->paginateWithSort(
            perPage: \$perPage,
            orderBy: \$orderBy,
            direction: \$sort,
        );

        return {$name}ResourceCollection::make(\$items);
    }
}
PHP;
    }

    private function repoStub(string $name, string $model): string
    {
        $namespace = GeneratorPath::namespaceFor('repositories', 'app/Repositories');
        $baseClass = $this->baseClass('repository', 'App\\Repositories\\BaseRepo');
        $baseShort = class_basename($baseClass);
        $modelClass = GeneratorPath::modelClass($model);

        return <<<PHP
<?php

namespace {$namespace};

use {$baseClass};
use {$modelClass};

class {$name}Repo extends {$baseShort}
{
    protected function model(): string
    {
        return {$model}::class;
    }
}
PHP;
    }

    private function resourceStub(string $name, string $model): string
    {
        $namespace = GeneratorPath::namespaceFor('resources', 'app/Http/Resources');
        $baseClass = $this->baseClass('resource', 'Illuminate\\Http\\Resources\\Json\\JsonResource');
        $baseShort = class_basename($baseClass);
        $modelClass = GeneratorPath::modelClass($model);

        $fields = $this->getFillableFields($model);
        $dateFields = $this->getDateFields($model);
        $fieldLines = $this->buildFieldLines($fields, $dateFields);

        return <<<PHP
<?php

namespace {$namespace};

use {$baseClass};
use {$modelClass};
use Illuminate\Http\Request;

/**
 * @mixin {$model}
 */
class {$name}Resource extends {$baseShort}
{
    public function toArray(Request \$request): array
    {
        return [
{$fieldLines}
        ];
    }
}
PHP;
    }

    private function collectionStub(string $name): string
    {
        $namespace = GeneratorPath::namespaceFor('resource_collections', 'app/Http/Resources/Collections');
        $resourceNamespace = GeneratorPath::namespaceFor('resources', 'app/Http/Resources');
        $resourceClass = "{$resourceNamespace}\\{$name}Resource";

        return <<<PHP
<?php

namespace {$namespace};

use {$resourceClass};
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class {$name}ResourceCollection extends ResourceCollection
{
    public function toArray(Request \$request): array
    {
        return [
            'data' => {$name}Resource::collection(\$this->collection),
        ];
    }
}
PHP;
    }

    /**
     * Resolve a configured base class FQCN for generated stubs.
     */
    private function baseClass(string $key, string $default): string
    {
        $class = config("artisan-toolkit.base_classes.{$key}");

        return is_string($class) && $class !== '' ? trim($class, '\\') : $default;
    }

    private function requestStub(string $name, string $model): string
    {
        $namespace = GeneratorPath::namespaceFor('requests', 'app/Http/Requests');
        $fields = $this->getFillableFields($model);
        $rules = [];

        foreach ($fields as $field) {
            $rules[] = "            '$field' => ['nullable', 'string'],";
        }

        $rulesBlock = empty($rules) ? "            // No fillable fields found in $model" : implode("\n", $rules);

        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Foundation\Http\FormRequest;

class {$name}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
{$rulesBlock}
        ];
    }
}
PHP;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getFillableFields(string $model): array
    {
        $modelClass = GeneratorPath::modelClass($model);

        if (! class_exists($modelClass)) {
            return [];
        }

        try {
            $instance = app($modelClass);

            return Schema::getColumnListing($instance->getTable());
        } catch (Throwable) {
            return [];
        }
    }

    private function getDateFields(string $model): array
    {
        $modelClass = "App\\Models\\$model";

        if (! class_exists($modelClass)) {
            return [];
        }

        try {
            $instance = app($modelClass);

            $dateCastTypes = ['date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp'];

            $castFields = collect($instance->getCasts())
                ->filter(fn ($type) => in_array(strtolower($type), $dateCastTypes, true))
                ->keys()
                ->all();

            // Also include the standard Eloquent timestamp columns
            $timestampFields = $instance->usesTimestamps()
                ? [$instance->getCreatedAtColumn(), $instance->getUpdatedAtColumn()]
                : [];

            return array_unique(array_merge($castFields, $timestampFields));
        } catch (Throwable) {
            return [];
        }
    }

    private function buildFieldLines(array $fields, array $dateFields = []): string
    {
        if (empty($fields)) {
            return "            // 'id' => \$this->id,";
        }

        return collect($fields)
            ->map(function ($field) use ($dateFields) {
                if (in_array($field, $dateFields, true)) {
                    return "            '$field' => \$this->formatDate(\$this->$field),";
                }

                return "            '$field' => \$this->$field,";
            })
            ->implode("\n");
    }
}
