<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Masgeek\ArtisanToolkit\Support\GeneratorPath;
use Masgeek\ArtisanToolkit\Support\ModelColumns;
use Throwable;

final class MakeRequestCommand extends Command
{
    protected $signature = 'make:request 
                            {name : The request class name (e.g. StoreUserRequest)} 
                            {--model= : Infer validation rules from the model\'s columns} 
                            {--force : Overwrite an existing request} 
                            {--dry-run : Show the file that would be created without writing it}';

    protected $description = 'Create a FormRequest, optionally inferring rules from a model';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly($this->argument('name'));
        $modelName = $this->option('model');

        $namespace = GeneratorPath::namespaceFor('requests', 'app/Http/Requests');
        $filePath = GeneratorPath::to('requests', 'app/Http/Requests')."/{$name}.php";

        if ($files->exists($filePath) && ! $this->option('force')) {
            $this->error("Request [{$name}] already exists. Use --force to overwrite.");

            return self::FAILURE;
        }

        $rules = $modelName ? $this->rulesFromModel($modelName) : [];

        if ($this->option('dry-run')) {
            $this->line(" <fg=cyan>[dry-run]</> Would create: {$filePath}");

            return self::SUCCESS;
        }

        $files->ensureDirectoryExists(dirname($filePath));
        $files->put($filePath, $this->buildContent($name, $namespace, $rules, $modelName));

        $this->info("Request [{$filePath}] created successfully.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, list<string>>
     */
    private function rulesFromModel(string $modelName): array
    {
        $modelClass = GeneratorPath::modelClass($modelName);

        if (! class_exists($modelClass)) {
            $this->warn("Model class {$modelClass} not found. Generating an empty rules array.");

            return [];
        }

        $system = $this->systemColumns($modelClass);
        $rules = [];

        foreach (ModelColumns::forModel($modelClass) as $column) {
            if (in_array(strtolower($column['name']), $system, true)) {
                continue;
            }

            $rules[$column['name']] = ModelColumns::rules($column);
        }

        return $rules;
    }

    /**
     * Primary key, timestamp and soft-delete columns are not user input.
     *
     * @return list<string> Lowercased column names
     */
    private function systemColumns(string $modelClass): array
    {
        try {
            $model = app($modelClass);
            $columns = [$model->getKeyName()];

            if ($model->usesTimestamps()) {
                $columns[] = $model->getCreatedAtColumn();
                $columns[] = $model->getUpdatedAtColumn();
            }

            if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                $columns[] = $model->getDeletedAtColumn();
            }
        } catch (Throwable) {
            return [];
        }

        return array_values(array_unique(array_map('strtolower', array_filter($columns))));
    }

    /**
     * @param  array<string, list<string>>  $rules
     */
    private function buildContent(string $name, string $namespace, array $rules, ?string $modelName): string
    {
        if ($rules === []) {
            $rulesBlock = $modelName
                ? "            // No inferable columns for {$modelName}"
                : '            //';
        } else {
            $rulesBlock = collect($rules)
                ->map(fn (array $ruleSet, string $field): string => "            '{$field}' => ['".implode("', '", $ruleSet)."'],")
                ->implode("\n");
        }

        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Foundation\Http\FormRequest;

class {$name} extends FormRequest
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
}
