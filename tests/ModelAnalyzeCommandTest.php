<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ModelAnalyzeCommandTest extends TestCase
{
    private string $modelsPath;

    /** Conventional name so table inference ("analyze_widgets") resolves too. */
    private const MODEL = 'AnalyzeWidget';

    private const TABLE = 'analyze_widgets';

    protected function setUp(): void
    {
        parent::setUp();

        $this->modelsPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'analyze-models-'.uniqid();

        File::ensureDirectoryExists($this->modelsPath);
        $this->writeModel();

        config()->set('artisan-toolkit.paths.model_scan', [$this->modelsPath]);
        config()->set('artisan-toolkit.model_base_namespace', 'App\\Models');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists(self::TABLE);

        File::deleteDirectory($this->modelsPath);

        parent::tearDown();
    }

    public function test_it_reports_columns_and_casts(): void
    {
        $this->createTable();

        $this->artisan('model:analyze', ['model' => 'App\\Models\\'.self::MODEL])
            ->assertSuccessful()
            ->expectsOutputToContain(self::TABLE)
            ->expectsOutputToContain('name')
            ->expectsOutputToContain('price');
    }

    public function test_it_resolves_a_short_model_name(): void
    {
        $this->createTable();

        $this->artisan('model:analyze', ['model' => self::MODEL])
            ->assertSuccessful()
            ->expectsOutputToContain(self::TABLE);
    }

    public function test_it_resolves_a_table_name(): void
    {
        $this->createTable();

        $this->artisan('model:analyze', ['model' => self::TABLE])
            ->assertSuccessful()
            ->expectsOutputToContain(self::TABLE);
    }

    public function test_it_resolves_via_a_configured_scan_path(): void
    {
        $this->createTable();

        // Drop the base namespace hint so only the scan path can resolve it.
        config()->set('artisan-toolkit.model_base_namespace', 'App\\DoesNotExist');

        $this->artisan('model:analyze', ['model' => self::MODEL])
            ->assertSuccessful()
            ->expectsOutputToContain(self::TABLE);
    }

    public function test_it_fails_for_an_unresolvable_model(): void
    {
        $this->artisan('model:analyze', ['model' => 'NotARealModelAnywhere'])
            ->assertFailed()
            ->expectsOutputToContain('Could not resolve model class');
    }

    private function createTable(): void
    {
        Schema::create(self::TABLE, function ($table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    private function writeModel(): void
    {
        $file = $this->modelsPath.'/'.self::MODEL.'.php';

        File::put(
            $file,
            "<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass ".self::MODEL." extends Model\n{\n    protected \$table = '".self::TABLE."';\n}\n"
        );

        // A fixed class name would redeclare across test methods in one process.
        if (! class_exists('App\\Models\\'.self::MODEL, false)) {
            require_once $file;
        }
    }
}
