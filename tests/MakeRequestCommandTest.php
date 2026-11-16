<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class MakeRequestCommandTest extends TestCase
{
    private string $requestsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestsPath = app_path('Http/Requests');
        File::deleteDirectory($this->requestsPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->requestsPath);
        Schema::dropIfExists('request_samples');

        parent::tearDown();
    }

    public function test_it_creates_a_plain_request(): void
    {
        $this->artisan('make:request', ['name' => 'StoreWidgetRequest'])
            ->assertSuccessful();

        $file = $this->requestsPath.'/StoreWidgetRequest.php';
        $this->assertFileExists($file);

        $content = File::get($file);
        $this->assertStringContainsString('namespace App\Http\Requests;', $content);
        $this->assertStringContainsString('class StoreWidgetRequest extends FormRequest', $content);
        $this->assertStringContainsString('use Illuminate\Foundation\Http\FormRequest;', $content);
    }

    public function test_it_infers_rules_from_a_model(): void
    {
        Schema::create('request_samples', function ($table) {
            $table->id();
            $table->string('reference');
            $table->integer('quantity');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        eval('namespace App\Models; class RequestSampleModel extends \Illuminate\Database\Eloquent\Model {
            protected $table = "request_samples";
        }');

        $this->artisan('make:request', [
            'name' => 'StoreSampleRequest',
            '--model' => 'RequestSampleModel',
        ])->assertSuccessful();

        $content = File::get($this->requestsPath.'/StoreSampleRequest.php');
        $this->assertStringContainsString("'reference' => ['required', 'string']", $content);
        $this->assertStringContainsString("'quantity' => ['required', 'integer']", $content);
        $this->assertStringContainsString("'notes' => ['nullable', 'string']", $content);
        // Primary key and timestamps are not user input.
        $this->assertStringNotContainsString("'id' =>", $content);
        $this->assertStringNotContainsString("'created_at' =>", $content);
    }

    public function test_it_warns_for_an_unknown_model(): void
    {
        $this->artisan('make:request', [
            'name' => 'StoreMissingRequest',
            '--model' => 'NotARealModel',
        ])->assertSuccessful()
            ->expectsOutputToContain('not found');
    }

    public function test_it_fails_when_the_request_exists(): void
    {
        File::ensureDirectoryExists($this->requestsPath);
        File::put($this->requestsPath.'/StoreWidgetRequest.php', '<?php');

        $this->artisan('make:request', ['name' => 'StoreWidgetRequest'])
            ->assertFailed();
    }

    public function test_it_forces_overwrite(): void
    {
        File::ensureDirectoryExists($this->requestsPath);
        File::put($this->requestsPath.'/StoreWidgetRequest.php', '<?php // stale');

        $this->artisan('make:request', [
            'name' => 'StoreWidgetRequest',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertStringContainsString(
            'class StoreWidgetRequest',
            File::get($this->requestsPath.'/StoreWidgetRequest.php'),
        );
    }

    public function test_it_supports_dry_run(): void
    {
        $this->artisan('make:request', [
            'name' => 'StoreWidgetRequest',
            '--dry-run' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('dry-run');

        $this->assertFileDoesNotExist($this->requestsPath.'/StoreWidgetRequest.php');
    }

    public function test_it_honours_the_configured_request_path(): void
    {
        config()->set('artisan-toolkit.paths.requests', 'app/Domain/Requests');

        $this->artisan('make:request', ['name' => 'StoreWidgetRequest'])
            ->assertSuccessful();

        $file = app_path('Domain/Requests/StoreWidgetRequest.php');
        $this->assertFileExists($file);
        $this->assertStringContainsString('namespace App\Domain\Requests;', File::get($file));

        File::deleteDirectory(app_path('Domain'));
    }
}
