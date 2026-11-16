<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ModelFindUnusedCommandTest extends TestCase
{
    private string $modelsPath;

    private string $searchPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modelsPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'unused-models-'.uniqid();
        $this->searchPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'unused-app-'.uniqid();

        File::ensureDirectoryExists($this->modelsPath);
        File::ensureDirectoryExists($this->searchPath);

        config()->set('artisan-toolkit.paths.model_scan', [$this->modelsPath]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('find_unused_posts');

        File::deleteDirectory($this->modelsPath);
        File::deleteDirectory($this->searchPath);

        parent::tearDown();
    }

    public function test_it_fails_when_search_path_is_missing(): void
    {
        $this->artisan('model:find-unused', [
            '--search' => '/nonexistent/path/for/find/unused',
        ])->assertFailed();
    }

    public function test_it_warns_when_no_models_found(): void
    {
        $this->artisan('model:find-unused', [
            '--search' => $this->searchPath,
        ])->assertSuccessful()
            ->expectsOutputToContain('No models found');
    }

    public function test_it_reports_attributes_that_are_never_referenced(): void
    {
        $this->createPostModel();
        $this->createPostTable();

        // Only "title" is read anywhere in the application.
        File::put($this->searchPath.'/PostController.php', '<?php $post->title;');

        $this->artisan('model:find-unused', [
            '--search' => $this->searchPath,
        ])->assertSuccessful()
            ->expectsOutputToContain('body')
            ->expectsOutputToContain('unused attribute');
    }

    public function test_it_does_not_report_attributes_referenced_in_code(): void
    {
        $this->createPostModel();
        $this->createPostTable();

        File::put($this->searchPath.'/PostController.php', '<?php $post->title; $post->body;');

        $this->artisan('model:find-unused', [
            '--search' => $this->searchPath,
            '--json' => true,
        ])->assertSuccessful();
    }

    public function test_it_skips_the_primary_key_and_timestamps_by_default(): void
    {
        $this->createPostModel();
        $this->createPostTable();

        File::put($this->searchPath.'/PostController.php', '<?php $post->title; $post->body;');

        $this->artisan('model:find-unused', [
            '--search' => $this->searchPath,
            '--include-timestamps' => true,
        ])->assertSuccessful();
    }

    public function test_it_honours_the_exclude_option(): void
    {
        $this->createPostModel();
        $this->createPostTable();

        File::put($this->searchPath.'/PostController.php', '<?php $post->title;');

        $this->artisan('model:find-unused', [
            '--search' => $this->searchPath,
            '--exclude' => ['body'],
        ])->assertSuccessful()
            ->expectsOutputToContain('No unused attributes found');
    }

    public function test_it_skips_models_whose_table_is_missing(): void
    {
        $this->createPostModel();

        $this->artisan('model:find-unused', [
            '--search' => $this->searchPath,
        ])->assertSuccessful()
            ->expectsOutputToContain('Skipping');
    }

    public function test_it_previews_a_fix_migration_without_writing(): void
    {
        $this->createPostModel();
        $this->createPostTable();
        File::put($this->searchPath.'/PostController.php', '<?php $post->title;');

        $this->artisan('model:find-unused', [
            '--search' => $this->searchPath,
            '--fix' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('Dry run');
    }

    public function test_it_writes_a_fix_migration_with_apply(): void
    {
        $migrations = database_path('migrations');
        File::deleteDirectory($migrations);
        File::makeDirectory($migrations, 0755, true);

        $this->createPostModel();
        $this->createPostTable();
        File::put($this->searchPath.'/PostController.php', '<?php $post->title;');

        $this->artisan('model:find-unused', [
            '--search' => $this->searchPath,
            '--fix' => true,
            '--apply' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('Migration written');

        $files = File::files($migrations);
        $this->assertCount(1, $files);

        $content = (string) file_get_contents($files[0]->getPathname());
        $this->assertStringContainsString("dropColumn(['body'])", $content);

        File::deleteDirectory($migrations);
    }

    private function createPostTable(): void
    {
        Schema::create('find_unused_posts', function ($table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });
    }

    private function createPostModel(): void
    {
        $class = 'FindUnusedPost'.uniqid();

        File::put(
            $this->modelsPath.'/'.$class.'.php',
            "<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass {$class} extends Model\n{\n    protected \$table = 'find_unused_posts';\n}\n"
        );

        require_once $this->modelsPath.'/'.$class.'.php';
    }
}
