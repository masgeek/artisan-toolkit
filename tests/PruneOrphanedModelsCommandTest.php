<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class PruneOrphanedModelsCommandTest extends TestCase
{
    private string $modelsPath;

    private string $searchPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->modelsPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test-models-'.uniqid();
        $this->searchPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test-app-'.uniqid();
        mkdir($this->modelsPath, 0755, true);
        mkdir($this->searchPath, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modelsPath);
        File::deleteDirectory($this->searchPath);
        parent::tearDown();
    }

    public function test_it_fails_when_models_directory_does_not_exist(): void
    {
        $this->artisan('model:prune-orphaned', [
            '--path' => '/nonexistent/path/to/models',
        ])->assertFailed();
    }

    public function test_it_reports_no_orphans_when_directory_is_empty(): void
    {
        $this->artisan('model:prune-orphaned', [
            '--path'   => $this->modelsPath,
            '--search' => $this->searchPath,
        ])->assertSuccessful();
    }

    public function test_it_detects_model_with_no_table_and_no_references_as_orphaned(): void
    {
        $className = 'OrphanModel'.uniqid();
        $this->writeModelFile($className);

        $this->artisan('model:prune-orphaned', [
            '--path'   => $this->modelsPath,
            '--search' => $this->searchPath,
        ])->assertSuccessful()
            ->expectsOutputToContain($className);
    }

    public function test_it_does_not_flag_model_backed_by_a_table(): void
    {
        $className = 'BackedModel'.uniqid();
        $tableName = strtolower($className);
        $this->writeModelFile($className, $tableName);

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
        });

        $this->artisan('model:prune-orphaned', [
            '--path'   => $this->modelsPath,
            '--search' => $this->searchPath,
        ])->assertSuccessful()
            ->expectsOutputToContain('No orphaned models found');

        Schema::dropIfExists($tableName);
    }

    public function test_it_does_not_flag_model_referenced_in_search_path(): void
    {
        $className = 'ReferencedModel'.uniqid();
        $this->writeModelFile($className);

        file_put_contents(
            $this->searchPath.DIRECTORY_SEPARATOR.'SomeController.php',
            "<?php\nuse App\\Models\\{$className};\nclass SomeController {}"
        );

        $this->artisan('model:prune-orphaned', [
            '--path'   => $this->modelsPath,
            '--search' => $this->searchPath,
        ])->assertSuccessful()
            ->expectsOutputToContain('No orphaned models found');
    }

    public function test_it_deletes_orphaned_model_with_delete_and_force_flags(): void
    {
        $className = 'DeleteOrphanModel'.uniqid();
        $filePath = $this->writeModelFile($className);

        $this->artisan('model:prune-orphaned', [
            '--path'   => $this->modelsPath,
            '--search' => $this->searchPath,
            '--delete' => true,
            '--force'  => true,
        ])->assertSuccessful();

        $this->assertFileDoesNotExist($filePath);
    }

    public function test_it_skips_non_eloquent_php_files(): void
    {
        file_put_contents(
            $this->modelsPath.DIRECTORY_SEPARATOR.'PlainClass.php',
            "<?php\nnamespace App\\Models;\nclass PlainClass {}"
        );

        $this->artisan('model:prune-orphaned', [
            '--path'   => $this->modelsPath,
            '--search' => $this->searchPath,
        ])->assertSuccessful()
            ->expectsOutputToContain('No orphaned models found');
    }

    public function test_it_lists_orphans_without_deleting_when_delete_flag_is_absent(): void
    {
        $className = 'ListOrphanModel'.uniqid();
        $filePath = $this->writeModelFile($className);

        $this->artisan('model:prune-orphaned', [
            '--path'   => $this->modelsPath,
            '--search' => $this->searchPath,
        ])->assertSuccessful();

        $this->assertFileExists($filePath);
    }

    private function writeModelFile(string $className, ?string $table = null): string
    {
        $tableProperty = $table !== null ? "\n    protected \$table = '{$table}';" : '';
        $path = $this->modelsPath.DIRECTORY_SEPARATOR."{$className}.php";

        file_put_contents($path,
            "<?php\nnamespace App\\Models;\nclass {$className} extends \\Illuminate\\Database\\Eloquent\\Model\n{{$tableProperty}\n}\n"
        );

        return $path;
    }
}
