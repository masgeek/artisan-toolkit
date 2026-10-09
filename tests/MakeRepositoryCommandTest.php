<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Support\Facades\File;

class MakeRepositoryCommandTest extends TestCase
{
    private string $repositoriesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repositoriesPath = app_path('Repositories');
        File::deleteDirectory($this->repositoriesPath);
        File::deleteDirectory(app_path('Domain'));
        File::makeDirectory($this->repositoriesPath, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->repositoriesPath);
        File::deleteDirectory(app_path('Domain'));
        parent::tearDown();
    }

    public function test_it_creates_a_repository(): void
    {
        $this->artisan('make:repo', ['name' => 'UserRepo'])
            ->assertSuccessful();

        $this->assertFileExists($this->repositoriesPath.'/UserRepo.php');

        $content = File::get($this->repositoriesPath.'/UserRepo.php');
        $this->assertStringContainsString('namespace App\Repositories;', $content);
        $this->assertStringContainsString('class UserRepo extends BaseRepo', $content);
        $this->assertStringContainsString('use App\Repositories\BaseRepo;', $content);
    }

    public function test_it_creates_repository_with_model(): void
    {
        $this->artisan('make:repo', [
            'name' => 'PostRepo',
            '--model' => 'Post',
        ])->assertSuccessful();

        $content = File::get($this->repositoriesPath.'/PostRepo.php');
        $this->assertStringContainsString('use App\Models\Post;', $content);
        $this->assertStringContainsString('return Post::class;', $content);
    }

    public function test_it_fails_when_repository_already_exists(): void
    {
        File::put($this->repositoriesPath.'/UserRepo.php', '<?php');

        $this->artisan('make:repo', ['name' => 'UserRepo'])
            ->assertFailed();
    }

    public function test_it_forces_overwrite(): void
    {
        File::put($this->repositoriesPath.'/UserRepo.php', '<?php // stale');

        $this->artisan('make:repo', ['name' => 'UserRepo', '--force' => true])
            ->assertSuccessful();

        $this->assertStringContainsString(
            'class UserRepo',
            File::get($this->repositoriesPath.'/UserRepo.php'),
        );
    }

    public function test_it_creates_repository_without_model_when_omitted(): void
    {
        $this->artisan('make:repo', ['name' => 'CustomerRepo'])
            ->assertSuccessful();

        $content = File::get($this->repositoriesPath.'/CustomerRepo.php');
        $this->assertStringNotContainsString('use \App\Models', $content);
        $this->assertStringContainsString('return Model::class;', $content);
    }

    public function test_it_honours_the_configured_repository_path(): void
    {
        config()->set('artisan-toolkit.paths.repositories', 'app/Domain/Repos');

        $this->artisan('make:repo', ['name' => 'InvoiceRepo'])
            ->assertSuccessful();

        $file = app_path('Domain/Repos/InvoiceRepo.php');
        $this->assertFileExists($file);

        // Namespace is derived from the configured path, not hardcoded.
        $this->assertStringContainsString(
            'namespace App\Domain\Repos;',
            File::get($file),
        );

        File::deleteDirectory(app_path('Domain'));
    }

    public function test_it_honours_the_configured_base_repository_class(): void
    {
        config()->set('artisan-toolkit.base_classes.repository', 'App\Repositories\BaseRepository');

        $this->artisan('make:repo', ['name' => 'LegacyRepo'])
            ->assertSuccessful();

        $content = File::get($this->repositoriesPath.'/LegacyRepo.php');
        $this->assertStringContainsString('class LegacyRepo extends BaseRepository', $content);
        $this->assertStringContainsString('use App\Repositories\BaseRepository;', $content);
    }
}
