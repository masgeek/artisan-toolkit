<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class ModelFillableAuditCommandTest extends TestCase
{
    private string $modelsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->modelsPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fillable-audit-models-'.uniqid();
        File::ensureDirectoryExists($this->modelsPath);
        config()->set('artisan-toolkit.paths.model_scan', [$this->modelsPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modelsPath);
        Schema::dropIfExists('fillable_audit_test');
        parent::tearDown();
    }

    private function createTable(): void
    {
        Schema::create('fillable_audit_test', function ($table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->json('meta');
            $table->boolean('published');
            $table->integer('views');
            $table->timestamps();
        });
    }

    private function createModel(): void
    {
        $class = 'FillableAuditTest'.uniqid();
        $contents = "<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass {$class} extends Model\n{\n    protected \$table = 'fillable_audit_test';\n    protected \$fillable = ['title', 'ghost'];\n    protected \$casts = ['meta' => 'array', 'missing' => 'integer'];\n}\n";
        File::put($this->modelsPath.'/'.$class.'.php', $contents);
        require_once $this->modelsPath.'/'.$class.'.php';
    }

    public function test_it_reports_fillable_and_cast_drift(): void
    {
        $this->createTable();
        $this->createModel();

        $this->artisan('model:fillable-audit')
            ->assertSuccessful()
            ->expectsOutputToContain('ghost') // missing-fillable
            ->expectsOutputToContain('missing') // missing-cast
            ->expectsOutputToContain('body') // unfillable
            ->expectsOutputToContain('no cast defined'); // uncast suggestion for published and views

    }

    public function test_json_output_contains_expected_issues(): void
    {
        $this->createTable();
        $this->createModel();

        Artisan::call('model:fillable-audit', ['--json' => true]);
        $output = Artisan::output();
        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $kinds = array_column($data['issues'], 'kind');
        $this->assertContains('missing-fillable', $kinds);
        $this->assertContains('missing-cast', $kinds);
        $this->assertContains('unfillable', $kinds);
        $this->assertContains('uncast', $kinds);
    }
}
