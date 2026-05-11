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
        File::makeDirectory($this->repositoriesPath, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->repositoriesPath);
        parent::tearDown();
    }

    public function test_it_creates_a_repository(): void
    {
        $this->artisan('make:repo', ['name' => 'UserRepo'])
            ->assertSuccessful();

        $this->assertFileExists($this->repositoriesPath . '/UserRepo.php');

        $content = File::get($this->repositoriesPath . '/UserRepo.php');
        $this->assertStringContainsString('namespace App\Repositories;', $content);
        $this->assertStringContainsString('class UserRepo extends \App\Repositories\BaseRepository', $content);
    }

    public function test_it_creates_repository_with_model(): void
    {
        $this->artisan('make:repo', [
            'name' => 'PostRepo',
            '--model' => 'Post',
        ])->assertSuccessful();

        $content = File::get($this->repositoriesPath . '/PostRepo.php');
        $this->assertStringContainsString('use \App\Models\Post;', $content);
        $this->assertStringContainsString('Post $model', $content);
    }

    public function test_it_fails_when_repository_already_exists(): void
    {
        File::put($this->repositoriesPath . '/UserRepo.php', '<?php');

        $this->artisan('make:repo', ['name' => 'UserRepo'])
            ->assertFailed();
    }

    public function test_it_creates_repository_without_model_when_omitted(): void
    {
        $this->artisan('make:repo', ['name' => 'CustomerRepo'])
            ->assertSuccessful();

        $content = File::get($this->repositoriesPath . '/CustomerRepo.php');
        $this->assertStringNotContainsString('use App\Models', $content);
        $this->assertStringContainsString('model', $content);
    }
}
