<?php

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class MakeApiScaffoldCommand extends Command
{
    protected $signature = 'make:api-scaffold
                                {name? : Base name, e.g. Currency or StarchFactory}
                                {--model= : Model class name (defaults to {name})}
                                {--prefix= : URL prefix override, e.g. starch-factories (defaults to kebab-plural of name)}
                                {--force : Overwrite existing files}
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


        $this->line("Scaffolding <info>{$name}</info> (model: <info>{$model}</info>)...");

        $generated = [];
        $skipped = [];

        foreach ($this->buildFiles($name, $model) as $label => [$path, $stub]) {
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

        if (! $this->option('no-route')) {
            $prefix = $this->option('prefix') ?: Str::kebab(Str::plural($name));
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
        $controller = "\\App\\Http\\Controllers\\Api\\{$name}Controller";

        $routeBlock = <<<ROUTE

    Route::prefix('v1/{$prefix}')->group(function () {
        Route::get('/', [{$controller}::class, 'index']);
    });

ROUTE;

        // Insertion strategy:
        // 1. If the "mutating-endpoints" anchor exists, use it (legacy/specific layout).
        // 2. Otherwise, append to the end of the file (standard for new Laravel 11+ installs).
        $anchor = "\n});\n\n// Mutating";

        if (str_contains($contents, $anchor)) {
            if (str_contains($contents, "v1/{$prefix}")) {
                $this->warn("  Route prefix 'v1/{$prefix}' already exists in routes/api.php — skipped.");
                return;
            }

            $updated = str_replace($anchor, "\n".$routeBlock."});\n\n// Mutating", $contents);
            file_put_contents($routesFile, $updated);
            $this->info("  Route:   GET /v1/{$prefix} registered in routes/api.php");
        } else {
            if (str_contains($contents, "v1/{$prefix}")) {
                $this->warn("  Route prefix 'v1/{$prefix}' already exists in routes/api.php — skipped.");
                return;
            }

            file_put_contents($routesFile, $contents . $routeBlock);
            $this->info("  Route:   GET /v1/{$prefix} appended to routes/api.php");
        }
    }

    /**
     * Returns all files to generate as [ label => [path, stub] ].
     */
    private function buildFiles(string $name, string $model): array
    {
        $requestName = "{$name}Request";
        
        return [
            "Http/Controllers/Api/{$name}Controller.php" => [
                app_path("Http/Controllers/Api/{$name}Controller.php"),
                $this->controllerStub($name, $model),
            ],
            "Repositories/{$name}Repo.php" => [
                app_path("Repositories/{$name}Repo.php"),
                $this->repoStub($name, $model),
            ],
            "Http/Resources/{$name}Resource.php" => [
                app_path("Http/Resources/{$name}Resource.php"),
                $this->resourceStub($name, $model),
            ],
            "Http/Resources/Collections/{$name}ResourceCollection.php" => [
                app_path("Http/Resources/Collections/{$name}ResourceCollection.php"),
                $this->collectionStub($name),
            ],
            "Http/Requests/{$requestName}.php" => [
                app_path("Http/Requests/{$requestName}.php"),
                $this->requestStub($name, $model),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Stubs
    // -------------------------------------------------------------------------

    private function controllerStub(string $name, string $model): string
    {
        $repoClass = "App\\Repositories\\{$name}Repo";
        $collectionClass = "App\\Http\\Resources\\Collections\\{$name}ResourceCollection";
        $repoVar = lcfirst($name).'Repo';
        $modelVar = lcfirst($name);

        return <<<PHP
<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HasPaginationParams;
use App\Http\Controllers\Controller;
use {$collectionClass};
use {$repoClass};
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
        return <<<PHP
<?php

namespace App\Repositories;

use App\Models\\$model;

/**
 * @extends BaseRepo<$model>
 */
class {$name}Repo extends BaseRepo
{
    protected function model(): string
    {
        return $model::class;
    }
}
PHP;
    }

    private function resourceStub(string $name, string $model): string
    {
        $fields = $this->getFillableFields($model);
        $dateFields = $this->getDateFields($model);
        $fieldLines = $this->buildFieldLines($fields, $dateFields);

        return <<<PHP
<?php

namespace App\Http\Resources;

use App\Models\\$model;
use Illuminate\Http\Request;

/**
 * @mixin {$model}
 */
class {$name}Resource extends BaseJsonResource
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
        return <<<PHP
<?php

namespace App\Http\Resources\Collections;

use App\Http\Resources\\{$name}Resource;
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

    private function requestStub(string $name, string $model): string
    {
        $fields = $this->getFillableFields($model);
        $rules = [];

        foreach ($fields as $field) {
            $rules[] = "            '$field' => ['nullable', 'string'],";
        }

        $rulesBlock = empty($rules) ? "            // No fillable fields found in $model" : implode("\n", $rules);

        return <<<PHP
<?php

namespace App\Http\Requests;

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
        $modelClass = "App\\Models\\$model";

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
