<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class MakeApiScaffoldCommandTest extends TestCase
{
    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->paths = [
            'controller' => app_path('Http/Controllers/Api'),
            'repo' => app_path('Repositories'),
            'resource' => app_path('Http/Resources'),
            'collection' => app_path('Http/Resources/Collections'),
        ];

        foreach ($this->paths as $path) {
            File::deleteDirectory($path);
            File::makeDirectory($path, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            File::deleteDirectory($path);
        }
        File::deleteDirectory(app_path('Http/Controllers'));
        File::deleteDirectory(app_path('Repositories'));
        if (File::exists(base_path('routes/api.php'))) {
            File::delete(base_path('routes/api.php'));
        }
        Schema::dropIfExists('scaffold_test_models');
        parent::tearDown();
    }

    public function test_it_creates_all_scaffold_files(): void
    {
        $this->artisan('make:api-scaffold', [
            'name' => 'Currency',
            '--no-route' => true,
        ])->assertSuccessful();

        $this->assertFileExists($this->paths['controller'] . '/CurrencyController.php');
        $this->assertFileExists($this->paths['repo'] . '/CurrencyRepo.php');
        $this->assertFileExists($this->paths['resource'] . '/CurrencyResource.php');
        $this->assertFileExists($this->paths['collection'] . '/CurrencyResourceCollection.php');
    }

    public function test_it_creates_scaffold_with_custom_model(): void
    {
        $this->artisan('make:api-scaffold', [
            'name' => 'Currency',
            '--model' => 'MyCurrency',
            '--no-route' => true,
        ])->assertSuccessful();

        $repoContent = File::get($this->paths['repo'] . '/CurrencyRepo.php');
        $this->assertStringContainsString('MyCurrency::class', $repoContent);
    }

    public function test_it_skipped_when_partial_files_exist(): void
    {
        File::put($this->paths['controller'] . '/CurrencyController.php', '<?php');

        $this->artisan('make:api-scaffold', [
            'name' => 'Currency',
            '--no-route' => true,
        ])->assertSuccessful();

        $this->assertStringEqualsStringIgnoringLineEndings(
            '<?php',
            File::get($this->paths['controller'] . '/CurrencyController.php')
        );

        $this->assertFileExists($this->paths['repo'] . '/CurrencyRepo.php');
    }

    public function test_it_fails_when_all_files_exist_without_force(): void
    {
        File::put($this->paths['controller'] . '/CurrencyController.php', '<?php');
        File::put($this->paths['repo'] . '/CurrencyRepo.php', '<?php');
        File::put($this->paths['resource'] . '/CurrencyResource.php', '<?php');
        File::put($this->paths['collection'] . '/CurrencyResourceCollection.php', '<?php');

        $this->artisan('make:api-scaffold', [
            'name' => 'Currency',
            '--no-route' => true,
        ])->assertFailed();
    }

    public function test_it_forces_overwrite(): void
    {
        File::put($this->paths['controller'] . '/CurrencyController.php', '<?php // old');
        File::put($this->paths['repo'] . '/CurrencyRepo.php', '<?php // old');
        File::put($this->paths['resource'] . '/CurrencyResource.php', '<?php // old');
        File::put($this->paths['collection'] . '/CurrencyResourceCollection.php', '<?php // old');

        $this->artisan('make:api-scaffold', [
            'name' => 'Currency',
            '--force' => true,
            '--no-route' => true,
        ])->assertSuccessful();

        $content = File::get($this->paths['controller'] . '/CurrencyController.php');
        $this->assertStringContainsString('namespace App\Http\Controllers\Api', $content);
    }

    public function test_it_generates_namespaced_scaffold(): void
    {
        $this->artisan('make:api-scaffold', [
            'name' => 'Admin/Currency',
            '--no-route' => true,
        ])->assertSuccessful();

        $this->assertFileExists($this->paths['controller'] . '/Admin/CurrencyController.php');
    }

    public function test_it_registers_route_with_default_prefix(): void
    {
        File::ensureDirectoryExists(base_path('routes'));
        File::put(base_path('routes/api.php'), "<?php\n\nRoute::middleware('throttle:120,1')->group(function () {\n\n});\n\n// Mutating");

        $this->artisan('make:api-scaffold', ['name' => 'Currency'])
            ->assertSuccessful();

        $routesContent = File::get(base_path('routes/api.php'));
        $this->assertStringContainsString("v1/currencies", $routesContent);
        $this->assertStringContainsString("CurrencyController::class", $routesContent);
    }

    public function test_it_registers_route_with_custom_prefix(): void
    {
        File::ensureDirectoryExists(base_path('routes'));
        File::put(base_path('routes/api.php'), "<?php\n\nRoute::middleware('throttle:120,1')->group(function () {\n\n});\n\n// Mutating");

        $this->artisan('make:api-scaffold', [
            'name' => 'Currency',
            '--prefix' => 'my-currencies',
        ])->assertSuccessful();

        $routesContent = File::get(base_path('routes/api.php'));
        $this->assertStringContainsString("v1/my-currencies", $routesContent);
    }

    public function test_it_skips_duplicate_route_prefix(): void
    {
        File::ensureDirectoryExists(base_path('routes'));
        File::put(base_path('routes/api.php'), "<?php\n\nRoute::middleware('throttle:120,1')->group(function () {\n\n});\n\n// Mutating");

        $this->artisan('make:api-scaffold', ['name' => 'Currency'])
            ->assertSuccessful();

        $this->artisan('make:api-scaffold', [
            'name' => 'Currency',
            '--force' => true,
        ])->assertSuccessful();

        $routesContent = File::get(base_path('routes/api.php'));
        $this->assertEquals(1, substr_count($routesContent, "v1/currencies"));
    }

    public function test_it_warns_on_missing_route_anchor(): void
    {
        File::ensureDirectoryExists(base_path('routes'));
        File::put(base_path('routes/api.php'), "<?php\n\n// No mutating anchor in this file");

        $this->artisan('make:api-scaffold', ['name' => 'Currency'])
            ->assertSuccessful();

        $routesContent = File::get(base_path('routes/api.php'));
        $this->assertStringNotContainsString("v1/currencies", $routesContent);
    }

    public function test_it_generates_resource_stub_with_placeholder_fields(): void
    {
        $this->artisan('make:api-scaffold', [
            'name' => 'Currency',
            '--no-route' => true,
        ])->assertSuccessful();

        $resourceContent = File::get($this->paths['resource'] . '/CurrencyResource.php');
        $this->assertStringContainsString("// 'id' => \$this->id,", $resourceContent);
        $this->assertStringContainsString('class CurrencyResource extends BaseJsonResource', $resourceContent);
    }

    public function test_it_generates_correct_repo_stub(): void
    {
        $this->artisan('make:api-scaffold', [
            'name' => 'Currency',
            '--model' => 'Currency',
            '--no-route' => true,
        ])->assertSuccessful();

        $repoContent = File::get($this->paths['repo'] . '/CurrencyRepo.php');
        $this->assertStringContainsString('class CurrencyRepo extends BaseRepo', $repoContent);
        $this->assertStringContainsString('return Currency::class;', $repoContent);
        $this->assertStringContainsString('@extends BaseRepo<Currency>', $repoContent);
    }

    public function test_it_generates_correct_collection_stub(): void
    {
        $this->artisan('make:api-scaffold', [
            'name' => 'Currency',
            '--no-route' => true,
        ])->assertSuccessful();

        $collectionContent = File::get($this->paths['collection'] . '/CurrencyResourceCollection.php');
        $this->assertStringContainsString('CurrencyResource::collection($this->collection)', $collectionContent);
        $this->assertStringContainsString('class CurrencyResourceCollection extends ResourceCollection', $collectionContent);
    }

    public function test_it_generates_correct_controller_stub(): void
    {
        $this->artisan('make:api-scaffold', [
            'name' => 'Currency',
            '--no-route' => true,
        ])->assertSuccessful();

        $controllerContent = File::get($this->paths['controller'] . '/CurrencyController.php');
        $this->assertStringContainsString('class CurrencyController extends Controller', $controllerContent);
        $this->assertStringContainsString('CurrencyRepo $currencyRepo', $controllerContent);
        $this->assertStringContainsString('CurrencyResourceCollection', $controllerContent);
    }

    public function test_it_generates_resource_with_model_fields(): void
    {
        Schema::create('scaffold_test_models', function ($table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        eval('namespace App\Models { class ScaffoldTestModel extends \Illuminate\Database\Eloquent\Model {
            protected $table = "scaffold_test_models";
        }}');

        $this->artisan('make:api-scaffold', [
            'name' => 'ScaffoldItem',
            '--model' => 'ScaffoldTestModel',
            '--no-route' => true,
        ])->assertSuccessful();

        $resourceContent = File::get($this->paths['resource'] . '/ScaffoldItemResource.php');
        $this->assertStringContainsString("'id' => \$this->id,", $resourceContent);
        $this->assertStringContainsString("'title' => \$this->title,", $resourceContent);
        $this->assertStringContainsString("'description' => \$this->description,", $resourceContent);
        $this->assertStringContainsString("'created_at' => \$this->formatDate(\$this->created_at),", $resourceContent);
        $this->assertStringContainsString("'updated_at' => \$this->formatDate(\$this->updated_at),", $resourceContent);
    }

    public function test_it_falls_back_to_placeholders_when_model_table_missing(): void
    {
        eval('namespace App\Models { class ScaffoldNoTableModel extends \Illuminate\Database\Eloquent\Model {
            protected $table = "nonexistent_scaffold_table";
        }}');

        $this->artisan('make:api-scaffold', [
            'name' => 'NoTableItem',
            '--model' => 'ScaffoldNoTableModel',
            '--no-route' => true,
        ])->assertSuccessful();

        $resourceContent = File::get($this->paths['resource'] . '/NoTableItemResource.php');
        $this->assertStringContainsString("// 'id' => \$this->id,", $resourceContent);
    }
}
