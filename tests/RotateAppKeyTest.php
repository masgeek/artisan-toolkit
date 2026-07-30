<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class RotateAppKeyTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/artisan-toolkit-test-'.uniqid();
        File::makeDirectory($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    public function test_it_writes_key_file_when_path_configured(): void
    {
        $keyFilePath = $this->tempDir.'/keys.json';

        $modelClass = $this->createTestModel();
        config()->set('artisan-toolkit.encrypted_models', [
            $modelClass => ['secret_value'],
        ]);
        config()->set('artisan-toolkit.key_storage_path', $keyFilePath);

        $this->artisan('key:generate', ['--force' => true, '--no-env-file' => true])
            ->assertSuccessful();

        $this->assertFileExists($keyFilePath);

        $data = json_decode(File::get($keyFilePath), true);
        $this->assertArrayHasKey('current_key', $data);
        $this->assertArrayHasKey('previous_keys', $data);
        $this->assertArrayHasKey('updated_at', $data);
        $this->assertStringStartsWith('base64:', $data['current_key']);
        $this->assertIsArray($data['previous_keys']);
    }

    public function test_it_does_not_write_key_file_when_path_is_null(): void
    {
        $keyFilePath = $this->tempDir.'/keys.json';

        $modelClass = $this->createTestModel();
        config()->set('artisan-toolkit.encrypted_models', [
            $modelClass => ['secret_value'],
        ]);
        config()->set('artisan-toolkit.key_storage_path', null);

        $this->artisan('key:generate', ['--force' => true, '--no-env-file' => true])
            ->assertSuccessful();

        $this->assertFileDoesNotExist($keyFilePath);
    }

    public function test_it_includes_previous_keys_in_json_file(): void
    {
        $keyFilePath = $this->tempDir.'/keys.json';

        $modelClass = $this->createTestModel();
        config()->set('artisan-toolkit.encrypted_models', [
            $modelClass => ['secret_value'],
        ]);
        config()->set('artisan-toolkit.key_storage_path', $keyFilePath);

        $this->artisan('key:generate', ['--force' => true, '--no-env-file' => true])
            ->assertSuccessful();

        $data = json_decode(File::get($keyFilePath), true);
        $this->assertNotEmpty($data['previous_keys']);
    }

    public function test_it_creates_parent_directory_for_key_file(): void
    {
        $keyFilePath = $this->tempDir.'/nested/dir/keys.json';

        $modelClass = $this->createTestModel();
        config()->set('artisan-toolkit.encrypted_models', [
            $modelClass => ['secret_value'],
        ]);
        config()->set('artisan-toolkit.key_storage_path', $keyFilePath);

        $this->artisan('key:generate', ['--force' => true, '--no-env-file' => true])
            ->assertSuccessful();

        $this->assertFileExists($keyFilePath);
    }

    public function test_it_overwrites_key_file_on_subsequent_rotation(): void
    {
        $keyFilePath = $this->tempDir.'/keys.json';

        $modelClass = $this->createTestModel();
        config()->set('artisan-toolkit.encrypted_models', [
            $modelClass => ['secret_value'],
        ]);
        config()->set('artisan-toolkit.key_storage_path', $keyFilePath);

        $this->artisan('key:generate', ['--force' => true, '--no-env-file' => true])
            ->assertSuccessful();

        $firstRun = json_decode(File::get($keyFilePath), true);
        $firstKey = $firstRun['current_key'];

        $this->artisan('key:generate', ['--force' => true, '--no-env-file' => true])
            ->assertSuccessful();

        $secondRun = json_decode(File::get($keyFilePath), true);
        $this->assertNotEquals($firstKey, $secondRun['current_key']);
        $this->assertContains($firstKey, $secondRun['previous_keys']);
    }

    public function test_it_writes_key_file_on_reverse_rotation(): void
    {
        $keyFilePath = $this->tempDir.'/keys.json';

        $modelClass = $this->createTestModel();
        config()->set('artisan-toolkit.encrypted_models', [
            $modelClass => ['secret_value'],
        ]);
        config()->set('artisan-toolkit.key_storage_path', $keyFilePath);

        $this->artisan('key:generate', ['--force' => true, '--no-env-file' => true])
            ->assertSuccessful();

        $rotatedData = json_decode(File::get($keyFilePath), true);
        $rotatedKey = $rotatedData['current_key'];

        $this->artisan('key:generate', ['--reverse' => true, '--force' => true, '--no-env-file' => true])
            ->assertSuccessful();

        $reversedData = json_decode(File::get($keyFilePath), true);
        $this->assertContains($rotatedKey, $reversedData['previous_keys']);
    }

    public function test_it_returns_single_previous_key_for_first_rotation(): void
    {
        $keyFilePath = $this->tempDir.'/keys.json';
        $originalKey = config('app.key');

        $modelClass = $this->createTestModel();
        config()->set('artisan-toolkit.encrypted_models', [
            $modelClass => ['secret_value'],
        ]);
        config()->set('artisan-toolkit.key_storage_path', $keyFilePath);
        config()->set('app.previous_keys', []);

        $this->artisan('key:generate', ['--force' => true, '--no-env-file' => true])
            ->assertSuccessful();

        $data = json_decode(File::get($keyFilePath), true);
        $this->assertCount(1, $data['previous_keys']);
        $this->assertSame($originalKey, $data['previous_keys'][0]);
    }

    public function test_new_flag_skips_model_checks_and_rotation(): void
    {
        $keyFilePath = $this->tempDir.'/keys.json';

        config()->set('artisan-toolkit.encrypted_models', []);
        config()->set('artisan-toolkit.key_storage_path', $keyFilePath);
        config()->set('app.previous_keys', []);

        $this->artisan('key:generate', ['--new' => true, '--no-env-file' => true])
            ->assertSuccessful();

        $this->assertFileExists($keyFilePath);

        $data = json_decode(File::get($keyFilePath), true);
        $this->assertStringStartsWith('base64:', $data['current_key']);
        $this->assertEmpty($data['previous_keys']);
    }

    /**
     * Create a temporary Eloquent model class on disk, require it, and return its FQCN.
     */
    private function createTestModel(): string
    {
        $tableName = 'key_test_tokens_'.uniqid();
        $className = 'KeyTestToken'.uniqid();
        $fqcn = "App\\Models\\{$className}";

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->text('secret_value')->nullable();
            $table->timestamps();
        });

        $modelDir = $this->tempDir.'/Models';
        File::makeDirectory($modelDir, 0755, true);

        $modelPath = $modelDir.'/'.$className.'.php';
        File::put($modelPath, "<?php\n\nnamespace App\\Models;\n\nclass {$className} extends \\Illuminate\\Database\\Eloquent\\Model\n{\n    protected \$table = '{$tableName}';\n}\n");

        require_once $modelPath;

        return $fqcn;
    }
}
