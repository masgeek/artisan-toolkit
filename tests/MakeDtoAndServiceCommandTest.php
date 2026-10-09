<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class MakeDtoAndServiceCommandTest extends TestCase
{
    private string $dtosPath;

    private string $servicesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dtosPath = app_path('DTOs');
        $this->servicesPath = app_path('Services');

        File::deleteDirectory($this->dtosPath);
        File::deleteDirectory($this->servicesPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dtosPath);
        File::deleteDirectory($this->servicesPath);

        parent::tearDown();
    }

    public function test_it_creates_a_dto(): void
    {
        $this->artisan('make:dto', ['name' => 'UserData'])->assertSuccessful();

        $file = $this->dtosPath.'/UserData.php';
        $this->assertFileExists($file);

        $content = File::get($file);
        $this->assertStringContainsString('namespace App\DTOs;', $content);
        $this->assertStringContainsString('final class UserData', $content);
    }

    public function test_it_fails_when_the_dto_already_exists(): void
    {
        File::ensureDirectoryExists($this->dtosPath);
        File::put($this->dtosPath.'/UserData.php', '<?php');

        $this->artisan('make:dto', ['name' => 'UserData'])->assertFailed();
    }

    public function test_it_creates_a_dto_with_properties_from_a_model(): void
    {
        Schema::create('dto_samples', function ($table) {
            $table->id();
            $table->string('reference');
            $table->integer('quantity');
        });

        eval('namespace App\Models; class DtoSampleModel extends \Illuminate\Database\Eloquent\Model {
            protected $table = "dto_samples";
        }');

        $this->artisan('make:dto', ['name' => 'Sample', '--model' => 'DtoSampleModel'])
            ->assertSuccessful();

        $content = File::get($this->dtosPath.'/Sample.php');
        $this->assertStringContainsString('$reference', $content);
        $this->assertStringContainsString('$quantity', $content);

        Schema::dropIfExists('dto_samples');
    }

    public function test_it_warns_for_an_unknown_dto_model(): void
    {
        $this->artisan('make:dto', ['name' => 'Sample', '--model' => 'NotARealModel'])
            ->assertSuccessful()
            ->expectsOutputToContain('not found');
    }

    public function test_it_creates_a_service(): void
    {
        $this->artisan('make:service', ['name' => 'Billing'])->assertSuccessful();

        $file = $this->servicesPath.'/BillingService.php';
        $this->assertFileExists($file);

        $content = File::get($file);
        $this->assertStringContainsString('namespace App\Services;', $content);
        $this->assertStringContainsString('final class BillingService', $content);
    }

    public function test_it_fails_when_the_service_already_exists(): void
    {
        File::ensureDirectoryExists($this->servicesPath);
        File::put($this->servicesPath.'/BillingService.php', '<?php');

        $this->artisan('make:service', ['name' => 'Billing'])->assertFailed();
    }

    public function test_generated_files_honour_configured_paths(): void
    {
        config()->set('artisan-toolkit.paths.dtos', 'app/Domain/DTOs');
        config()->set('artisan-toolkit.paths.services', 'app/Domain/Services');

        $this->artisan('make:dto', ['name' => 'UserData'])->assertSuccessful();
        $this->artisan('make:service', ['name' => 'Billing'])->assertSuccessful();

        $dto = app_path('Domain/DTOs/UserData.php');
        $service = app_path('Domain/Services/BillingService.php');

        $this->assertFileExists($dto);
        $this->assertFileExists($service);

        // Namespace follows the configured path rather than the default.
        $this->assertStringContainsString('namespace App\Domain\DTOs;', File::get($dto));
        $this->assertStringContainsString('namespace App\Domain\Services;', File::get($service));

        File::deleteDirectory(app_path('Domain'));
    }
}
